<?php

namespace App\Http\Controllers\Web;

use App\Model\Award;

use App\Model\BusinessHall;
use App\Model\BusinessHallPrize;
use App\Model\ExchangeCode;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Validator;
use JWTFactory;
use JWTAuth;

class AwardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * 中奖列表
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function awardRecord(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $exchange = $request->input('exchange', 0);

        $wx_user = JWTAuth::parseToken()->authenticate();
        $award = Award::query()->where('wx_user_id', $wx_user->wx_user_id);
        if ($exchange) {
            $award->where('exchange_time', '>', 0);
        }
        $list = $award->orderBy('created_at', 'desc')
            ->paginate($per_page, ['*'], 'page', $page);
        return $this->response($list);
    }

    /**
     * 获取兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function exchange(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'award_id' => 'required|array',
                'business_id' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $wx_user = JWTAuth::parseToken()->authenticate();
            // 检查兑奖信息
            $award = Award::find($request->award_id);
            foreach ($award as $key => $value) {
                if ($value['wx_user_id'] != $wx_user->wx_user_id) {
                    return $this->error('award_id错误');
                }
                if ($value['is_exchange'] != 0) {
                    return $this->error($value['prize_name'] . ' 已经兑换');
                }
                // 检查营业厅
                $business_prize = BusinessHallPrize::query()
                    ->where('prize_id', $value['prize_id'])
                    ->where('business_hall_id', $request->business_id)
                    ->first();
                if (empty($value['exchange_code'])) {
                    if (empty($business_prize) || $business_prize->business_surplus_number - 1 < 0) {
                        return $this->error('营业厅库存不足');
                    }
                }
            }

            // 生成兑换码，兑换码重复重试4次
            $code = '';
            for ($i = 0; $i < 5; $i++) {
                $random_str = str_random(10);
                $exists = ExchangeCode::query()->where('exchange_code', $random_str)->count();
                if (!$exists) {
                    $code = $random_str;
                    break;
                }
            }
            if (empty($code)) {
                return $this->error('生成兑换码失败');
            }
            DB::beginTransaction();
            $expire_time = strtotime('+3 day');
            // 清空兑换码
            $res = Award::query()
                ->where('wx_user_id', $wx_user->wx_user_id)
                ->where('is_exchange', 0)
                ->where('exchange_code', '!=', '')
                ->get();
            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $surplus_number = BusinessHallPrize::query()
                        ->where('prize_id', $value['prize_id'])
                        ->where('business_hall_id', $value['business_hall_id'])
                        ->first();
                    if (!empty($surplus_number)) {
                        $surplus_number->decrement('lock_prize_number');
                        $surplus_number->increment('business_surplus_number');
                    }
                }
            }
            $res = Award::query()
                ->where('wx_user_id', $wx_user->wx_user_id)
                ->where('is_exchange', 0)
                ->update([
                    'exchange_code' => '',
                    'expire_time' => 0,
                    'business_hall_id' => 0,
                    'business_hall_name' => ''
                ]);
            if (!$res) {
                $this->error('清空兑换码失败');
            }
            // 保存用户兑换码
            foreach ($award as $key => $value) {
                $model = Award::find($value['award_id']);
                $model->exchange_code = $code;
                $model->expire_time = $expire_time;
                $model->business_hall_id = $request->business_id;
                $model->business_hall_name = BusinessHall::query()->find($request->business_id)->business_hall_name;
                if (!$model->save()) {
                    DB::rollBack();
                    return $this->error('保存兑换码失败');
                }
                // 锁定奖品
                $business_prize = BusinessHallPrize::query()
                    ->where('prize_id', $value['prize_id'])
                    ->where('business_hall_id', $request->business_id)
                    ->first();
                $business_prize->decrement('business_surplus_number');
                $business_prize->increment('lock_prize_number');
                if (!$business_prize->save()) {
                    return $this->error('保存兑换码失败');
                }
            }

            DB::commit();
            return $this->response(['code' => $code]);
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error();
        }
    }

    /**
     * 删除兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteCode(Request $request)
    {
        try {
            $wx_user = JWTAuth::parseToken()->authenticate();
            $key = 'php_delete_code_' . $wx_user->wx_user_id . '_' . date('Ymd');
            $exchange_num = Redis::get($key);
            if (empty($exchange_num)) {
                Redis::setex($key, 9000, 0);
            } else {
                if ($exchange_num >= $wx_user->cancel_exchange_number) {
                    return $this->error('今日取消兑换码次数用尽');
                }
                Redis::incr($key);
            }
            DB::beginTransaction();
            // 清空兑换码
            $res = Award::query()
                ->where('wx_user_id', $wx_user->wx_user_id)
                ->where('is_exchange', 0)
                ->where('exchange_code', '!=', '')
                ->get();
            if (!empty($res)) {
                foreach ($res as $key => $value) {
                    $surplus_number = BusinessHallPrize::query()
                        ->where('prize_id', $value['prize_id'])
                        ->where('business_hall_id', $value['business_hall_id'])
                        ->first();
                    if (!empty($surplus_number)) {
                        $surplus_number->decrement('lock_prize_number');
                        $surplus_number->increment('business_surplus_number');
                    }
                }
            }
            $res = Award::query()
                ->where('wx_user_id', $wx_user->wx_user_id)
                ->where('is_exchange', 0)
                ->update([
                    'exchange_code' => '',
                    'expire_time' => 0,
                    'business_hall_id' => 0,
                    'business_hall_name' => ''
                ]);
            if (!$res) {
                $this->error('清空兑换码失败');
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
     * 展示兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function getExchangeCode(Request $request)
    {
        $wx_user = JWTAuth::parseToken()->authenticate();
        $award = Award::query()
            ->select([DB::raw('COUNT(*) as prize_number, any_value(award_id) as award_id,
                any_value(prize_name) as prize_name, any_value(award_level) as award_level,
                any_value(business_hall_name) as business_hall_name, any_value(exchange_code) as exchange_code,
                any_value(expire_time) as expire_time')])
            ->where('wx_user_id', $wx_user->wx_user_id)
            ->where('is_exchange', 0)
            ->groupBy('prize_id')
            ->get();
        if (empty($award)) {
            return $this->error('没有中奖信息');
        }
        if (empty($award[0]['exchange_code'])) {
            return $this->error('没有兑换码');
        }
        if ($award[0]['expire_time'] < time()) {
            return $this->error('兑换码已过期');
        }
        return $this->response($award);
    }

    /**
     * 营业厅列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function businessHall(Request $request)
    {
        $area = $request->input('area', '');
        $province = $request->input('province', '');
        $business_hall_name = $request->input('business_name', '');
        $business_hall = BusinessHall::query();
        if (!empty($area)) {
            $business_hall->where('area', 'like', "%$area%");
        }
        if (!empty($province)) {
            $business_hall->where('province', 'like', "%$province%");
        }
        if (!empty($business_hall_name)) {
            $business_hall->where('business_hall_name', 'like', "%$business_hall_name%");
        }
        $list = $business_hall->paginate();
        return $this->response($list);
    }
}
