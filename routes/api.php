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
Route::get('/status', function () {
    return \DB::table('tbm_crawledPosts')->select([
        'type',
        'fid',
        'tid',
        'pid',
        'startTime',
        'duration',
        'webRequestTimes',
        'parsedPostTimes',
        'parsedUserTimes'
    ])->orderBy('id', 'DESC')->limit(10000)->get()->toJson();
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
