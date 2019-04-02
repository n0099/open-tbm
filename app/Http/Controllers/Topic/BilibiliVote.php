<?php

namespace App\Http\Controllers\Topic;

use App\Eloquent\BilibiliVoteModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BilibiliVote
{
    private static $groupTimeRangeRawSQL = [
        'minute' => 'DATE_FORMAT(postTime, "%Y-%m-%d %H:%i") AS time',
        'hour' => 'DATE_FORMAT(postTime, "%Y-%m-%d %H:00") AS time',
    ];

    private static function getTopVotesCandidates(int $candidatesCount): array
    {
        return BilibiliVoteModel::select('voteFor')
            ->selectRaw('COUNT(*) AS count')
            ->where('isValid', true)
            ->groupBy('voteFor')
            ->orderBy('count', 'DESC')
            ->limit($candidatesCount)
            ->get()->pluck('voteFor')
            ->toArray();
    }

    public static function top50CandidatesVotesCountResult(Request $request)
    {
        $top50Candidates = static::getTopVotesCandidates(50);
        return BilibiliVoteModel::select(['voteFor', 'isValid'])
            ->selectRaw('COUNT(*) AS count, AVG(authorExpGrade) AS voterAvgGrade')
            ->whereIn('voteFor', $top50Candidates)
            ->groupBy('voteFor', 'isValid')
            ->orderBy('count', 'DESC')
            ->get()->toJson();
    }

    public static function top5CandidatesVotesCountByTime(Request $request)
    {
        $request->validate([
            'timeRange' => Rule::in(array_keys(static::$groupTimeRangeRawSQL))
        ]);
        $top10Candidates = static::getTopVotesCandidates(5);
        return BilibiliVoteModel::selectRaw(static::$groupTimeRangeRawSQL[$request->query()['timeRange']])
            ->selectRaw('COUNT(*) AS count')
            ->addSelect(['voteFor', 'isValid'])
            ->whereIn('voteFor', $top10Candidates)
            ->groupBy('time', 'voteFor', 'isValid')
            ->orderBy('time', 'DESC')
            ->get()->toJson();
    }

    public static function getAllVotesCountByTime(Request $request)
    {
        $request->validate([
            'timeRange' => Rule::in(array_keys(static::$groupTimeRangeRawSQL))
        ]);
        return BilibiliVoteModel::selectRaw(static::$groupTimeRangeRawSQL[$request->query()['timeRange']])
            ->selectRaw('COUNT(*) AS count, isValid')
            ->groupBy('time', 'isValid')
            ->orderBy('time', 'DESC')
            ->get()->toJson();
    }

    public static function top10CandidatesTimeline(Request $request)
    {
        $top10Candidates = static::getTopVotesCandidates(10);
        $voteStartTime = '2019-03-10T12:35:00'; // exactly 2019-03-10T12:38:17
        $voteEndTime = '2019-03-11T12:00:00';
        $timeRange = 5 * 60; // 5 mins
        $timeRangesRawSQL = [];
        for ($time = strtotime($voteStartTime); $time <= strtotime($voteEndTime); $time += $timeRange) {
            $timeRangesRawSQL[] = "SELECT \"{$time}\" AS endTime";
        }
        return \DB::query()
            ->selectRaw('SUM(timeGroups.count) AS count, timeRangesRawSQL.endTime, isValid, voteFor')
            ->fromSub(BilibiliVoteModel
                ::selectRaw("FLOOR(UNIX_TIMESTAMP(postTime)/{$timeRange})*{$timeRange} as endTime, COUNT(*) as count, isValid, voteFor")
                ->whereIn('voteFor', $top10Candidates)
                ->groupBy('endTime', 'isValid', 'voteFor'), 'timeGroups')
            ->join(\DB::raw('(' . implode(' UNION ', $timeRangesRawSQL) . ') AS timeRangesRawSQL'), 'timeGroups.endTime', '<', 'timeRangesRawSQL.endTime')
            ->groupBy('endTime', 'isValid', 'voteFor')
            ->orderBy('count', 'ASC')
            ->get()->toJson();
    }
}
