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
}
