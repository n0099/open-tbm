<?php

namespace App\Http\Controllers\Topic;

use App\Eloquent\BilibiliVoteModel;
use App\Helper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Spatie\Regex\Regex;

/**
 * Class BilibiliVote
 *
 * Controller for /api/bilibiliVote
 *
 * @package App\Http\Controllers\Topic
 */
class BilibiliVote
{
    /**
     * Generate a query builder, which returns top $candidatesCount candidates based on valid votes
     *
     * @sql select `voteFor` from (select `voteFor`, COUNT(*) AS count from `tbm_bilibiliVote` where `isValid` = 1 group by `voteFor` order by `count` desc limit $candidatesCount) as `T`
     * @param int $candidatesCount
     * @return \Illuminate\Database\Query\Builder
     */
    private static function getTopVotesCandidatesSQL(int $candidatesCount): \Illuminate\Database\Query\Builder
    {
        return \DB::query()->select('voteFor')->fromSub(
            BilibiliVoteModel::select('voteFor')
                ->selectRaw('COUNT(*) AS count')
                ->where('isValid', true)
                ->groupBy('voteFor')
                ->orderBy('count', 'DESC')
                ->limit($candidatesCount)
        , 'T');
    }

    /**
     * Return every candidates' valid and invalid votes count
     *
     * @sql select `isValid`, `voteFor`, COUNT(*) AS count from `tbm_bilibiliVote` group by `isValid`, `voteFor` order by `voteFor` asc
     * @param Request $request
     * @return string
     */
    public static function allCandidatesVotesCount(Request $request): string
    {
        return static::sanitizeVoteForField(BilibiliVoteModel::select(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count')
            ->groupBy('isValid', 'voteFor')
            ->orderBy('voteFor', 'ASC')
            ->get())
            ->values()
            ->toJson();
    }

    /**
     * Return all valid and invalid votes count, group by given time range
     *
     * @sql select DATE_FORMAT(postTime, "%Y-%m-%d %H:%i|00") AS time, isValid, COUNT(*) AS count from `tbm_bilibiliVote` group by `time`, `isValid` order by `time` asc
     * @param Request $request
     * @return string
     */
    public static function allVotesCountByTime(Request $request): string
    {
        $groupByTimeGranular = Helper::getRawSqlGroupByTimeGranular('postTime', ['minute', 'hour']);
        $request->validate([
            'timeGranular' => ['required', Rule::in(array_keys($groupByTimeGranular))]
        ]);
        return BilibiliVoteModel::selectRaw($groupByTimeGranular[$request->query()['timeGranular']])
            ->selectRaw('isValid, COUNT(*) AS count')
            ->groupBy('time', 'isValid')
            ->orderBy('time', 'ASC')
            ->get()->toJson();
    }

    /**
     * Return votes count and average voters' exp grade of top 50 candidates
     *
     * @sql select `isValid`, `voteFor`, COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade from `tbm_bilibiliVote` where `voteFor` in getTopVotesCandidatesSQL(50) group by `isValid`, `voteFor` order by `voteFor` asc
     * @param Request $request
     * @return string
     */
    public static function top50CandidatesVotesCount(Request $request): string
    {
        return static::sanitizeVoteForField(BilibiliVoteModel::select(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade')
            ->whereIn('voteFor', static::getTopVotesCandidatesSQL(50))
            ->groupBy('isValid', 'voteFor')
            ->orderBy('voteFor', 'ASC')
            ->get())
            ->map(function ($i) {
                $i['voterAvgGrade'] = (float)$i['voterAvgGrade'];
                return $i;
            })
            ->toJson();
    }

    /**
     * Return votes count of top 5 candidates, group by given time range
     *
     * @sql select DATE_FORMAT(postTime, "%Y-%m-%d %H:%i|00") AS time, `isValid`, `voteFor`, COUNT(*) AS count from `tbm_bilibiliVote` where `voteFor` in getTopVotesCandidatesSQL(5) group by `time`, `isValid`, `voteFor` order by `time` asc
     * @param Request $request
     * @return string
     */
    public static function top5CandidatesVotesCountByTime(Request $request): string
    {
        $groupByTimeGranular = Helper::getRawSqlGroupByTimeGranular('postTime', ['minute', 'hour']);
        $request->validate([
            'timeGranular' => ['required', Rule::in(array_keys($groupByTimeGranular))]
        ]);
        return static::sanitizeVoteForField(BilibiliVoteModel::selectRaw($groupByTimeGranular[$request->query()['timeGranular']])
            ->addSelect(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count')
            ->whereIn('voteFor', static::getTopVotesCandidatesSQL(5))
            ->groupBy('time', 'isValid', 'voteFor')
            ->orderBy('time', 'ASC')
            ->get())
            ->toJson();
    }

    /**
     * Return every 5 mins sum of cumulative votes count, group by candidates and validate
     *
     * @sql select CAST(timeRangesRawSQL.endTime AS UNSIGNED) AS endTime, isValid, voteFor, CAST(SUM(timeGroups.count) AS UNSIGNED) AS count
     * from (
     *   select FLOOR(UNIX_TIMESTAMP(postTime)/300)*300 as endTime, isValid, voteFor, COUNT(*) as count from `tbm_bilibiliVote`
     *   where `voteFor` in getTopVotesCandidatesSQL(10)
     *   group by `endTime`, `isValid`, `voteFor`
     * ) as `timeGroups`
     * inner join (SELECT "1552192500" AS endTime UNION ...every +300... UNION SELECT "1552276800" AS endTime) AS timeRangesRawSQL
     * on `timeGroups`.`endTime` < `timeRangesRawSQL`.`endTime`
     * group by `endTime`, `isValid`, `voteFor`
     * order by `endTime` asc
     * @param Request $request
     * @return string
     */
    public static function top10CandidatesTimeline(Request $request): string
    {
        $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
        $voteEndTime = '2019-03-11T12:00:00';
        $timeRange = 5 * 60; // 5 mins
        $timeRangesRawSQL = [];
        for ($time = strtotime($voteStartTime); $time <= strtotime($voteEndTime); $time += $timeRange) {
            $timeRangesRawSQL[] = "SELECT \"{$time}\" AS endTime";
        }
        $timeRangesRawSQL = implode(' UNION ', $timeRangesRawSQL);
        return static::sanitizeVoteForField(\DB::query()
            ->selectRaw('CAST(timeRangesRawSQL.endTime AS UNSIGNED) AS endTime, isValid, voteFor, CAST(SUM(timeGroups.count) AS UNSIGNED) AS count')
            ->fromSub(BilibiliVoteModel
                ::selectRaw("FLOOR(UNIX_TIMESTAMP(postTime)/{$timeRange})*{$timeRange} as endTime, isValid, voteFor, COUNT(*) as count")
                ->whereIn('voteFor', static::getTopVotesCandidatesSQL(10))
                ->groupBy('endTime', 'isValid', 'voteFor')
            , 'timeGroups')
            ->join(\DB::raw("({$timeRangesRawSQL}) AS timeRangesRawSQL"),
            'timeGroups.endTime', '<', 'timeRangesRawSQL.endTime') // cumulative
            ->groupBy('endTime', 'isValid', 'voteFor')
            ->orderBy('endTime', 'ASC')
            ->orderBy('voteFor', 'ASC')
            ->get()
            ->map(fn ($i) => (array)$i))
            ->toJson();
    }

    private static function sanitizeVoteForField(Collection $collection): Collection
    {
        return $collection
            ->filter(fn ($i) => Regex::match('/^(0|[1-9][0-9]*)$/', $i['voteFor'] ?? '')->hasMatch())
            ->map(function ($i) {
                $i['voteFor'] = (int)$i['voteFor'];
                return $i;
            })
            ->filter(fn ($i) => $i['voteFor'] >= 1 && $i['voteFor'] <= 1056);
    }
}
