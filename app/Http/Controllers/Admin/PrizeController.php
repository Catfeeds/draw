<?php

namespace App\Http\Controllers\Admin;

use App\Model\ActivePrize;
use App\Model\BusinessHallPrize;
use App\Model\Prize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PrizeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 添加奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addPrize(Request $request)
    {
        $valid = Validator::make($request->all(), [
            'prize_name' => 'required',
            'total_number' => 'required|integer',
            'image' => 'image',
        ]);
        if ($valid->fails()) {
            return $this->error($valid->errors()->first());
        }
        $prize = new Prize;
        if ($request->has('image')) {
            $path = $request->file('image')->store('prize_image');
            if (!$path) {
                return $this->error('奖品图片保存失败');
            }
            $prize->prize_image = $path;
        }
        $prize->prize_name = $request->post('prize_name');
        $prize->total_number = $request->post('total_number');
        $prize->surplus_number = $request->post('total_number');
        $prize->description = $request->post('description', '');
        $prize->lock_number = 0;

        if ($prize->save()) {
            return $this->response(['id' => $prize->prize_id]);
        }
        return $this->error();
    }

    /**
     * 删除奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePrize(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'prize_id' => 'required'
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            DB::beginTransaction();
            Prize::destroy($request->input('prize_id'));
            BusinessHallPrize::query()->where('prize_id', $request->prize_id)->delete();
            ActivePrize::query()->where('prize_id', $request->prize_id)->delete();
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }

    /**
     * 修改奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePrize(Request $request)
    {
        $valid = Validator::make($request->all(), [
            'id' => 'required|integer',
            'total_number' => 'integer',
            'image' => 'image',
        ]);
        if ($valid->fails()) {
            return $this->error($valid->errors()->first());
        }
        $prize_id = $request->input('id');
        $prize = Prize::find($prize_id);
        if ($request->prize_name) {
            $prize->prize_name = $request->prize_name;
        }
        $diff = $request->total_number - $prize->total_number;
        if ($request->total_number) {
            $prize->total_number = $request->total_number;
        }
        if ($prize->surplus_number + $diff < 0) {
            return $this->error('奖品余量不足');
        }
        $prize->surplus_number = $prize->surplus_number + $diff;
        if ($request->total_number) {
            $prize->total_number = $request->total_number;
        }
        $prize->updated_at = time();
        if ($prize->save()) {
            return $this->success();
        }
        return $this->error();
    }

    /**
     * 奖品列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function prizeList(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $prize_name = $request->input('prize_name', '');
        if (empty($prize_name)) {
            $list = Prize::query()
                ->orderBy('created_at', 'desc')
                ->paginate($per_page, ['*'], 'page', $page);
        } else {
            $list = Prize::query()
                ->where('prize_name', $prize_name)
                ->orderBy('created_at', 'desc')
                ->paginate($per_page, ['*'], 'page', $page);
        }
        return $this->success($list);
    }

    /**
     * 分配奖品到营业厅
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function businessPrize(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'business_id' => 'required|integer',
                'prize_id' => 'required|integer',
                'prize_number' => 'required|integer',
                'prize_name' => 'required|string',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            // 检查奖品库存
            $prize = Prize::query()->find($request->prize_id);
            if (empty($prize) || $prize->surplus_number - $request->prize_number < 0) {
                return $this->error('奖品库存不足' . $request->prize_number);
            }
            // 减库存
            $prize->decrement('surplus_number', $request->prize_number);
            if (!$prize->save()) {
                DB::rollBack();
                return $this->error('更新奖品表库存失败');
            }
            // 营业厅没有奖品添加奖品，已有更新库存
            $business_prize = BusinessHallPrize::query()
                ->where('business_hall_id', $request->business_id)
                ->where('prize_id', $request->prize_id)
                ->first();
            DB::beginTransaction();
            if (empty($business_prize)) {
                $model = new BusinessHallPrize;
                $model->prize_id = $request->prize_id;
                $model->prize_name = $request->prize_name;
                $model->business_hall_id = $request->business_id;
                $model->business_prize_number = $request->prize_number;
                $model->business_surplus_number = $request->prize_number;
                if (!$model->save()) {
                    DB::rollBack();
                    return $this->error('分配奖品失败');
                }
            } else {
                $business_prize->increment('business_prize_number', $request->prize_number);
                $business_prize->increment('business_surplus_number', $request->prize_number);
                if (!$business_prize->save()) {
                    DB::rollBack();
                    return $this->error('分配奖品失败');
                }
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
     * 删除分配奖品
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteBusinessPrize(Request $request)
    {
        try {
            $valid = Validator::make($request->all(), [
                'business_id' => 'required|integer',
                'prize_id' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $business_prize = BusinessHallPrize::query()
                ->where('business_hall_id', $request->business_id)
                ->where('prize_id', $request->prize_id)
                ->first();
            DB::beginTransaction();
            if (!empty($business_prize)) {
                $prize = Prize::query()->find($request->prize_id);
                $prize->increment('surplus_number', $business_prize->business_surplus_number);
                if (!$prize->save()) {
                    DB::rollBack();
                    return $this->error('更新奖品表库存失败');
                }
                if (!$business_prize->delete()) {
                    DB::rollBack();
                    return $this->error('更新奖品表库存失败');
                }
            }
            DB::commit();
            return $this->success('删除成功');
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
            return $this->error($exception->getMessage());
        }
    }
}
