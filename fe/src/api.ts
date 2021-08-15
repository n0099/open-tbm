import qs from 'qs';
import type { ApiError, ApiQSStatus, ApiStatus } from './api.d';

const getRequester = async <T extends ApiError>(endpoint: string, queryString?: Record<string, unknown>): Promise<ApiError | T> => {
    try {
        const res = await fetch(`${process.env.VUE_APP_PUBLIC_PATH}${endpoint}?${qs.stringify(queryString)}`);
        if (!res.ok) throw Error(`API ${endpoint} 返回 HTTP ${res.status} 错误`);
        return await res.json() as T;
    } catch (e: unknown) {
        if (e instanceof Error) {
            const { message } = e;
            return { error: message };
        }
        throw e;
    }
};

const reCAPTCHACheck = async (action = ''): Promise<string> => new Promise((reslove, reject) => {
    grecaptcha.ready(() => {
        grecaptcha.execute(process.env.VUE_APP_RECAPTCHA_SITE_KEY, { action }).then(
            token => {
                reslove(token);
            }, (...args) => {
                reject(new Error(JSON.stringify(args)));
            }
        );
    });
});

const getRequesterWithReCAPTCHA = async (endpoint: string, queryString?: Record<string, unknown>, action = '') =>
    getRequester(endpoint, { ...queryString, token: await reCAPTCHACheck(action) });

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
export const isApiError = (r: any): r is ApiError => 'error' in r && typeof r.error === 'string';
export const apiStatus = async (statusQuery: ApiQSStatus): Promise<ApiError | ApiStatus> => getRequesterWithReCAPTCHA('/status', statusQuery);
