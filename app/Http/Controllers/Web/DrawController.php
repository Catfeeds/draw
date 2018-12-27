<?php

namespace App\Http\Controllers\Web;

use App\Model\Active;
use App\Model\Sign;
use App\Model\WxUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class DrawController extends Controller
{
    /**
     * 活动首页
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $wx_username = $request->input('wx_username', '');
        if (empty($wx_username)) {
            return $this->error('wx_username必须');
        }
        $wx_user = WxUser::query()->where('wx_username', $wx_username)->first();
        if (!$wx_user) {
            // TODO 获取用户信息并保存
        }
        // 每日第一次登陆签到
        $key = 'first_login_' . $wx_user->wx_user_id;
        $first_login = Redis::get($key);
        if (empty($first_login) || $first_login < time()) {
            DB::beginTransaction();
            $sign = new Sign;
            $sign->sign_time = time();
            $sign->ip = $request->getClientIp();
            $sign->wx_user_id = $wx_user->wx_user_id;
            $res = $sign->save();
            if (!$res) {
                Log::error($res);
                return $this->error('签到失败');
            }
            $res = Redis::set($key, strtotime(date('Y-m-d')) + 86400);
            if (!$res) {
                DB::rollBack();
                Log::error($res);
                return $this->error('签到失败');
            }
            DB::commit();
        }
        // 获取活动信息
        $active = Active::query()->where('enable', 1);
        return $this->success();
    }
}
