import type { ApiError, ApiForumList, ApiStatus, ApiStatusQP, ApiStats, ApiStatsQP } from '@/api.d';
import NProgress from 'nprogress';
import qs from 'qs';
import _ from 'lodash';
import Noty from 'noty';

// eslint-disable-next-line @typescript-eslint/explicit-module-boundary-types, @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access
export const isApiError = (r: any): r is ApiError => 'errorInfo' in r && typeof r.errorInfo === 'string';
export const nullIfApiError = <T>(api: ApiError | T): T | null => (isApiError(api) ? null : api);
const getRequester = async <T = ApiError | unknown>(endpoint: string, queryString?: Record<string, unknown>): Promise<ApiError | T> => {
    NProgress.start();
    document.body.style.cursor = 'progress';
    let errorMessage = `GET ${endpoint}<br />`;
    try {
        const response = await fetch(
            `${process.env.VUE_APP_API_URL_PREFIX}${endpoint}?${qs.stringify(queryString)}`,
            { headers: { Accept: 'application/json' } }
        );
        errorMessage += `HTTP ${response.status} `;
        const json = await response.json() as T;
        if (isApiError(json)) {
            errorMessage += `错误码：${json.errorCode}<br />`;
            if (_.isObject(json.errorInfo)) {
                errorMessage += _.map(json.errorInfo, (info, paramName) =>
                    `参数 ${paramName}：${info.join('<br />')}`).join('<br />');
            } else {
                errorMessage += json.errorInfo;
            }
            throw Error();
        }
        return json;
    } catch (e: unknown) {
        if (e instanceof Error) {
            const { message: exceptionMessage } = e;
            new Noty({ timeout: 3000, type: 'error', text: `${errorMessage}<br />${exceptionMessage}` }).show();
            return { errorCode: 0, errorInfo: exceptionMessage };
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

export const apiStatus = async (qp: ApiStatusQP): Promise<ApiError | ApiStatus> => getRequesterWithReCAPTCHA<ApiStatus>('/status', qp);
export const apiForumList = async (): Promise<ApiError | ApiForumList> => getRequester('/forumList');
export const apiStats = async (qp: ApiStatsQP): Promise<ApiError | ApiStats> => getRequesterWithReCAPTCHA<ApiStats>('/stats/forumPostsCount', qp);
