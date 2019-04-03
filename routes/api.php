<?php

use App\Http\Middleware\ReCAPTCHACheck;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use function GuzzleHttp\json_encode;

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
Route::get('/forumsList', function () {
    echo App\Tieba\Eloquent\ForumModel::all()->toJson();
});

Route::middleware(ReCAPTCHACheck::class)->group(function () {
    Route::get('/postsQuery', 'PostsQuery@query');
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
    Route::get('/stats/forumPostsCount', function () {
        $groupTimeRangeRawSQL = [
            'minute' => 'DATE_FORMAT(postTime, "%Y-%m-%d %H:%i") AS time',
            'hour' => 'DATE_FORMAT(postTime, "%Y-%m-%d %H:00") AS time',
            'day' => 'DATE(postTime) AS time',
            'week' => 'DATE_FORMAT(postTime, "%Y第%u周") AS time',
            'month' => 'DATE_FORMAT(postTime, "%Y-%m") AS time',
            'year' => 'DATE_FORMAT(postTime, "%Y") AS time',
        ];

        $queryParams = \Request()->validate([
            'fid' => 'required|integer',
            'timeRange' => ['required', 'string', Rule::in(array_keys($groupTimeRangeRawSQL))],
            'startTime' => 'required|date',
            'endTime' => 'required|date'
        ]);

        $forumPostsCount = [];
        foreach (PostModelFactory::getPostsModelByForumID($queryParams['fid']) as $postType => $forumPostModel) {
            $forumPostsCount[$postType] = $forumPostModel
                ->selectRaw($groupTimeRangeRawSQL[$queryParams['timeRange']])
                ->selectRaw('COUNT(*) AS count')
                ->whereBetween('postTime', [$queryParams['startTime'], $queryParams['endTime']])
                ->groupBy('time')
                ->get()->toArray();
        }

        return json_encode($forumPostsCount);
    });
    Route::get('/bilibiliVote/top50CandidatesVotesCount', 'Topic\BilibiliVote@top50CandidatesVotesCount');
    Route::get('/bilibiliVote/top5CandidatesVotesCountByTime', 'Topic\BilibiliVote@top5CandidatesVotesCountByTime');
    Route::get('/bilibiliVote/top10CandidatesTimeline', 'Topic\BilibiliVote@top10CandidatesTimeline');
    Route::get('/bilibiliVote/allVotesCountByTime', 'Topic\BilibiliVote@allVotesCountByTime');
    Route::get('/bilibiliVote/allCandidatesVotesCount', 'Topic\BilibiliVote@allCandidatesVotesCount');
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
