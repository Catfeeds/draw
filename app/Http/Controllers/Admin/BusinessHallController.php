<?php

namespace App\Http\Controllers\Admin;

use App\Model\Award;
use App\Model\BusinessHallPrize;
use App\Model\WxUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\BusinessHallImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Model\BusinessHall;

class BusinessHallController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin', ['except' => ['login', 'register']]);
    }

    /**
     * 上传营业厅Execl文件
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        $file = $request->file('bank');
        Excel::import(new BusinessHallImport, $file);
        return response()->json('success');
    }

    /**
     * 核对兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkExchangeCode(Request $request)
    {
        $code = $request->input('code');
        if (empty($code)) {
            return $this->error('code必须');
        }
        $award = Award::query()
            ->select([DB::raw('COUNT(*) as prize_number, any_value(award_id) as award_id,
                any_value(prize_name) as prize_name, any_value(award_level) as award_level,
                any_value(business_hall_name) as business_hall_name, any_value(exchange_code) as exchange_code,
                any_value(expire_time) as expire_time')])
            ->where('exchange_code', $code)
            ->where('is_exchange', 0)
            ->groupBy('prize_id')
            ->get();
        if ($award->isEmpty()) {
            return $this->error('没有中奖信息');
        }
        if ($award[0]['expire_time'] < time()) {
            return $this->error('兑换码过期');
        }
        return $this->response($award->all());
    }

    /**
     * 确认核销兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        try {
            $code = $request->input('code');
            if (empty($code)) {
                return $this->error('code必须');
            }
            $award = Award::query()
                ->select([DB::raw('COUNT(*) as prize_number, any_value(award_id) as award_id,
                any_value(prize_id) as prize_id, any_value(award_level) as award_level,
                any_value(business_hall_id) as business_hall_id, any_value(exchange_code) as exchange_code,
                any_value(expire_time) as expire_time')])
                ->where('exchange_code', $code)
                ->where('is_exchange', 0)
                ->groupBy('prize_id')
                ->get();
            if ($award->isEmpty()) {
                return $this->error('没有中奖信息');
            }

            $time = time();
            $award->each(function ($item) use ($time) {
                $award_model = Award::find($item->award_id);
                $award_model->is_exchange = 1;
                $award_model->exchange_time = $time;
                $award_model->updated_at = $time;
                if (!$award_model->save()) {
                    DB::rollBack();
                    return $this->error('核销失败');
                }
                $business_model = BusinessHallPrize::query()
                    ->where('prize_id', $item->prize_id)
                    ->where('business_hall_id', $item->business_hall_id)
                    ->first();
                if ($business_model->lock_prize_number - 1 < 0) {
                    return $this->error('锁定奖品库存不足');
                }
                $business_model->decrement('lock_prize_number');
                if (!$business_model->save()) {
                    DB::rollBack();
                    return $this->error('核销失败');
                }
            });
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error();
        }
    }

    /**
     * 营业厅列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function businessList(Request $request)
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
}
