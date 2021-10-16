export interface ApiError { errorCode: number, errorInfo: Record<string, unknown[]> | string }
export type ApiQS = Record<string, unknown>;

type ISO8601DateUTCTimeFromStr = string; // "2020-10-10T00:11:22Z"
type UnixTimestamp = number;
type IntFromStr = number | string; // "1"

export type ApiStatus = Array<{
    startTime: ISO8601DateTimeInUTCFromStr,
    queueTiming: number,
    webRequestTiming: number,
    savePostsTiming: number,
    webRequestTimes: IntFromStr,
    parsedPostTimes: IntFromStr,
    parsedUserTimes: IntFromStr
}>;
export interface ApiQSStatus extends ApiQS {
    timeGranular: 'day' | 'hour' | 'minute',
    startTime: UnixTimestamp,
    endTime: UnixTimestamp
}
