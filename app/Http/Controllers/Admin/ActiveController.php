<?php

namespace App\Http\Controllers\Admin;

use App\Model\Active;
use App\Model\ActivePrize;
use App\Model\Prize;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * 新建活动
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addActive(Request $request)
    {
        try {
            DB::beginTransaction();

            $params = $request->all();
            if (empty($params)) {
                return $this->error('没有添加抽奖奖品');
            }

            $active = new Active;
            $active->active_name = $request->input('active_name', '');
            $active->must_award = $request->input('must_award', 3);
            $active->enable = $request->input('enable', 1);
            $active->start_time = $request->start_time ? strtotime($request->start_time) : 0;
            $active->end_time = $request->end_time ? strtotime($request->end_time) : 0;
            if (!$active->save()) {
                DB::rollBack();
                return $this->error('创建失败');
            }

            $chance = 0;
            $data = array();
            foreach ($params['prize'] as $key => $value) {
                if (!isset($value['prize_id'])) {
                    return $this->error('奖品id必须');
                }
                if (!isset($value['prize_number'])) {
                    return $this->error('奖品数量必须');
                }
                if (!isset($value['every_day_number'])) {
                    return $this->error('没天奖品数量必须');
                }
                if (!isset($value['chance'])) {
                    return $this->error('抽奖概率必须');
                }
                $prize_exist = Prize::query()->find($value['prize_id']);
                if (!$prize_exist) {
                    return $this->error('奖品不存在');
                }
                if ($prize_exist['surplus_number'] < $value['prize_number']) {
                    return $this->error('奖品余量不足');
                }
                if ($value['every_day_number'] > $value['prize_number']) {
                    return $this->error('每天奖品数量不能大于奖品数量');
                }
                $data[] = [
                    'active_id' => $active->active_id,
                    'prize_id' => $value['prize_id'],
                    'prize_name' => $prize_exist['prize_name'],
                    'active_prize_number' => $value['prize_number'],
                    'active_surplus_number' => $value['prize_number'],
                    'every_day_number' => $value['every_day_number'],
                    'chance' => $value['chance']
                ];
                $chance += $value['chance'];
            }
            if ($chance > 100) {
                DB::rollBack();
                return $this->error('抽奖概率在0~100之间');
            }
            $result = DB::table('active_prize')->insert($data);
            if (!$result) {
                DB::rollBack();
                return $this->error('创建失败');
            }
            DB::commit();
            return $this->response(['active_id' => $active->active_id]);
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
            $id = $request->input('id');
            if (empty($id)) {
                return $this->error('id必须');
            }
            $id = is_array($id) ? $id : array($id);
            DB::beginTransaction();
            if (!Active::destroy($id)) {
                DB::rollBack();
                return $this->error('删除失败');
            }
            $result = ActivePrize::query()->whereIn('active_id', $id)->delete();
            if (!$result) {
                DB::rollBack();
                return $this->error('删除失败');
            }
            DB::commit();
            return $this->success();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error($exception->getMessage());
        }
    }
}
