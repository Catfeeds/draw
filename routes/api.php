<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['namespace' => 'Admin',], function ($router) {
    Route::get('login', 'AdminUserController@login')->name('login');
    Route::post('register', 'AdminUserController@register');
    Route::post('logout', 'AdminUserController@logout');
    Route::post('refresh', 'AdminUserController@refresh');
    // 上传营业厅
    Route::post('import', 'BussinessHallController@import');
    // 添加奖品
    Route::post('prize', 'PrizeController@addPrize');
    Route::delete('prize', 'PrizeController@deletePrize');
});