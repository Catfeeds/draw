<?php

namespace App\Http\Controllers\Web;

use App\Model\Active;
use App\Model\ActivePrize;
use App\Model\Award;
use App\Model\Prize;
use App\Model\Sign;
use App\Model\WxUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Ixudra\Curl\Facades\Curl;
use Validator;
use JWTFactory;
use JWTAuth;

class DrawController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login']]);
    }

    /**
     * 登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'code' => 'required',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $app_id = env('APP_ID');
            $app_secret = env('APP_SECRET');
            $code = $request->input('code');
            $url = "https://api.weixin.qq.com/sns/jscode2session?appid=$app_id&secret=$app_secret&js_code=$code&grant_type=authorization_code";
            $response = Curl::to($url)->get();
            $response = json_decode($response, true);

            Log::info($response);
            if (isset($response['errcode'])) {
                return $this->error($response['errmsg']);
            }

            $wx_user = WxUser::query()->where('wx_username', $response['openid'])->first();
            if (empty($wx_user)) {
                $wx_user = new WxUser;
                $wx_user->wx_username = $response['openid'];
                if (!$wx_user->save()) {
                    return $this->error('保存用户信息失败');
                }
            }

            $token = auth('api')->fromUser($wx_user);
            return $this->response([
                'token' => $token,
                'unionid' => isset($response['unionid']) ? $response['unionid'] : '',
                'token_type' => 'bearer',
                'expire_in' => auth('api')->factory()->getTTL() * 60
            ]);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->error();
        }
    }

    /**
     * 活动首页
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // 解析token
            $wx_user = JWTAuth::parseToken()->authenticate();
            // 获取活动信息
            $active = Active::query()
                ->select(['active_id', 'active_name', 'must_award', 'created_at'])
                ->where('enable', 1)
                ->orderBy('created_at', 'desc')
                ->first();
            $active->prizes;

            // 每日第一次登陆签到
            $continuous = 0;
            $key = 'php_first_login_' . $wx_user->wx_user_id . '_' . date('Ymd');
            $first_login = Redis::exists($key);
            if (empty($first_login)) {
                DB::beginTransaction();
                // 写入签到记录
                $sign = new Sign;
                $sign->created_at = time();
                $sign->ip = $request->getClientIp();
                $sign->wx_user_id = $wx_user->wx_user_id;
                if (!$sign->save()) {
                    return $this->error('签到失败');
                }
                // 增加用户抽奖次数
                $wx_user->draw_number++;
                if (!$wx_user->save()) {
                    return $this->error('更新用户抽奖次数失败');
                }
                // 每日首次登陆标记
                $res = Redis::setex($key, 90000, 1);
                if (!$res) {
                    DB::rollBack();
                    return $this->error('签到失败');
                }
                DB::commit();

                // 统计登陆天数
                $today_end_time = strtotime(date('Ymd')) + 86400;
                for ($i = 0; $i < 1; $i++) {
                    $sign_day = Sign::query()
                        ->where('wx_user_id', $wx_user->wx_user_id)
                        ->where('created_at', '>=', $today_end_time - 86400)
                        ->where('created_at', '<', $today_end_time)
                        ->count();
                    if ($sign_day > 0) {
                        $continuous++;
                    } else {
                        break;
                    }
                }

                // 判断是否登陆N天必中
                $must_award_day = $active->must_award;
                $before_time = $today_end_time - $must_award_day * 86400;
                $sign_day = Sign::query()
                    ->where('wx_user_id', $wx_user->wx_user_id)
                    ->whereBetween('created_at', [$before_time, $today_end_time])
                    ->count();
                if ($sign_day >= $must_award_day) {
                    $res = Sign::query()
                        ->where('wx_user_id', $wx_user->wx_user_id)
                        ->whereBetween('created_at', [$before_time, $today_end_time])
                        ->update(['enable_draw' => 1]);
                    if (!$res) {
                        return $this->error('更新签到天数失败');
                    }
                    // 满足连续签到N天必中
                    Redis::setex('php_must_award_' . $wx_user->wx_user_id . '_' . date('Ymd'), 9000, 1);
                }
                $active['first_login'] = true;
            } else {
                $active['first_login'] = false;
            }
            $active['continuous'] = $continuous;
            $active['draw_number'] = $wx_user->draw_number;
            return $this->response($active);
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error();
        }
    }

    /**
     * 抽奖
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function draw(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'active_id' => 'required|int'
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $wx_user = JWTAuth::parseToken()->authenticate();
            if ($wx_user['draw_number'] - 1 < 0) {
                return $this->error('没有抽奖机会了');
            }
            // 查询抽奖信息
            $active = Active::query()
                ->select(['active_id', 'active_name', 'must_award', 'created_at'])
                ->where('enable', 1)
                ->orderBy('created_at', 'desc')
                ->first();
            $active->prizes;

            if (empty($active)) {
                return $this->error('活动不存在');
            }
            // 奖项数组
            $chance = 0;
            $data = [];
            foreach ($active['prizes'] as $key => $value) {
                // 奖品没有库存则从奖项数组中剔除
                if ($value['active_surplus_number'] <= 0) {
                    continue;
                }
                // 当天奖品数量已抽完，从奖项数组中剔除
                // 每天奖品抽奖数量使用key标记，抽中递增
                $keyword = 'php_prize_num_' . $value['prize_id'] . '_' . date('Ymd');
                $exists = Redis::exists($keyword);
                if ($exists) {
                    $every_day_number = Redis::get($keyword);
                    if ($every_day_number >= $value['every_day_number']) {
                        continue;
                    }
                } else {
                    Redis::setex($keyword, 90000, 0);
                }
                $chance += $value['chance'];
                $data[$key]['prize_id'] = $value['prize_id'];
                $data[$key]['prize_name'] = $value['prize_name'];
                $data[$key]['chance'] = $value['chance'];
                $data[$key]['award_level'] = $value['award_level'];
            }
            $must_award = Redis::exists('php_must_award_' . $wx_user->wx_user_id . '_' . date('Ymd'));
            if ($must_award) {
                Redis::del('php_must_award_' . $wx_user->wx_user_id . '_' . date('Ymd'));
            } else {
                // 不是N天必中，中奖概率不足100，使用未中奖填充
                if ($chance < 100) {
                    $data[] = [
                        'prize_id' => 0,
                        'chance' => 100 - $chance,
                        'award_level' => 0
                    ];
                }
            }
            DB::beginTransaction();
            // 开始抽奖
            $award = $this->getRand($data);
            // 更新抽奖次数
            $wx_user->draw_number--;
            if (!$wx_user->save()) {
                DB::rollBack();
                return $this->error('更新用户抽奖次数失败');
            }
            // 中将处理
            if ($award['award_level'] != 0) {
                // 检查库存
                $active_prize = ActivePrize::query()->where([
                    'active_id' => $active->active_id,
                    'prize_id' => $award['prize_id']
                ])->first();
                // 库存不足，响应未中奖
                if (empty($active_prize) || $active_prize->active_surplus_number - 1 < 0) {
                    return $this->response([
                        'prize_id' => 0,
                        'prize_name' => '谢谢惠顾',
                        'award_level' => 0
                    ]);
                }
                // 今日奖品抽奖数量加一
                Redis::incr('php_prize_num_' . $award['prize_id'] . '_' . date('Ymd'));
                // 中奖更新活动奖品表库存
                $active_prize->active_surplus_number--;
                if (!$active_prize->save()) {
                    DB::rollBack();
                    return $this->error('更新活动奖品库存失败');
                }
                // 更新总奖品表库存
                $prize = Prize::query()->find($award['prize_id']);
                if (empty($prize) || $prize->surplus_number - 1 < 0) {
                    DB::rollBack();
                    return $this->response([
                        'prize_id' => 0,
                        'prize_name' => '谢谢惠顾',
                        'award_level' => 0
                    ]);
                }
                $prize->surplus_number--;
                if (!$prize->save()) {
                    DB::rollBack();
                    return $this->error('更新奖品库存失败');
                }
                // 写入中奖记录
                $award_record = new Award;
                $award_record->prize_id = $award['prize_id'];
                $award_record->prize_name = $award['prize_name'];
                $award_record->award_level = $award['award_level'];
                $award_record->active_id = $active['active_id'];
                $award_record->wx_user_id = $wx_user['wx_user_id'];
                if (!$award_record->save()) {
                    DB::rollBack();
                    return $this->error();
                }
                // 抽奖成功
                DB::commit();
                return $this->response([
                    'prize_id' => $award['prize_id'],
                    'prize_name' => $award['prize_name'],
                    'award_level' => $award['award_level']
                ]);
            } else {
                DB::commit();
                // 未中奖
                return $this->response([
                    'prize_id' => 0,
                    'prize_name' => '谢谢惠顾',
                    'award_level' => 0
                ]);
            }
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            return $this->error();
        }
    }

    function incDrawNumber(Request $request)
    {
        $wx_user = JWTAuth::parseToken()->authenticate();
        $keyword = 'php_prize_num_' . $wx_user->wx_user_id . '_' . date('Ymd');
        $exists = Redis::exists($keyword);
        if ($exists) {
            return $this->error('今日已经分享过');
        } else {
            Redis::setex($keyword, 90000, 0);
            DB::beginTransaction();
//            $wx_user->
        }
    }

    /**
     * 从抽奖数组选取奖项
     * @param $proArr
     * @return array
     */
    function getRand($proArr)
    {
        $result = array();
        foreach ($proArr as $key => $val) {
            $arr[$key] = $val['chance'];
        }
        // 概率数组的总概率
        $proSum = array_sum($arr);
        asort($arr);
        // 概率数组循环
        foreach ($arr as $k => $v) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $v) {
                $result = $proArr[$k];
                break;
            } else {
                $proSum -= $v;
            }
        }
        return $result;
    }
}
