import type { Float, UInt, UnixTimestamp } from '@/shared';
import type { Mix } from '@/shared/groupByTimeGranularUtcPlus8';
export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }
export type ApiQueryParam = Record<never, never>;

export type ApiForumList = Array<{
    id: number,
    fid: number,
    name: string,
    isCrawling: number
}>;

export type ApiStatus = Array<{
    startTime: UnixTimestamp,
    queueTiming: Float,
    webRequestTiming: Float,
    savePostsTiming: Float,
    webRequestTimes: UInt,
    parsedPostTimes: UInt,
    parsedUserTimes: UInt
}>;
export interface ApiStatusQP extends ApiQueryParam {
    timeGranular: 'day' | 'hour' | 'minute',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}

interface TimeCountPair { time: Mix, count: UInt }
export interface ApiStatsForumPostsCount {
    thread: TimeCountPair[],
    reply: TimeCountPair[],
    subReply: TimeCountPair[]
}
export interface ApiStatsQP extends ApiQueryParam {
    fid: UInt,
    timeGranular: 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}
