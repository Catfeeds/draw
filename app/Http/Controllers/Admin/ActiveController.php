<?php

namespace App\Http\Controllers\Admin;

use App\Model\Active;
use App\Model\ActivePrize;
use App\Model\Prize;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;

class ActiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 添加活动
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addActive(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'active_name' => 'required',
                'must_award' => 'required|integer',
                'sign_prize_number' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            DB::beginTransaction();
            $active = new Active;
            $active->active_name = $request->input('active_name', '');
            $active->must_award = $request->input('must_award', 3);
            $active->enable = $request->input('enable', 1);
            $active->sign_prize_number = $request->input('sign_prize_number', 0);
            $active->start_time = $request->has('start_time') ? strtotime($request->start_time) : 0;
            $active->end_time = $request->has('end_time') ? strtotime($request->end_time) : 0;
            if (!$active->save()) {
                DB::rollBack();
                return $this->error('创建失败');
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
        }
    }

    /**
     * 修改活动
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateActive(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'active_id' => 'required|required',
                'must_award' => 'integer',
                'sign_prize_number' => 'integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $active = Active::find($request->active_id);
            if (empty($active)) {
                return $this->error('活动不存在');
            }
            if ($request->has('active_name')) {
                $active->active_name = $request->active_name;
            }
            if ($request->has('must_award')) {
                $active->must_award = $request->must_award;
            }
            if ($request->has('sign_prize_number')) {
                $active->sign_prize_number = $request->sign_prize_number;
            }
            if (!$active->save()) {
                DB::rollBack();
                return $this->error('修改失败');
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
        }
    }

    /**
     * 删除活动
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteActive(Request $request)
    {
        try {
            $id = $request->input('active_id');
            if (empty($id)) {
                return $this->error('active_id必须');
            }
            $id = is_array($id) ? $id : array($id);
            DB::beginTransaction();
            if (!Active::destroy($id)) {
                DB::rollBack();
                return $this->error('删除失败');
            }
            ActivePrize::query()->whereIn('active_id', $id)->delete();
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 活动列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActive()
    {
        $active = Active::query()
            ->with(['prizes'])
            ->orderBy('created_at', 'desc')->get();
        return $this->response($active);
    }

    /**
     * 添加活动奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addActivePrize(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'active_id' => 'required|integer',
                'prize_id' => 'required|integer',
                'active_prize_number' => 'required|integer',
                'every_day_number' => 'required|integer',
//                'chance' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $active = Active::find($request->active_id);
            if (empty($active)) {
                return $this->error('活动不存在');
            }
            $prize = Prize::query()->find($request->prize_id);
            if (empty($prize)) {
                return $this->error('奖品不存在');
            }
            // 库存检查
            $prize_number = ActivePrize::query()
                ->where('prize_id', $request->prize_id)
                ->sum('active_prize_number');
            if ($prize->total_number - $prize_number < $request->active_prize_number) {
                return $this->error('奖品库存不足' . $request->active_prize_number);
            }

//            if ($prize->surplus_number - $request->active_prize_number < 0) {
//                return $this->error('奖品还有' . $prize->surplus_number . '，余量不足' . $request->active_prize_number);
//            }
            if ($request->active_prize_number < $request->every_day_number) {
                return $this->error('活动奖品数量不能小于每日奖品数量');
            }
            $has_prize = ActivePrize::query()
                ->where('active_id', $request->active_id)
                ->where('prize_id', $request->prize_id)
                ->count();
            if (!empty($has_prize)) {
                return $this->error('奖品已经添加');
            }
            DB::beginTransaction();
//            $active_prize = ActivePrize::query()->where('active_id', $request->active_id)->get(['chance']);
//            $chance = $active_prize->sum('chance');
//            if ($chance + $request->chance > 100) {
//                return $this->error('奖品概率为0~100');
//            }
            $model = new ActivePrize;
            $model->active_id = $request->active_id;
            $model->prize_id = $request->prize_id;
            $model->prize_name = $prize->prize_name;
            $model->active_prize_number = $request->active_prize_number;
            $model->active_surplus_number = $request->active_prize_number;
            $model->every_day_number = $request->every_day_number;
//            $model->chance = $request->chance;
            if (!$model->save()) {
                DB::rollBack();
                return $this->error('添加奖品失败');
            }

//            $prize->decrement('surplus_number', $request->active_prize_number);
//            if (!$prize->save()) {
//                DB::rollBack();
//                return $this->error('更新奖品库存失败');
//            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 更新活动奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateActivePrize(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'active_id' => 'required|integer',
                'prize_id' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $active = Active::query()->find($request->active_id);
            if (empty($active)) {
                return $this->error('活动不存在');
            }
            $active_prize = ActivePrize::query()
                ->where('active_id', $request->active_id)
                ->where('prize_id', $request->prize_id)
                ->first();
            if (empty($active_prize)) {
                return $this->error('未添加活动奖品');
            }
            $prize = Prize::query()->find($request->prize_id);
            if (empty($prize)) {
                return $this->error('奖品不存在');
            }
            DB::beginTransaction();
//            if ($request->has('chance')) {
//                $chance = ActivePrize::query()
//                    ->where('active_id', $request->active_id)
//                    ->where('prize_id', '<>', $request->prize_id)
//                    ->sum('chance');
//                if ($chance + $request->chance > 100) {
//                    return $this->error('奖品概率为0~100');
//                }
//                $active_prize->chance = $request->chance;
//            }
            if ($request->has('active_prize_number')) {
                $active_prize_number = $request->active_prize_number - $active_prize->active_prize_number;
                if ($prize->surplus_number - $active_prize_number < 0) {
                    return $this->error('奖品余量不足');
                }
                $prize->decrement('surplus_number', $active_prize_number);
                $active_prize->active_prize_number += $active_prize_number;
                $active_prize->active_surplus_number += $active_prize_number;
            }
            if ($request->has('every_day_number')) {
                if ($active_prize->active_prize_number < $request->every_day_number) {
                    return $this->error('活动奖品数量不能小于每日奖品数量');
                }
                $active_prize->every_day_number = $request->every_day_number;
            }
            if (!$active_prize->save()) {
                DB::rollBack();
                return $this->error('修改奖品失败');
            }
            if (!$prize->save()) {
                DB::rollBack();
                return $this->error('修改奖品库存失败');
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 删除活动奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteActivePrize(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'active_id' => 'required|integer',
                'prize_id' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $active_prize = ActivePrize::query()
                ->where('active_id', $request->active_id)
                ->where('prize_id', $request->prize_id)
                ->first();
            if (empty($active_prize)) {
                return $this->error('未添加活动奖品');
            }
            DB::beginTransaction();
            if (!$active_prize->delete()) {
                DB::rollBack();
                return $this->error('删除奖品失败');
            }
            $prize = Prize::query()->find($request->prize_id);
            if (empty($prize)) {
                DB::rollBack();
                return $this->error('奖品不存在');
            }
            $prize->increment('surplus_number', $active_prize->active_surplus_number);
            if (!$prize->save()) {
                DB::rollBack();
                return $this->error('修改奖品库存失败');
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }
}
