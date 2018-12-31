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
        $code = $request->get('code');
        if (empty($code)) {
            return $this->error('code必须');
        }
        $award = Award::query()
            ->select(['award_id','wx_user_id','prize_id','business_hall_id','prize_name','award_level','exchange_code','expire_time','is_exchange'])
            ->where('exchange_code', $code)
            ->first();
        if ($award->is_exchange != 0) {
            return $this->error('兑换码已使用');
        }
        if ($award->expire_time < time()) {
            return $this->error('兑换码过期');
        }
        $wx_user = WxUser::query()->select(['wx_nickname'])->find($award->wx_user_id);
        $award->wx_nickname = $wx_user->wx_nickname;
        return $this->response($award);
    }

    /**
     * 确认核销兑换码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        try {
            $award_id = $request->input('award_id');
            if (empty($award_id)) {
                return $this->error('award_id必须');
            }
            $time = time();
            DB::beginTransaction();
            $award = Award::find($award_id);
            $award->is_exchange = 0;
            $award->exchange_time = $time;
            $award->updated_at = $time;
            if (!$award->save()) {
                DB::rollBack();
                return $this->error('核销失败');
            }
            $business_prize = BusinessHallPrize::query()
                ->where(['prize_id' => $award->prize_id, 'business_hall_id' => $award->business_hall_id])
                ->first();
            if ($business_prize->lock_prize_number - 1 < 0) {
                return $this->error('锁定奖品库存不足');
            }
            $business_prize->lock_prize_number--;
            if (!$business_prize->save()) {
                DB::rollBack();
                return $this->error('核销失败');
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error();
        }
    }
}
