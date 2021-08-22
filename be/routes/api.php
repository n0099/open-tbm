<?php

use App\Helper;
use App\Http\Middleware\ReCAPTCHACheck;
use App\Tieba\Eloquent\PostModelFactory;
use Illuminate\Validation\Rule;
use Illuminate\Database\Query\Builder;

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
Route::get('/forumsList', fn () => App\Tieba\Eloquent\ForumModel::all()->toJson());

Route::middleware(ReCAPTCHACheck::class)->group(function (): void {
    Route::get('/postsQuery', 'PostsQuery@query');
    Route::get('/usersQuery', 'UsersQuery@query');
    Route::get('/status', function (): string {
        $groupTimeRangeRawSQL = [
            'minute' => 'FROM_UNIXTIME(startTime, "%Y-%m-%d %H:%i") AS startTime',
            'hour' => 'FROM_UNIXTIME(startTime, "%Y-%m-%d %H:00") AS startTime',
            'day' => 'FROM_UNIXTIME(startTime, "%Y-%m-%d") AS startTime',
        ];

        /** @var array{timeRange: string, startTime: string, endTime: string} $queryParams */
        $queryParams = \request()::validate([
            'timeRange' => ['required', 'string', Rule::in(array_keys($groupTimeRangeRawSQL))],
            'startTime' => 'required|date',
            'endTime' => 'required|date'
        ]);

        return \DB::query()
            ->selectRaw('
                startTime,
                SUM(queueTiming) AS queueTiming,
                SUM(webRequestTiming) AS webRequestTiming,
                SUM(savePostsTiming) AS savePostsTiming,
                SUM(webRequestTimes) AS webRequestTimes,
                SUM(parsedPostTimes) AS parsedPostTimes,
                SUM(parsedUserTimes) AS parsedUserTimes
            ')
            ->fromSub(fn (Builder $query) =>
                $query->from('tbm_crawledPosts')
                ->selectRaw($groupTimeRangeRawSQL[$queryParams['timeRange']])
                ->selectRaw('
                    queueTiming,
                    webRequestTiming,
                    savePostsTiming,
                    webRequestTimes,
                    parsedPostTimes,
                    parsedUserTimes
                ')
                ->whereRaw("startTime BETWEEN UNIX_TIMESTAMP('{$queryParams['startTime']}') AND UNIX_TIMESTAMP('{$queryParams['endTime']}')")
                ->orderBy('id', 'DESC'), 'T')
            ->groupBy('startTime')
            ->get()->toJson();
    });
    Route::get('/stats/forumPostsCount', function (): array {
        $groupTimeRangeRawSQL = Helper::getRawSqlGroupByTimeRange('postTime');
        $queryParams = \request()::validate([
            'fid' => 'required|integer',
            'timeRange' => ['required', 'string', Rule::in(array_keys($groupTimeRangeRawSQL))],
            'startTime' => 'required|date',
            'endTime' => 'required|date'
        ]);

        $forumPostsCount = [];
        foreach (PostModelFactory::getPostModelsByFid($queryParams['fid']) as $postType => $forumPostModel) {
            $forumPostsCount[$postType] = $forumPostModel
                ->selectRaw($groupTimeRangeRawSQL[$queryParams['timeRange']])
                ->selectRaw('COUNT(*) AS count')
                ->whereBetween('postTime', [$queryParams['startTime'], $queryParams['endTime']])
                ->groupBy('time')
                ->get()->toArray();
        }
        Helper::abortAPIIf(40403, Helper::isArrayValuesAllEqualTo($forumPostsCount, []));

        return $forumPostsCount;
    });
    Route::get('/bilibiliVote/top50CandidatesVotesCount', 'Topic\BilibiliVote@top50CandidatesVotesCount');
    Route::get('/bilibiliVote/top5CandidatesVotesCountByTime', 'Topic\BilibiliVote@top5CandidatesVotesCountByTime');
    Route::get('/bilibiliVote/top10CandidatesTimeline', 'Topic\BilibiliVote@top10CandidatesTimeline');
    Route::get('/bilibiliVote/allVotesCountByTime', 'Topic\BilibiliVote@allVotesCountByTime');
    Route::get('/bilibiliVote/allCandidatesVotesCount', 'Topic\BilibiliVote@allCandidatesVotesCount');
});
