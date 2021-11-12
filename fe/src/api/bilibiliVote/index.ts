import type { Float, SqlDateTimeUtcPlus8, UInt, UnixTimestamp } from '@/shared';
import allCandidatesVotesCount from './allCandidatesVotesCount.json';
import allVotesCountByTimeHourGranularity from './allVotesCountByTimeHourGranularity.json';
import allVotesCountByTimeMinuteGranularity from './allVotesCountByTimeMinuteGranularity.json';
import candidatesName from './candidatesName.json';
import top5CandidatesVotesCountByTimeHourGranularity from './top5CandidatesVotesCountByTimeHourGranularity.json';
import top5CandidatesVotesCountByTimeMinuteGranularity from './top5CandidatesVotesCountByTimeMinuteGranularity.json';
import top10CandidatesTimeline from './top10CandidatesTimeline.json';
import top50CandidatesVotesCount from './top50CandidatesVotesCount.json';
import top50OfficialValidVotesCount from './top50OfficialValidVotesCount.json';

export const json: {
    allCandidatesVotesCount: AllCandidatesVotesCount,
    allVotesCountByTimeHourGranularity: AllVotesCountByTime,
    allVotesCountByTimeMinuteGranularity: AllVotesCountByTime,
    candidatesName: CandidatesName,
    top5CandidatesVotesCountByTimeHourGranularity: Top5CandidatesVotesCountByTime,
    top5CandidatesVotesCountByTimeMinuteGranularity: Top5CandidatesVotesCountByTime,
    top10CandidatesTimeline: Top10CandidatesTimeline,
    top50CandidatesVotesCount: Top50CandidatesVotesCount,
    top50OfficialValidVotesCount: Top50OfficialValidVotesCount
} = {
    allCandidatesVotesCount: allCandidatesVotesCount as AllCandidatesVotesCount,
    allVotesCountByTimeHourGranularity: allVotesCountByTimeHourGranularity as AllVotesCountByTime,
    allVotesCountByTimeMinuteGranularity: allVotesCountByTimeMinuteGranularity as AllVotesCountByTime,
    candidatesName,
    top5CandidatesVotesCountByTimeHourGranularity: top5CandidatesVotesCountByTimeHourGranularity as Top5CandidatesVotesCountByTime,
    top5CandidatesVotesCountByTimeMinuteGranularity: top5CandidatesVotesCountByTimeMinuteGranularity as Top5CandidatesVotesCountByTime,
    top10CandidatesTimeline: top10CandidatesTimeline as Top10CandidatesTimeline,
    top50CandidatesVotesCount: top50CandidatesVotesCount as Top50CandidatesVotesCount,
    top50OfficialValidVotesCount
};

export type IsValid = 0 | 1;
export type CountByTimeGranularity = 'hour' | 'minute';
export type CandidatesName = string[];
export type AllCandidatesVotesCount = Array<{
    isValid: IsValid,
    voteFor: UInt,
    count: UInt
}>;
export type Top50OfficialValidVotesCount = Array<{
    voteFor: UInt,
    officialValidCount: UInt
}>;
export type Top50CandidatesVotesCount = AllCandidatesVotesCount & Array<{ voterAvgGrade: Float }>;
export type Top5CandidatesVotesCountByTime = AllCandidatesVotesCount & Array<{ time: SqlDateTimeUtcPlus8 }>;
export type AllVotesCountByTime = Array<{
    time: SqlDateTimeUtcPlus8,
    isValid: IsValid,
    count: UInt
}>;
export type Top10CandidatesTimeline = AllCandidatesVotesCount & Array<{ endTime: UnixTimestamp }>;
