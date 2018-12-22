<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\BusinessHallImport;
use Maatwebsite\Excel\Facades\Excel;

class BussinessHallController extends Controller
{
    public function import(Request $request)
    {
        $file = $request->file('bank');
        Excel::import(new BusinessHallImport, $file);
        return response()->json('success');
    }
}
