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
     * Generate a query builder, which returns top $candidateCount candidates based on valid votes
     *
     * @sql select `voteFor` from (select `voteFor`, COUNT(*) AS count from `tbm_bilibiliVote` where `isValid` = 1 group by `voteFor` order by `count` desc limit $candidateCount) as `T`
     * @param int $candidateCount
     * @return \Illuminate\Database\Query\Builder
     */
    private static function getCandidatesWithMostVotes(int $candidateCount): \Illuminate\Database\Query\Builder
    {
        return \DB::query()->select('voteFor')->fromSub(
            BilibiliVoteModel::select('voteFor')
                ->selectRaw('COUNT(*) AS count')
                ->where('isValid', true)
                ->groupBy('voteFor')
                ->orderBy('count', 'DESC')
                ->limit($candidateCount)
        , 'T');
    }

    /**
     * Return every candidates' valid and invalid votes count
     *
     * @sql select `isValid`, `voteFor`, COUNT(*) AS count from `tbm_bilibiliVote` group by `isValid`, `voteFor` order by `voteFor` asc
     * @param Request $request
     * @return string
     */
    public static function allCandidatesVoteCount(Request $request): string
    {
        return self::sanitizeVoteForField(BilibiliVoteModel::select(['isValid', 'voteFor'])
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
    public static function allVoteCountsGroupByTime(Request $request): string
    {
        $groupByTimeGranularity = Helper::rawSqlGroupByTimeGranularity('postTime', ['minute', 'hour']);
        $request->validate([
            'timeGranularity' => ['required', Rule::in(array_keys($groupByTimeGranularity))]
        ]);
        return BilibiliVoteModel::selectRaw($groupByTimeGranularity[$request->query()['timeGranularity']])
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
    public static function top50CandidatesVoteCount(Request $request): string
    {
        return self::sanitizeVoteForField(BilibiliVoteModel::select(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade')
            ->whereIn('voteFor', self::getCandidatesWithMostVotes(50))
            ->groupBy('isValid', 'voteFor')
            ->orderBy('voteFor', 'ASC')
            ->get())
            ->map(static function ($i) {
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
    public static function top5CandidatesVoteCountGroupByTime(Request $request): string
    {
        $groupBytimeGranularity = Helper::rawSqlGroupByTimeGranularity('postTime', ['minute', 'hour']);
        $request->validate([
            'timeGranularity' => ['required', Rule::in(array_keys($groupBytimeGranularity))]
        ]);
        return self::sanitizeVoteForField(BilibiliVoteModel::selectRaw($groupBytimeGranularity[$request->query()['timeGranularity']])
            ->addSelect(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count')
            ->whereIn('voteFor', self::getCandidatesWithMostVotes(5))
            ->groupBy('time', 'isValid', 'voteFor')
            ->orderBy('time', 'ASC')
            ->get())
            ->toJson();
    }

    /**
     * Return every 5 mins sum of cumulative votes count, group by candidates and validate
     *
     * @sql select CAST(timeGranularityRawSQL.endTime AS UNSIGNED) AS endTime, isValid, voteFor, CAST(SUM(timeGroups.count) AS UNSIGNED) AS count
     * from (
     *   select FLOOR(UNIX_TIMESTAMP(postTime)/300)*300 as endTime, isValid, voteFor, COUNT(*) as count from `tbm_bilibiliVote`
     *   where `voteFor` in getTopVotesCandidatesSQL(10)
     *   group by `endTime`, `isValid`, `voteFor`
     * ) as `timeGroups`
     * inner join (SELECT "1552192500" AS endTime UNION ...every +300... UNION SELECT "1552276800" AS endTime) AS timeGranularityRawSQL
     * on `timeGroups`.`endTime` < `timeGranularityRawSQL`.`endTime`
     * group by `endTime`, `isValid`, `voteFor`
     * order by `endTime` asc
     * @param Request $request
     * @return string
     */
    public static function top10CandidatesTimeline(Request $request): string
    {
        $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
        $voteEndTime = '2019-03-11T12:00:00';
        $timeGranularity = 5 * 60; // 5 mins
        $timeGranularityRawSQL = [];
        for ($time = strtotime($voteStartTime); $time <= strtotime($voteEndTime); $time += $timeGranularity) {
            $timeGranularityRawSQL[] = "SELECT \"{$time}\" AS endTime";
        }
        $timeGranularityRawSQL = implode(' UNION ', $timeGranularityRawSQL);
        return self::sanitizeVoteForField(\DB::query()
            ->selectRaw('CAST(timeGranularityRawSQL.endTime AS UNSIGNED) AS endTime, isValid, voteFor, CAST(SUM(timeGroups.count) AS UNSIGNED) AS count')
            ->fromSub(BilibiliVoteModel
                ::selectRaw("FLOOR(UNIX_TIMESTAMP(postTime)/{$timeGranularity})*{$timeGranularity} as endTime, isValid, voteFor, COUNT(*) as count")
                ->whereIn('voteFor', self::getCandidatesWithMostVotes(10))
                ->groupBy('endTime', 'isValid', 'voteFor')
            , 'timeGroups')
            ->join(\DB::raw("({$timeGranularityRawSQL}) AS timeGranularityRawSQL"),
            'timeGroups.endTime', '<', 'timeGranularityRawSQL.endTime') // cumulative
            ->groupBy('endTime', 'isValid', 'voteFor')
            ->orderBy('endTime', 'ASC')
            ->orderBy('voteFor', 'ASC')
            ->get()
            ->map(static fn ($i) => (array)$i))
            ->toJson();
    }

    private static function sanitizeVoteForField(Collection $collection): Collection
    {
        return $collection
            ->filter(static fn ($i) => Regex::match('/^(0|[1-9][0-9]*)$/', $i['voteFor'] ?? '')->hasMatch())
            ->map(static function ($i) {
                $i['voteFor'] = (int)$i['voteFor'];
                return $i;
            })
            ->filter(static fn ($i) => $i['voteFor'] >= 1 && $i['voteFor'] <= 1056);
    }
}
