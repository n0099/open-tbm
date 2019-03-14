<?php

use App\Eloquent\BilibiliVoteModel;
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

Route::get('/postsQuery', 'PostsQueryController@query')->middleware(ReCAPTCHACheck::class);
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
})->middleware(ReCAPTCHACheck::class);

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
})->middleware(ReCAPTCHACheck::class);

Route::get('/bilibiliVote/top10CandidatesStats', function () {
    /*$groupTimeRangeRawSQL = [
        'minute' => 'DATE_FORMAT(postTime, "%Y-%m-%d %H:%i") AS time',
        'hour' => 'DATE_FORMAT(postTime, "%Y-%m-%d %H:00") AS time',
    ];*/
    $queryParams = \Request()->validate([
        'type' => 'required|in:count,timeline',
        'timeRange' => 'string|required_if:type,timeline',
    ]);
    $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
    $voteEndTime = '2019-03-11T12:00:00';
    $top10Candidates = BilibiliVoteModel::select('voteFor')
        ->selectRaw('COUNT(*) AS count')
        ->where('isValid', true)
        ->groupBy('voteFor')
        ->orderBy('count', 'DESC')
        ->limit(10)
        ->get()->pluck('voteFor')
        ->toArray();
    switch ($queryParams['type']) {
        case 'count':
            return BilibiliVoteModel::select(['voteFor', 'isValid'])
                ->selectRaw('COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade')
                ->whereIn('voteFor', $top10Candidates)
                ->groupBy('voteFor', 'isValid')
                ->orderBy('count', 'DESC')
                ->get()->toJson();
            break;
        case 'timeline':
            $timeRange = 5 * 60; // 5 mins
            $timeRanges = [];
            for ($time = strtotime($voteStartTime); $time <= strtotime($voteEndTime); $time += $timeRange) {
                $timeRanges[] = "SELECT \"{$time}\" AS endTime";
            }
            return \DB::query()
                ->selectRaw('SUM(timeGroups.count) AS count, timeRanges.endTime, isValid, voteFor')
                ->fromSub(BilibiliVoteModel
                    ::selectRaw("FLOOR(UNIX_TIMESTAMP(postTime)/{$timeRange})*{$timeRange} as endTime, COUNT(*) as count, isValid, voteFor")
                    ->whereIn('voteFor', $top10Candidates)
                    ->groupBy('endTime', 'isValid', 'voteFor'), 'timeGroups')
                ->join(\DB::raw('(' . implode(' UNION ', $timeRanges) . ') AS timeRanges'), 'timeGroups.endTime', '<', 'timeRanges.endTime')
                ->groupBy('endTime', 'isValid', 'voteFor')
                ->orderBy('count', 'ASC')
                ->get()->toJson();
            /*return BilibiliVoteModel
                ::selectRaw('COUNT(*) AS count, endTime, isValid, voteFor, authorExpGrade')
                ->join(\DB::raw('(' . implode(' UNION ', $a) . ') AS timeRanges'), 'tbm_bilibiliVote.postTime', '<', 'timeRanges.endTime')
                ->whereIn('voteFor', $top10Candidates)
                ->groupBy('timeRanges.endTime', 'isValid', 'voteFor', 'authorExpGrade')
                ->orderBy('endTime', 'ASC')
                ->get()->toArray();*/
            /*return BilibiliVoteModel::selectRaw($groupTimeRangeRawSQL[$queryParams['timeRange']])
                ->selectRaw('COUNT(*) AS count')
                ->addSelect(['voteFor', 'isValid'])
                ->whereIn('voteFor', $top10Candidates)
                ->groupBy('time', 'voteFor', 'isValid')
                ->orderBy('time', 'DESC')
                ->get()->toJson();*/
            /*return BilibiliVoteModel
                ::selectRaw($groupTimeRangeRawSQL[$queryParams['timeRange']])
                ->selectRaw('COUNT(*) AS count')
                ->groupBy('time')
                ->get()->toJson();*/
            break;
    }
})->middleware(ReCAPTCHACheck::class);

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
