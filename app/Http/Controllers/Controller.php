<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function success($message = 'ok', $code = 0)
    {
        return response()->json([
           'code' => $code,
           'msg' => $message
        ]);
    }

    public function error($message = 'failed', $code = 1) {
        return response()->json([
            'code' => $code,
            'msg' => $message
        ]);
    }

    public function response($data = null, $message = 'ok', $code = 0) {
        return response()->json([
           'code' => $code,
           'msg' => $message,
           'data' => $data
        ]);
    }
}
