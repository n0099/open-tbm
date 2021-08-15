export interface ApiError { error: string }
export type ApiQS = Record<string, unknown>;

export interface ApiStatus {
    // todo
}
export interface ApiQSStatus extends ApiQS {
    timeRange: 'day' | 'hour' | 'minute',
    startTime: string,
    endTime: string
}
