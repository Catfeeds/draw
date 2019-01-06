<?php

namespace App\Http\Controllers\Admin;

use App\Model\Sign;
use App\Model\WxUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use JWTFactory;
use JWTAuth;

class WxUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * 微信用户列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function wxUserList(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $nickname = $request->input('nickname', '');
        if (empty($nickname)) {
            $wx_user = WxUser::query()
                ->paginate($per_page, ['*'], 'page', $page);
        } else {
            $wx_user = WxUser::query()
                ->where('wx_nickname', $nickname)
                ->paginate($per_page, ['*'], 'page', $page);
        }
        return $this->response($wx_user);
    }
}
