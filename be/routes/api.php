<?php

use App\Eloquent\Model\Post\PostFactory;
use App\Helper;
use App\Http\Controllers\PostsQuery;
use App\Http\Controllers\UsersQuery;
use App\Http\Middleware\ReCAPTCHACheck;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/forums', static fn () => \App\Eloquent\Model\Forum::all()->toJson());

Route::middleware(ReCAPTCHACheck::class)->group(static function () {
    Route::get('/posts', [PostsQuery::class, 'query']);
    Route::get('/users', [UsersQuery::class, 'query']);
    Route::get('/status', static function (Request $request): string {
        $groupByTimeGranularity = [
            'minute' => 'FROM_UNIXTIME(startTime, "%Y-%m-%d %H:%i") AS startTime',
            'hour' => 'FROM_UNIXTIME(startTime, "%Y-%m-%d %H:00") AS startTime',
            'day' => 'FROM_UNIXTIME(startTime, "%Y-%m-%d") AS startTime',
        ];

        /** @var array{timeGranularity: string, startTime: string, endTime: string} $queryParams */
        $queryParams = $request->validate([
            'timeGranularity' => ['required', 'string', Rule::in(array_keys($groupByTimeGranularity))],
            'startTime' => 'required|integer|numeric',
            'endTime' => 'required|integer|numeric'
        ]);

        return DB::query()
            ->selectRaw('
                CAST(UNIX_TIMESTAMP(startTime) AS UNSIGNED) AS startTime,
                SUM(queueTiming) AS queueTiming,
                SUM(webRequestTiming) AS webRequestTiming,
                SUM(savePostsTiming) AS savePostsTiming,
                CAST(SUM(webRequestTimes) AS UNSIGNED) AS webRequestTimes,
                CAST(SUM(parsedPostTimes) AS UNSIGNED) AS parsedPostTimes,
                CAST(SUM(parsedUserTimes) AS UNSIGNED) AS parsedUserTimes
            ')
            ->fromSub(static fn (Builder $query) =>
            $query->from('tbm_crawledPosts')
                ->selectRaw($groupByTimeGranularity[$queryParams['timeGranularity']])
                ->selectRaw('
                    queueTiming,
                    webRequestTiming,
                    savePostsTiming,
                    webRequestTimes,
                    parsedPostTimes,
                    parsedUserTimes
                ')
                ->whereBetween('startTime', [$queryParams['startTime'], $queryParams['endTime']])
                ->orderBy('id', 'DESC'), 'T')
            ->groupBy('startTime')
            ->get()->toJson();
    });
    Route::get('/stats/forums/postCount', static function (Request $request): array {
        $groupByTimeGranularity = Helper::rawSqlGroupByTimeGranularity('postTime');
        $queryParams = $request->validate([
            'fid' => 'required|integer',
            'timeGranularity' => ['required', 'string', Rule::in(array_keys($groupByTimeGranularity))],
            'startTime' => 'required|integer|numeric',
            'endTime' => 'required|integer|numeric'
        ]);

        $forumsPostCount = [];
        foreach (PostFactory::getPostModelsByFid($queryParams['fid']) as $postType => $forumPostModel) {
            /** @var \Illuminate\Database\Eloquent\Model $forumPostModel */
            $forumsPostCount[$postType] = $forumPostModel
                ->selectRaw($groupByTimeGranularity[$queryParams['timeGranularity']])
                ->selectRaw('COUNT(*) AS count')
                ->whereBetween('postTime', [Helper::timestampToLocalDateTime($queryParams['startTime']), Helper::timestampToLocalDateTime($queryParams['endTime'])])
                ->groupBy('time')
                ->orderBy('time')
                ->get()->toArray();
        }
        Helper::abortAPIIf(40403, collect($forumsPostCount)->every(fn ($i) => $i === []));

        return $forumsPostCount;
    });
});
