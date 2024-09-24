<?php

namespace App\Http;

use App\Eloquent\Model\BilibiliVote;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Spatie\Regex\Regex;

class BilibiliVoteQuery
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Generate a query builder, which returns top $candidateCount candidates based on valid votes
     */
    private function getCandidatesWithMostVotes(int $candidateCount): \Illuminate\Database\Query\Builder
    {
        /*
         * select `voteFor` from (
         *     select `voteFor`, COUNT(*) AS count
         *     from `tbm_bilibiliVote`
         *     where `isValid` = 1
         *     group by `voteFor`
         *     order by `count` desc
         *     limit $candidateCount
         * ) as `T`
         */
        return $this->db->query()->select('voteFor')->fromSub(
            BilibiliVote::select('voteFor')
                ->selectRaw('COUNT(*) AS count')
                ->where('isValid', true)
                ->groupBy('voteFor')
                ->orderBy('count', 'DESC')
                ->limit($candidateCount),
            'T',
        );
    }

    /**
     * Return every candidate valid and invalid votes count
     */
    public static function allCandidatesVoteCount(Request $request): string
    {
        /*
         * select `isValid`, `voteFor`, COUNT(*) AS count
         * from `tbm_bilibiliVote`
         * group by `isValid`, `voteFor`
         * order by `voteFor` asc
         */
        return self::normalizeVoteFor(BilibiliVote::select(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count')
            ->groupBy('isValid', 'voteFor')
            ->orderBy('voteFor', 'ASC')
            ->get())
            ->values()
            ->toJson();
    }

    /**
     * @return string[]
     * @psalm-return array{minute: string, hour: string, day: string, week: string, month: string, year: string}
     */
    public static function rawSqlGroupByTimeGranularity(
        string $fieldName,
        array $timeGranularity = ['minute', 'hour', 'day', 'week', 'month', 'year'],
    ): array {
        return Arr::only([
            'minute' => "DATE_FORMAT($fieldName, \"%Y-%m-%d %H:%i\") AS time",
            'hour' => "DATE_FORMAT($fieldName, \"%Y-%m-%d %H:00\") AS time",
            'day' => "DATE($fieldName) AS time",
            'week' => "DATE_FORMAT($fieldName, \"%Y年第%u周\") AS time",
            'month' => "DATE_FORMAT($fieldName, \"%Y-%m\") AS time",
            'year' => "DATE_FORMAT($fieldName, \"%Y年\") AS time",
        ], $timeGranularity);
    }

    /**
     * Return all valid and invalid votes count, group by given time range
     */
    public static function allVoteCountsGroupByTime(Request $request): string
    {
        /*
         * select DATE_FORMAT(postTime, "%Y-%m-%d %H:%i|00") AS time, isValid, COUNT(*) AS count
         * from `tbm_bilibiliVote`
         * group by `time`, `isValid`
         * order by `time` asc
         */
        $groupByTimeGranularity = self::rawSqlGroupByTimeGranularity('postTime', ['minute', 'hour']);
        $request->validate([
            'timeGranularity' => ['required', Rule::in(array_keys($groupByTimeGranularity))],
        ]);
        return BilibiliVote::selectRaw($groupByTimeGranularity[$request->query()['timeGranularity']])
            ->selectRaw('isValid, COUNT(*) AS count')
            ->groupBy('time', 'isValid')
            ->orderBy('time', 'ASC')
            ->get()->toJson();
    }

    /**
     * Return votes count and average voters' exp grade of top 50 candidates
     */
    public function top50CandidatesVoteCount(Request $request): string
    {
        /*
         * select `isValid`, `voteFor`, COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade
         * from `tbm_bilibiliVote`
         * where `voteFor` in getTopVotesCandidatesSQL(50)
         * group by `isValid`, `voteFor`
         * order by `voteFor` asc
         */
        return self::normalizeVoteFor(BilibiliVote::select(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade')
            ->whereIn('voteFor', $this->getCandidatesWithMostVotes(50))
            ->groupBy('isValid', 'voteFor')
            ->orderBy('voteFor', 'ASC')
            ->get())
            ->map(static function ($i) {
                $i['voterAvgGrade'] = (float) $i['voterAvgGrade'];
                return $i;
            })
            ->toJson();
    }

    /**
     * Return votes count of top 5 candidates, group by given time range
     */
    public function top5CandidatesVoteCountGroupByTime(Request $request): string
    {
        /*
         * select DATE_FORMAT(postTime, "%Y-%m-%d %H:%i|00") AS time, `isValid`, `voteFor`, COUNT(*) AS count
         * from `tbm_bilibiliVote`
         * where `voteFor` in getTopVotesCandidatesSQL(5)
         * group by `time`, `isValid`, `voteFor`
         * order by `time` asc
         */
        $groupBytimeGranularity = self::rawSqlGroupByTimeGranularity('postTime', ['minute', 'hour']);
        $request->validate([
            'timeGranularity' => ['required', Rule::in(array_keys($groupBytimeGranularity))],
        ]);
        return self::normalizeVoteFor(BilibiliVote::selectRaw(
            $groupBytimeGranularity[$request->query()['timeGranularity']],
        )
            ->addSelect(['isValid', 'voteFor'])
            ->selectRaw('COUNT(*) AS count')
            ->whereIn('voteFor', $this->getCandidatesWithMostVotes(5))
            ->groupBy('time', 'isValid', 'voteFor')
            ->orderBy('time', 'ASC')
            ->get())
            ->toJson();
    }

    /**
     * Return every 5-min sum of cumulative votes count, group by candidates and validate
     */
    public function top10CandidatesTimeline(Request $request): string
    {
        /*
         * select CAST(timeGranularityRawSQL.endTime AS UNSIGNED) AS endTime,
         *     isValid, voteFor, CAST(SUM(timeGroups.count) AS UNSIGNED) AS count
         * from (
         *     select FLOOR(UNIX_TIMESTAMP(postTime)/300)*300 as endTime,
         *     isValid, voteFor, COUNT(*) as count
         *     from `tbm_bilibiliVote`
         *     where `voteFor` in getTopVotesCandidatesSQL(10)
         *     group by `endTime`, `isValid`, `voteFor`
         * ) as `timeGroups`
         * inner join (
         *     select "1552192500" as endTime union
         *     ...every +300... union
         *     select "1552276800" as endTime
         * ) AS timeGranularityRawSQL
         * on `timeGroups`.`endTime` < `timeGranularityRawSQL`.`endTime`
         * group by `endTime`, `isValid`, `voteFor`
         * order by `endTime` asc
         */
        $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
        $voteEndTime = '2019-03-11T12:00:00';
        $timeGranularity = 5 * 60; // 5 mins
        $timeGranularityRawSQL = [];
        for ($time = strtotime($voteStartTime); $time <= strtotime($voteEndTime); $time += $timeGranularity) {
            $timeGranularityRawSQL[] = "SELECT '$time' AS endTime";
        }
        $timeGranularityRawSQL = implode(' UNION ', $timeGranularityRawSQL);
        return self::normalizeVoteFor($this->db->query()
            ->selectRaw('CAST(timeGranularityRawSQL.endTime AS UNSIGNED) AS endTime,
                isValid, voteFor, CAST(SUM(timeGroups.count) AS UNSIGNED) AS count')
            ->fromSub(BilibiliVote::selectRaw(
                "FLOOR(UNIX_TIMESTAMP(postTime)/$timeGranularity)*$timeGranularity as endTime,
                isValid, voteFor, COUNT(*) as count",
            )
                ->whereIn('voteFor', $this->getCandidatesWithMostVotes(10))
                ->groupBy('endTime', 'isValid', 'voteFor'), 'timeGroups')
            ->join(
                $this->db->raw("($timeGranularityRawSQL) AS timeGranularityRawSQL"),
                'timeGroups.endTime',
                '<',
                'timeGranularityRawSQL.endTime',
            ) // cumulative
            ->groupBy('endTime', 'isValid', 'voteFor')
            ->orderBy('endTime', 'ASC')
            ->orderBy('voteFor', 'ASC')
            ->get()
            ->map(static fn($i) => (array) $i))
            ->toJson();
    }

    private static function normalizeVoteFor(Collection $collection): Collection
    {
        return $collection
            ->filter(static fn($i) => Regex::match('/^(0|[1-9][0-9]*)$/', $i['voteFor'] ?? '')->hasMatch())
            ->map(static function ($i) {
                $i['voteFor'] = (int) $i['voteFor'];
                return $i;
            })
            ->filter(static fn($i) => $i['voteFor'] >= 1 && $i['voteFor'] <= 1056);
    }
}
