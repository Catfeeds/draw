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
     * 新建活动
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addActive(Request $request)
    {
        try {
            $params = $request->all();
            if (empty($params)) {
                return $this->error('没有添加抽奖奖品');
            }
            DB::beginTransaction();
            $active = new Active;
            $active->active_name = $request->input('active_name', '');
            $active->must_award = $request->input('must_award', 3);
            $active->enable = $request->input('enable', 1);
            $active->sign_prize_number = $request->input('sign_prize_number', 0);
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
                    return $this->error('每天奖品数量必须');
                }
                if (isset($value['chance']) && $value['chance'] < 0) {
                    return $this->error('抽奖概率在0~100之间');
                }
                $prize_exist = Prize::query()->find($value['prize_id']);
                if (!$prize_exist) {
                    return $this->error('奖品不存在');
                }
                if ($prize_exist['surplus_number'] < $value['prize_number']) {
                    return $this->error($prize_exist->prize_name . '余量不足');
                }
                if ($value['every_day_number'] > $value['prize_number']) {
                    return $this->error($prize_exist->prize_name . '每天奖品数量不能大于奖品数量');
                }
                $data[] = [
                    'active_id' => $active->active_id,
                    'prize_id' => $value['prize_id'],
                    'prize_name' => $prize_exist['prize_name'],
                    'active_prize_number' => $value['prize_number'],
                    'active_surplus_number' => $value['prize_number'],
                    'every_day_number' => $value['every_day_number'],
                    'chance' => $value['chance'] ?? 0
                ];
                $chance += $value['chance'] ?? 0;
            }
            $set_chance_num = collect($params['prize'])->filter(function ($value, $key) {
                return isset($value['chance']) && !empty($value['chance']);
            });
            if ($set_chance_num->count() != count($params['prize']) && $set_chance_num->count() != 0) {
                DB::rollBack();
                return $this->error('请设置全部奖品概率或使用默认奖品概率');
            }
            if ($set_chance_num->count() == count($params['prize']) && ($chance > 100 || $chance < 0)) {
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
            return $this->error($exception->getMessage());
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
                'active_id' => 'required|integer',
            ]);
            if ($valid->fails()) {
                return $this->error($valid->errors()->first());
            }
            $active_id = $request->input('active_id');
            DB::beginTransaction();
            $active = Active::query()->find($active_id);
            if (empty($active)) {
                DB::rollBack();
                return $this->error('活动不存在');
            }
            if (!empty($request->input('active_name', ''))) {
                $active->active_name = $request->input('active_name');
            }
            if ($request->has('enable')) {
                $active->enable = $request->input('enable', 0);
            }
            if (!empty($request->input('sign_prize_number', 0))) {
                $active->sign_prize_number = $request->input('sign_prize_number');
            }
            if (!$active->save()) {
                 DB::rollBack();
                 return $this->error('修改活动失败');
            }
            if ($request->has('prize')) {
                $prize = collect($request->input('prize'));
                if ($prize->isEmpty()) {
                    return $this->error('奖品不能为空');
                }
                $chance = 0;
                $data = [];
                $prize->each(function ($item) use ($active_id, &$chance, &$data, &$has_must_award_prize) {
                    if (!isset($item['prize_id'])) {
                        return $this->error('奖品id必须');
                    }
                    if (!isset($item['prize_number'])) {
                        return $this->error('奖品数量必须');
                    }
                    if (!isset($item['every_day_number'])) {
                        return $this->error('没天奖品数量必须');
                    }
                    if (isset($item['chance']) && $item['chance'] < 0) {
                        return $this->error('抽奖概率在0~100之间');
                    }
                    $prize_exist = Prize::query()->find($item['prize_id']);
                    if (!$prize_exist) {
                        return $this->error('奖品不存在');
                    }
                    if ($prize_exist['surplus_number'] < $item['prize_number']) {
                        return $this->error($prize_exist->prize_name . '余量不足');
                    }
                    if ($item['every_day_number'] > $item['prize_number']) {
                        return $this->error($prize_exist->prize_name . '每天奖品数量不能大于奖品数量');
                    }
                    $data[] = [
                        'active_id' => $active_id,
                        'prize_id' => $item['prize_id'],
                        'prize_name' => $prize_exist['prize_name'],
                        'active_prize_number' => $item['prize_number'],
                        'active_surplus_number' => $item['prize_number'],
                        'every_day_number' => $item['every_day_number'],
                        'chance' => $item['chance'] ?? 0
                    ];
                    $chance += $item['chance'] ?? 0;
                });
                $active_prize = ActivePrize::query()->find($request->award_id);
                if (!empty($active_prize)) {
                    if (!$active_prize->delete()) {
                        return $this->error('删除原奖品失败');
                    }
                }
                $set_chance_num = collect($prize['prize'])->filter(function ($value, $key) {
                    return isset($value['chance']) && !empty($value['chance']);
                });
                if ($set_chance_num->count() != count($prize['prize']) && $set_chance_num->count() != 0) {
                    DB::rollBack();
                    return $this->error('请设置全部奖品概率或使用默认奖品概率');
                }
                if ($set_chance_num->count() == count($prize['prize']) && ($chance > 100 || $chance < 0)) {
                    DB::rollBack();
                    return $this->error('抽奖概率在0~100之间');
                }
                $result = DB::table('active_prize')->insert($data);
                if (!$result) {
                    DB::rollBack();
                    return $this->error('修改失败');
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
}
