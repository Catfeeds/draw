<?php

namespace App\Http\Controllers\Admin;

use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\AdminUser;
use JWTFactory;
use JWTAuth;

class AdminUserController extends Controller
{
    /**
     * Create a new AuthController instance.
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    public function register(Request $request)
    {
        $valid = Validator::make($request->all(), [
            'username' => 'required|unique:admin_user|min:4|max:100',
            'password' => 'required|min:6'
        ]);
        if ($valid->fails()) {
            return $this->error($valid->errors()->first());
        }
        $user = new AdminUser;
        $user->username = $request->post('username');
        $user->password = bcrypt($request->post('password'));
        if ($user->save()) {
            return $this->response(['user_id' => $user->admin_user_id]);
        }
        return $this->error('failed');
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['username', 'password']);

        if (! $token = auth('admin')->attempt($credentials)) {
            return $this->error('Unauthorized', 401);
        }
        return $this->response([
            'token' => $token,
            'token_type' => 'bearer',
            'expire_in' => auth('admin')->factory()->getTTL() * 60
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth('api')->logout();
        return $this->success();
    }

    /**
     * Refresh a token.
     * 刷新token，如果开启黑名单，以前的token便会失效。
     * 值得注意的是用上面的getToken再获取一次Token并不算做刷新，两次获得的Token是并行的，即两个都可用。
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->response([
            'token' => auth('api')->refresh(),
            'token_type' => 'bearer',
            'expire_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }
}
