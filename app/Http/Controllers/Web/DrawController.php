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
use Validator;

class DrawController extends Controller
{
    /**
     * 活动首页
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $wx_username = $request->input('wx_username', 'test');
            if (empty($wx_username)) {
                return $this->error('wx_username必须');
            }
            $wx_user = WxUser::query()->where('wx_username', $wx_username)->first();
            if (!$wx_user) {
                // TODO 获取用户信息并保存
            }
            // 每日第一次登陆签到
            $key = 'first_login_' . $wx_user->wx_user_id;
            $first_login = Redis::get($key);
            if (empty($first_login) || $first_login < time()) {
                DB::beginTransaction();
                $sign = new Sign;
                $sign->sign_time = time();
                $sign->ip = $request->getClientIp();
                $sign->wx_user_id = $wx_user->wx_user_id;
                if (!$sign->save()) {
                    return $this->error('签到失败');
                }
                $wx_user->draw_number++;
                if (!$wx_user->save()) {
                    return $this->error('更新用户抽奖次数失败');
                }
                $res = Redis::set($key, strtotime(date('Y-m-d')) + 86400);
                if (!$res) {
                    DB::rollBack();
                    return $this->error('签到失败');
                }
                DB::commit();
            }
            // 获取活动信息
            $active = Active::query()
                ->select(['active_id', 'active_name', 'must_award', 'created_at'])
                ->where('enable', 1)
                ->orderBy('created_at', 'desc')
                ->first();
            $active->prizes;
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
                'wx_username' => 'required',
                'active_id' => 'required|int'
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $wx_user = WxUser::query()->where('wx_username', $request->wx_username)->first();
            if (empty($wx_user)) {
                return $this->error('用户未注册');
            }
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
                $keyword = date('Ymd') . '_' . $value['prize_id'];
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
            // 中奖概率不足100，使用未中奖填充
            if ($chance < 100) {
                $data[] = [
                    'prize_id' => 0,
                    'chance' => 100 - $chance,
                    'award_level' => 0
                ];
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
                Redis::incr(date('Ymd') . '_' . $award['prize_id']);
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

    /**
     * 从抽奖数组选取奖项
     * @param $proArr
     * @return array
     */
    function getRand($proArr) {
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
