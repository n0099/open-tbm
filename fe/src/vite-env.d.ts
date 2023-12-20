/* eslint-disable @typescript-eslint/naming-convention */
/// <reference types="vite/client" />

// https://github.com/vitejs/vite/blob/ec9d2e779d4b8d785c648430594d534d461d6639/packages/vite/client.d.ts#L145
declare module '*.avifs' {
    const src: string;
    export default src;
}

interface ImportMetaEnv {
    readonly VITE_API_URL_PREFIX: string,
    readonly VITE_GA_MEASUREMENT_ID: string,
    readonly VITE_RECAPTCHA_SITE_KEY: string,
    readonly VITE_INSTANCE_NAME: string,
    readonly VITE_FOOTER_TEXT: string
}

interface ImportMeta {
    readonly env: ImportMetaEnv
}
