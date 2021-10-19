export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }
export type ApiQueryParam = Record<string, unknown>;

type Iso8601DateTimeUtc0 = string; // "2020-10-10T00:11:22Z"
type UnixTimestamp = number;
type IntFromStr = number | string; // "1"

export type ApiStatus = Array<{
    startTime: Iso8601DateTimeUtc0,
    queueTiming: number,
    webRequestTiming: number,
    savePostsTiming: number,
    webRequestTimes: IntFromStr,
    parsedPostTimes: IntFromStr,
    parsedUserTimes: IntFromStr
}>;
export interface ApiStatusQP extends ApiQueryParam {
    timeGranular: 'day' | 'hour' | 'minute',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}
export type ApiForumList = Array<{
    id: number,
    fid: number,
    name: string,
    isCrawling: number
}>;
interface TimeCountPair { time: string, count: number }
export type ApiStats = Array<{
    thread: TimeCountPair,
    reply: TimeCountPair,
    subReply: TimeCountPair
}>;
export interface ApiStatsQP extends ApiQueryParam {
    fid: number,
    timeGranular: 'day' | 'hour' | 'minute' | 'month' | 'week' | 'year',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}
