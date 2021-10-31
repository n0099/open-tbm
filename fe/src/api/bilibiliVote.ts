import type { Float, SqlDateTimeUtcPlus8, UInt, UnixTimestamp } from '@/shared';
import type { ApiError, ApiQueryParam } from '@/api/index.d';
import { getRequester } from '@/api';
import _ from 'lodash';

export type IsValid = 0 | 1;
export type CountByTimeGranularity = 'hour' | 'minute';
interface CountByTimeGranularityQP extends ApiQueryParam { timeGranularity: CountByTimeGranularity }
export type ApiCandidatesName = string[];
export type ApiAllCandidatesVotesCount = Array<{
    isValid: IsValid,
    voteFor: UInt,
    count: UInt
}>;
export type ApiTop50OfficialValidVotesCount = Array<{
    voteFor: UInt,
    officialValidCount: UInt
}>;
export type ApiTop50CandidatesVotesCount = ApiAllCandidatesVotesCount & Array<{ voterAvgGrade: Float }>;
export type ApiTop5CandidatesVotesCountByTime = ApiAllCandidatesVotesCount & Array<{ time: SqlDateTimeUtcPlus8 }>;
export type ApiAllVotesCountByTime = Array<{
    time: SqlDateTimeUtcPlus8,
    isValid: IsValid,
    count: UInt
}>;
export type ApiTop10CandidatesTimeline = ApiAllCandidatesVotesCount & Array<{ endTime: UnixTimestamp }>;

export const apiCandidatesName = async (): Promise<ApiCandidatesName | ApiError> =>
    getRequester('/bilibiliVote/candidatesName.json');
export const apiTop50OfficialValidVotesCount = async (): Promise<ApiError | ApiTop50OfficialValidVotesCount> =>
    getRequester('/bilibiliVote/top50OfficialValidVotesCount.json');
export const apiAllCandidatesVotesCount = async (): Promise<ApiAllCandidatesVotesCount | ApiError> =>
    getRequester('/bilibiliVote/allCandidatesVotesCount.json');
export const apiTop50CandidatesVotesCount = async (): Promise<ApiError | ApiTop50CandidatesVotesCount> =>
    getRequester('/bilibiliVote/top50CandidatesVotesCount.json');
export const apiTop10CandidatesTimeline = async (): Promise<ApiError | ApiTop10CandidatesTimeline> =>
    getRequester('/bilibiliVote/top10CandidatesTimeline.json');
export const apiTop5CandidatesVotesCountByTime = async (qp: CountByTimeGranularityQP): Promise<ApiError | ApiTop5CandidatesVotesCountByTime> =>
    getRequester(`/bilibiliVote/top5CandidatesVotesCountByTime${_.capitalize(qp.timeGranularity)}Granularity.json`);
export const apiAllVotesCountByTime = async (qp: CountByTimeGranularityQP): Promise<ApiAllVotesCountByTime | ApiError> =>
    getRequester(`/bilibiliVote/allVotesCountByTime${_.capitalize(qp.timeGranularity)}Granularity.json`);
