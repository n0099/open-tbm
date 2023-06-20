/* eslint-disable @typescript-eslint/naming-convention */
// eslint-disable-next-line spaced-comment
/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_PUBLIC_PATH: string,
    readonly VITE_API_URL_PREFIX: string,
    readonly VITE_GA_MEASUREMENT_ID: string,
    readonly VITE_RECAPTCHA_SITE_KEY: string,
    readonly VITE_INSTANCE_NAME: string,
    readonly VITE_FOOTER_TEXT: string
}

interface ImportMeta {
    readonly env: ImportMetaEnv
}
