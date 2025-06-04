import allCandidatesVoteCount from '@/api/bilibiliVote/allCandidatesVoteCount.json' with { type: 'json' };
import allVoteCountGroupByHour from '@/api/bilibiliVote/allVoteCountGroupByHour.json' with { type: 'json' };
import allVoteCountGroupByMinute from '@/api/bilibiliVote/allVoteCountGroupByMinute.json' with { type: 'json' };
import candidateNames from '@/api/bilibiliVote/candidateNames.json' with { type: 'json' };
import top10CandidatesTimeline from '@/api/bilibiliVote/top10CandidatesTimeline.json' with { type: 'json' };
import top50CandidatesOfficialValidVoteCount from '@/api/bilibiliVote/top50CandidatesOfficialValidVoteCount.json' with { type: 'json' };
import top50CandidatesVoteCount from '@/api/bilibiliVote/top50CandidatesVoteCount.json' with { type: 'json' };
import top5CandidatesVoteCountGroupByHour from '@/api/bilibiliVote/top5CandidatesVoteCountGroupByHour.json' with { type: 'json' };
import top5CandidatesVoteCountGroupByMinute from '@/api/bilibiliVote/top5CandidatesVoteCountGroupByMinute.json' with { type: 'json' };

export const json = {
    allCandidatesVoteCount: allCandidatesVoteCount as AllCandidatesVoteCount,
    allVoteCountGroupByHour: allVoteCountGroupByHour as AllVoteCountsGroupByTime,
    allVoteCountGroupByMinute: allVoteCountGroupByMinute as AllVoteCountsGroupByTime,
    candidateNames: candidateNames as CandidatesName,
    top5CandidatesVoteCountGroupByHour: top5CandidatesVoteCountGroupByHour as Top5CandidatesVoteCountGroupByTime,
    top5CandidatesVoteCountGroupByMinute: top5CandidatesVoteCountGroupByMinute as Top5CandidatesVoteCountGroupByTime,
    top10CandidatesTimeline: top10CandidatesTimeline as Top10CandidatesTimeline,
    top50CandidatesVoteCount: top50CandidatesVoteCount as Top50CandidatesVoteCount,
    top50CandidatesOfficialValidVoteCount: top50CandidatesOfficialValidVoteCount as Top50CandidatesOfficialValidVoteCount
};

export type IsValid = BoolInt;
export type GroupByTimeGranularity = 'hour' | 'minute';
export type CandidatesName = string[];
export type AllCandidatesVoteCount = Array<{
    isValid: IsValid,
    voteFor: UInt,
    count: UInt
}>;
export type Top50CandidatesOfficialValidVoteCount = Array<{
    voteFor: UInt,
    officialValidCount: UInt
}>;
export type Top50CandidatesVoteCount = AllCandidatesVoteCount & Array<{ voterAvgGrade: Float }>;
export type Top5CandidatesVoteCountGroupByTime = AllCandidatesVoteCount & Array<{ time: SqlDateTimeUtcPlus8 }>;
export type AllVoteCountsGroupByTime = Array<{
    time: SqlDateTimeUtcPlus8,
    isValid: IsValid,
    count: UInt
}>;
export type Top10CandidatesTimeline = AllCandidatesVoteCount & Array<{ endTime: UnixTimestamp }>;
