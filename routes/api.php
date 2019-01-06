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

Route::group(['namespace' => 'Web',], function () {
    // 登陆
    Route::get('login', 'DrawController@login');
    // 抽奖首页
    Route::get('index', 'DrawController@index');
    // 抽奖
    Route::post('draw', 'DrawController@draw');
    // 中奖列表
    Route::get('award', 'AwardController@awardRecord');
    // 生成兑换码
    Route::post('exchange', 'AwardController@exchange');
    Route::delete('exchange', 'AwardController@deleteCode');
    // 展示兑换码
    Route::get('exchange', 'AwardController@getExchangeCode');
    // 营业厅列表
    Route::get('business', 'AwardController@businessHall');
    // 增加抽奖次数
    Route::post('draw_number', 'DrawController@incDrawNumber');
    // 省份列表
    Route::get('province', 'DrawController@province');
    // 区列表
    Route::get('area', 'DrawController@area');
    // 营业厅奖品余量
    Route::get('surplus_number', 'AwardController@businessPrize');
    // 保存用户信息
    Route::post('bind/user', 'DrawController@saveWxUserInfo');
});