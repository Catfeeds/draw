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
                'award_id' => 'required|integer',
                'business_id' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $wx_user = JWTAuth::parseToken()->authenticate();
            // 检查兑奖信息
            $award = Award::find($request->award_id);
            if ($award->wx_user_id != $wx_user->wx_user_id) {
                return $this->error('award_id错误');
            }
            if ($award->is_exchange != 0) {
                return $this->error('已经兑奖');
            }
            if ($award->exchange_code && $award->expire_time > time()) {
                return $this->error('已经生成兑换码');
            }
            // 检查营业厅
            $business_prize = BusinessHallPrize::query()
                ->where('prize_id', $award->prize_id)
                ->where('business_hall_id', $request->business_id)
                ->first();
            if (empty($business_prize) || $business_prize->business_surplus_number - 1 < 0) {
                return $this->error('营业厅库存不足');
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

            // 保存用户兑换码
            $award->exchange_code = $code;
            $award->expire_time = $expire_time;
            $award->business_hall_id = $request->business_id;
            $award->business_hall_name = BusinessHall::query()->find($request->business_id)->business_hall_name;
            if (!$award->save()) {
                DB::rollBack();
                return $this->error('保存兑换码失败');
            }
            // 锁定奖品
            $business_prize->business_surplus_number--;
            $business_prize->lock_prize_number++;
            if (!$business_prize->save()) {
                return $this->error('保存兑换码失败');
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
            $award_id = $request->input('award_id');
            if (empty($award_id)) {
                return $this->error('award_id必须');
            }
            $wx_user = JWTAuth::parseToken()->authenticate();
            $award = Award::query()->where('wx_user_id', $wx_user->wx_user_id)->find($award_id);
            if (empty($award)) {
                return $this->error('award_id不存在');
            }
            if ($award->is_exchange) {
                return $this->error('已经兑奖，不能取消');
            }
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
            $award->is_exchange = 0;
            $award->expire_time = 0;
            $award->exchange_code = '';
            $award->business_hall_id = 0;
            $award->business_hall_name = '';
            if (!$award->save()) {
                DB::rollBack();
                return $this->error('取消兑换码失败');
            }

            $business_prize = BusinessHallPrize::query()
                ->where(['prize_id' => $award->prize_id, 'business_hall_id' => $award->business_hall_id])
                ->first();
            if (!empty($business_prize)) {
                if ($business_prize->lock_prize_number - 1 < 0) {
                    return $this->error('锁定库存不足');
                }
                $business_prize->decrement('lock_prize_number');
                $business_prize->increment('business_surplus_number');
                if (!$business_prize->save()) {
                    return $this->error('取消兑换码失败');
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
     * 展示兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    function getExchangeCode(Request $request)
    {
        $award_id = $request->get('award_id', 0);
        if (empty($award_id)) {
            return $this->error('award_id必须');
        }
        $wx_user = JWTAuth::parseToken()->authenticate();
        $award = Award::query()
            ->select(['prize_name','award_level','business_hall_name','exchange_code','expire_time'])
            ->where('wx_user_id', $wx_user->wx_user_id)
            ->find($award_id);
        if ($award->expire_time < time()) {
            return $this->error('兑换码已过期');
        }
        $award->wx_nickname = $wx_user->nickname;
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
