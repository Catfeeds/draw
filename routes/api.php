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
    Route::get('login', 'DrawController@login');
    Route::get('index', 'DrawController@index');
    Route::post('draw', 'DrawController@draw');
    Route::get('award', 'AwardController@awardRecord');
    Route::get('exchange', 'AwardController@exchange');
    Route::get('business', 'AwardController@businessHall');
});