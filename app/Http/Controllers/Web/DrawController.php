<?php

namespace App\Http\Controllers\Web;

use App\Model\Active;
use App\Model\ActivePrize;
use App\Model\Award;
use App\Model\BusinessHall;
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
     * 保存用户信息
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveWxUserInfo(Request $request)
    {
        $wx_user = JWTAuth::parseToken()->authenticate();
        $head = $request->input('avatarUrl', '');
        $wx_nickname = $request->input('nickName', '');
        $gender = $request->input('gender', 1);
        $country = $request->input('country', '');
        $city = $request->input('city', '');
        $province = $request->input('province', '');
        if (!empty($head)) {
            $wx_user->head = $head;
        }
        if (!empty($wx_nickname)) {
            $wx_user->wx_nickname = $wx_nickname;
        }
        if (!empty($gender)) {
            $wx_user->gender = $gender;
        }
        if (!empty($country)) {
            $wx_user->country = $country;
        }
        if (!empty($city)) {
            $wx_user->city = $city;
        }
        if (!empty($province)) {
            $wx_user->province = $province;
        }
        if (!$wx_user->save()) {
            return $this->error('保存用户信息失败');
        }
        return $this->success('保存用户信息成功');
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
            $today_timestamp = strtotime(date('Ymd'));
            $first_login = Sign::query()
                ->where('wx_user_id', $wx_user->wx_user_id)
                ->where('created_at', '>=', $today_timestamp)
                ->where('created_at', '<', $today_timestamp + 86400)
                ->count();
            if ($first_login == 0) {
                $yesterday_timestamp = strtotime(date('Ymd', strtotime('-1 day')));
                $yesterday_sign = Sign::query()
                    ->where('wx_user_id', $wx_user->wx_user_id)
                    ->where('created_at', '>=', $yesterday_timestamp)
                    ->where('created_at', '<', strtotime(date('Ymd')))
                    ->count();
                if ($yesterday_sign > 0) {
                    $continuous = $wx_user->sign_days + 1;
                } else {
                    $continuous = 1;
                }
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
                $wx_user->increment('draw_number');
                $wx_user->sign_days = $continuous;
                if (!$wx_user->save()) {
                    return $this->error('更新用户抽奖次数失败');
                }

                // 判断是否登陆N天必中
                $must_award_day = $active->must_award;
                $before_time = ($today_timestamp + 86400) - $must_award_day * 86400;
                $sign_day = Sign::query()
                    ->where('enable_draw', 0)
                    ->where('wx_user_id', $wx_user->wx_user_id)
                    ->whereBetween('created_at', [$before_time, $today_timestamp + 86400])
                    ->count();
                if ($sign_day >= $must_award_day) {
                    $res = Sign::query()
                        ->where('wx_user_id', $wx_user->wx_user_id)
                        ->whereBetween('created_at', [$before_time, $today_timestamp + 86400])
                        ->update(['enable_draw' => 1]);
                    if (!$res) {
                        return $this->error('更新签到天数失败');
                    }
                    // 满足连续签到N天必中
                    Redis::setex(date('Ymd') . '_php_must_award_' . $wx_user->wx_user_id, 9000, 1);
                }
                DB::commit();
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
            $must_award_prize = [];
            foreach ($active['prizes'] as $key => $value) {
                // 奖品没有库存则从奖项数组中剔除
                if ($value['active_surplus_number'] <= 0) {
                    continue;
                }
                // 当天奖品数量已抽完，从奖项数组中剔除
                // 每天奖品抽奖数量使用key标记，抽中递增
                $keyword = date('Ymd') . '_php_prize_num_' . $value['prize_id'];
                $exists = Redis::exists($keyword);
                if ($exists) {
                    $every_day_number = Redis::get($keyword);
                    if ($every_day_number >= $value['every_day_number']) {
                        continue;
                    }
                } else {
                    Redis::setex($keyword, 90000, 0);
                }
                // 签到必中奖品不放入奖项数组
                if ($value['must_award_prize'] == 0) {
                    $must_award_prize[]['prize_id'] = $value['prize_id'];
                    $must_award_prize[]['prize_name'] = $value['prize_name'];
                    $must_award_prize[]['chance'] = $value['chance'];
                    $must_award_prize[]['award_level'] = $value['award_level'];
                    continue;
                }
                $chance += $value['chance'];
                $data[$key]['prize_id'] = $value['prize_id'];
                $data[$key]['prize_name'] = $value['prize_name'];
                $data[$key]['chance'] = $value['chance'];
                $data[$key]['award_level'] = $value['award_level'];
            }
            $must_award = Redis::exists(date('Ymd') . '_php_must_award_' . $wx_user->wx_user_id);
            if ($must_award) {
                $data = $must_award_prize;
                $result = Redis::del(date('Ymd') . '_php_must_award_' . $wx_user->wx_user_id);
                if (empty($result)) {
                    return $this->error('更新连续签到必中失败');
                }
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
            // 开始抽奖
            DB::beginTransaction();
            $award = $this->getRand($data);
            // 更新抽奖次数
            $wx_user->decrement('draw_number');
            if (!$wx_user->save()) {
                DB::rollBack();
                return $this->error('更新用户抽奖次数失败');
            }
            // 中将处理
            if ($award['award_level'] != 0) {
                // 检查库存
                $active_prize = ActivePrize::query()->sharedLock()->where([
                    'active_id' => $active->active_id,
                    'prize_id' => $award['prize_id']
                ])->first();
                // 库存不足，响应未中奖
                if (empty($active_prize) || $active_prize->active_surplus_number - 1 < 0) {
                    DB::commit();
                    return $this->response([
                        'prize_id' => 0,
                        'prize_name' => '谢谢惠顾',
                        'award_level' => 0
                    ]);
                }
                // 今日奖品抽奖数量加一
                Redis::incr(date('Ymd') . '_php_prize_num_' . $award['prize_id']);
                // 中奖更新活动奖品表库存
                $active_prize->decrement('active_surplus_number');
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
                $prize->decrement('surplus_number');
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
                $award_record->wx_nickname = $wx_user['wx_nickname'];
                if (!$award_record->save()) {
                    DB::rollBack();
                    return $this->error('写入中奖信息失败');
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

    // 增加中奖次数
    function incDrawNumber(Request $request)
    {
        try {
            $wx_user = JWTAuth::parseToken()->authenticate();
            $keyword = 'php_draw_num_' . $wx_user->wx_user_id . '_' . date('Ymd');
            $exists = Redis::exists($keyword);
            if ($exists) {
                return $this->error('今日已经分享过');
            } else {
                Redis::setex($keyword, 90000, 0);
                DB::beginTransaction();
                $wx_user->increment('draw_number');
                $wx_user->updated_at = time();
                if (!$wx_user->save()) {
                    DB::rollBack();
                    return $this->error('增加抽奖次数失败');
                }
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error();
        }
    }

    /**
     * 省份列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function province()
    {
        $province = BusinessHall::query()->select([DB::raw('any_value(business_hall_id) as business_hall_id, any_value(province) as province')])
            ->groupBy('province')->get();
        return $this->response($province);
    }

    /**
     * 区列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function area(Request $request)
    {
        $province = $request->input('province');
        $area = BusinessHall::query()->select([DB::raw('any_value(business_hall_id) as business_hall_id, any_value(area) as area')])
            ->where('province', $province)
            ->groupBy('area')->get();
        return $this->response($area);
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
