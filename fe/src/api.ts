import type { ApiError, ApiQSStatus, ApiStatus } from '@/api.d';
import NProgress from 'nprogress';
import qs from 'qs';
import _ from 'lodash';
import Noty from 'noty';

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
export const isApiError = (r: any): r is ApiError => 'errorInfo' in r && typeof r.errorInfo === 'string';
const getRequester = async <T = ApiError | unknown>(endpoint: string, queryString?: Record<string, unknown>): Promise<ApiError | T> => {
    NProgress.start();
    document.body.style.cursor = 'progress';
    try {
        const res = await fetch(
            `${process.env.VUE_APP_API_URL_PREFIX}${endpoint}?${qs.stringify(queryString)}`,
            { headers: { Accept: 'application/json' } }
        );
        let error = null;
        if (!res.ok) {
            error = `GET ${endpoint} => HTTP ${res.status}`;
            throw Error(error);
        }
        const json = await res.json() as T;
        if (isApiError(json)) {
            error = `GET ${endpoint} => HTTP ${res.status} 错误码：${json.errorCode}<br />`;
            if (_.isObject(json.errorInfo)) {
                error = _.map(json.errorInfo, (info, paramName) =>
                    `参数 ${paramName}：${info.join('<br />')}`).join('<br />');
            } else {
                error += json.errorInfo;
            }
            throw Error(error);
        }
        return json;
    } catch (e: unknown) {
        if (e instanceof Error) {
            const { message } = e;
            new Noty({ timeout: 3000, type: 'error', text: message }).show();
            return { errorCode: 0, errorInfo: message };
        }
        throw e;
    } finally {
        NProgress.done();
        document.body.style.cursor = '';
    }
};
const reCAPTCHACheck = async (action = ''): Promise<Record<never, never> | { reCAPTCHA: string }> => new Promise((reslove, reject) => {
    if (process.env.NODE_ENV === 'production') {
        grecaptcha.ready(() => {
            grecaptcha.execute(process.env.VUE_APP_RECAPTCHA_SITE_KEY, { action }).then(
                token => {
                    reslove(token);
                }, (...args) => {
                    reject(new Error(JSON.stringify(args)));
                }
            );
        });
    } else {
        reslove({});
    }
});
const getRequesterWithReCAPTCHA = async <T = ApiError | unknown>(endpoint: string, queryString?: Record<string, unknown>, action = '') =>
    getRequester<T>(endpoint, { ...queryString, ...await reCAPTCHACheck(action) });

export const apiStatus = async (statusQuery: ApiQSStatus): Promise<ApiError | ApiStatus> => getRequesterWithReCAPTCHA<ApiStatus>('/status', statusQuery);
