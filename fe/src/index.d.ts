import { DateTime } from 'luxon';

declare global {
    namespace NodeJS {
        interface ProcessEnv {
            VUE_APP_PUBLIC_PATH: string,
            VUE_APP_GA_MEASUREMENT_ID: string,
            VUE_APP_RECAPTCHA_SITE_KEY: string
        }
    }
}

export interface ApiQS {
    [key: string]: any
}
export interface ApiStatus {}
export interface ApiQSStatus extends ApiQS {
    timeRange: 'minute' | 'hour' | 'day',
    startTime: string,
    endTime: string
}
