export interface ApiError { errorCode: number; errorInfo: string | Record<string, unknown[]>; }
export type ApiQS = Record<string, unknown>;

export interface ApiStatus {
    // todo
}
export interface ApiQSStatus extends ApiQS {
    timeRange: 'day' | 'hour' | 'minute',
    startTime: string,
    endTime: string
}
