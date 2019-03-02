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
    return \DB::query()
        ->select('startTime')
        ->selectRaw('SUM(duration) AS duration')
        ->selectRaw('SUM(webRequestTimes) AS webRequestTimes')
        ->selectRaw('SUM(parsedPostTimes) AS parsedPostTimes')
        ->selectRaw('SUM(parsedUserTimes) AS parsedUserTimes')
        ->fromSub(function ($query) {
            $query->from('tbm_crawledPosts')->selectRaw('FROM_UNIXTIME(startTime, "%Y-%m-%dT%H:%i") AS startTime')->addSelect([
                'duration',
                'webRequestTimes',
                'parsedPostTimes',
                'parsedUserTimes'
            ])->orderBy('id', 'DESC')->limit(10000);
        }, 'T')
        ->groupBy('startTime')
        ->get()->toJson();
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
