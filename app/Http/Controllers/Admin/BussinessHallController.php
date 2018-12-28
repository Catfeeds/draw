<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\BusinessHallImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Model\BusinessHall;

class BussinessHallController extends Controller
{
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
        $list = $business_hall->get();
        return $this->response($list);
    }
}
