import type { ApiError, ApiQueryParam } from '@/api/index.d';
import { getRequester, getRequesterWithReCAPTCHA } from '@/api';
import type { Float, SqlDateTimeUtcPlus8, UInt, UnixTimestamp } from '@/shared';

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
    getRequesterWithReCAPTCHA('/bilibiliVote/allCandidatesVotesCount');
export const apiTop50CandidatesVotesCount = async (): Promise<ApiError | ApiTop50CandidatesVotesCount> =>
    getRequesterWithReCAPTCHA('/bilibiliVote/top50CandidatesVotesCount');
export const apiTop5CandidatesVotesCountByTime = async (qp: CountByTimeGranularityQP): Promise<ApiError | ApiTop5CandidatesVotesCountByTime> =>
    getRequesterWithReCAPTCHA('/bilibiliVote/top5CandidatesVotesCountByTime', qp);
export const apiAllVotesCountByTime = async (qp: CountByTimeGranularityQP): Promise<ApiAllVotesCountByTime | ApiError> =>
    getRequesterWithReCAPTCHA('/bilibiliVote/allVotesCountByTime', qp);
export const apiTop10CandidatesTimeline = async (): Promise<ApiError | ApiTop10CandidatesTimeline> =>
    getRequesterWithReCAPTCHA('/bilibiliVote/top10CandidatesTimeline');
