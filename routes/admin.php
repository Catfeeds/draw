<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Admin Routes
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

Route::group(['namespace' => 'Admin',], function () {
    Route::get('login', 'AdminUserController@login')->name('login');
    Route::post('register', 'AdminUserController@register');
    Route::post('logout', 'AdminUserController@logout');
    Route::post('refresh', 'AdminUserController@refresh');
    // 上传营业厅
    Route::post('import', 'BusinessHallController@import');
    // 添加奖品
    Route::post('prize', 'PrizeController@addPrize');
    // 删除奖品
    Route::delete('prize', 'PrizeController@deletePrize');
    // 修改奖品
    Route::post('update/prize', 'PrizeController@updatePrize');
    // 奖品列表
    Route::get('prize', 'PrizeController@prizeList');
    // 添加活动
    Route::post('active', 'ActiveController@addActive');
    // 删除活动
    Route::delete('active', 'ActiveController@deleteActive');
    // 核销兑换码
    Route::get('exchange', 'BusinessHallController@checkExchangeCode');
});