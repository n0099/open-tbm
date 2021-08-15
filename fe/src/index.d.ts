/* eslint-disable @typescript-eslint/naming-convention */
declare global {
    namespace NodeJS {
        interface ProcessEnv {
            VUE_APP_PUBLIC_PATH: string,
            VUE_APP_GA_MEASUREMENT_ID: string,
            VUE_APP_RECAPTCHA_SITE_KEY: string
        }
    }
}
/* eslint-enable @typescript-eslint/naming-convention */

export {};
