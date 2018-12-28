<?php

namespace App\Http\Controllers\Web;

use App\Model\Award;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AwardController extends Controller
{
    public function awardRecord(Request $request)
    {
        $exchange = $request->input('exchange', 0);
        return Award::query()->where('');
    }
}
