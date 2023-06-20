/* eslint-disable @typescript-eslint/naming-convention */
declare global {
    namespace NodeJS {
        interface ProcessEnv {
            NODE_ENV: 'development' | 'production' | 'test',
            BASE_URL: string,
            VUE_APP_PUBLIC_PATH: string,
            VUE_APP_API_URL_PREFIX: string,
            VUE_APP_GA_MEASUREMENT_ID: string,
            VUE_APP_RECAPTCHA_SITE_KEY: string,
            VUE_APP_INSTANCE_NAME: string,
            VUE_APP_FOOTER_TEXT: string
        }
    }
}
/* eslint-enable @typescript-eslint/naming-convention */

export {};
