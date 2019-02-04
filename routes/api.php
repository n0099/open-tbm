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

Route::get('/postsQuery', 'PostsQueryController@query');
Route::get('/forumsList', function () {
    echo App\Tieba\Eloquent\ForumModel::all()->toJson();
});
/*Route::get('/index/tid/{tid}', 'SearchController@searchByTid');
Route::get('/index/tid/{tid}/pid/{pid}', 'SearchController@searchByTid');
Route::get('/index/tid/{tid}/pid/{pid}/spid/{spid}', 'SearchController@searchByTid');
Route::get('/index/pid/{pid}', 'SearchController@searchByPid');
Route::get('/index/pid/{pid}/spid/{spid}', 'SearchController@searchByPid');
Route::get('/index/spid/{spid}', 'SearchController@searchBySpid');*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
