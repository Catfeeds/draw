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

Route::group(['namespace' => 'Admin', 'middleware' => ['cors']], function () {
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
    // 修改活动
    Route::post('update/active', 'ActiveController@updateActive');
    Route::get('active', 'ActiveController@getActive');
    // 核销兑换码
    Route::get('exchange', 'BusinessHallController@checkExchangeCode');
    // 确认核销
    Route::get('confirm', 'BusinessHallController@confirm');
    //  分配奖品到营业厅
    Route::post('business', 'PrizeController@businessPrize');
    // 删除分配奖品
    Route::delete('business', 'PrizeController@deleteBusinessPrize');
    // 营业厅列表
    Route::get('business', 'BusinessHallController@businessList');
    // 省份列表
    Route::get('province', 'BusinessHallController@province');
    // 区列表
    Route::get('area', 'BusinessHallController@area');
    // 微信用户列表
    Route::get('wxuser', 'WxUserController@wxUserList');
    // 中奖记录
    Route::get('award', 'WxUserController@awardRecord');

    // 上传图片
    Route::post('image', 'ImageController@addImage');
    // 修改图片
    Route::post('update/image', 'ImageController@updateImage');
    // 删除图片
    Route::delete('image', 'ImageController@deleteImage');
    // 图片列表
    Route::get('image', 'ImageController@getImage');
    // 根据id查询图片
    Route::get('image/{image_id}', 'ImageController@getOneImage');
    // 添加活动奖品
    Route::post('active/prize', 'ActiveController@addActivePrize');
    // 修改活动奖品
    Route::put('active/prize', 'ActiveController@updateActivePrize');
    // 删除活动奖品
    Route::delete('active/prize', 'ActiveController@deleteActivePrize');

    // 随机生成账号
    Route::get('generate', 'AdminUserController@generateAccount');
});