<?php

namespace App\Http\Controllers\Admin;

use App\Model\Prize;
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
        $valid = Validator::make($request->all(), [
            'id' => 'required'
        ]);
        if ($valid->fails()) {
            return $this->error($valid->errors()->first());
        }
        $count = Prize::destroy($request->input('id'));
        if ($count) {
            return $this->success();
        }
        return $this->error();
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
        if ($request->total_number) {
            $prize->total_number = $request->total_number;
        }
        if ($request->surplus_number) {
            $prize->surplus_number = $request->surplus_number;
        }
        if ($request->description) {
            $prize->description = $request->description;
        }
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
        $list = Prize::query()->where('prize_name', $prize_name)
            ->orderBy('created_at', 'desc')
            ->paginate($per_page, ['*'], 'page', $page);
        return $this->success($list);
    }
}
