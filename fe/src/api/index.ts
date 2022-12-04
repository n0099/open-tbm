import type { ApiError, ApiForumList, ApiPostsQuery, ApiPostsQueryQP, ApiStatsForumPostCount, ApiStatsForumPostCountQP, ApiStatus, ApiStatusQP, ApiUsersQuery, ApiUsersQueryQP } from '@/api/index.d';
import { notyShow } from '@/shared';
import NProgress from 'nprogress';
import qs from 'qs';
import _ from 'lodash';

export const isApiError = <T>(r: ApiError | T): r is ApiError => 'errorCode' in r && 'errorInfo' in r;
export const throwIfApiError = <T>(api: ApiError | T): T => {
    if (isApiError(api)) throw Error(JSON.stringify(api));
    return api;
};
export const getRequester = async <T extends ApiError | unknown, Q>
(endpoint: string, queryString?: Q & { reCAPTCHA?: string }): Promise<ApiError | T> => {
    NProgress.start();
    document.body.style.cursor = 'progress';
    let errorCode = 0;
    let errorMessage = `GET ${endpoint}<br />`;
    try {
        const response = await fetch(
            process.env.VUE_APP_API_URL_PREFIX + endpoint + (_.isEmpty(queryString) ? '' : '?') + qs.stringify(queryString),
            { headers: { Accept: 'application/json' } }
        );
        errorCode = response.status;
        errorMessage += `HTTP ${response.status} `;
        const json = await response.json() as T;
        if (isApiError(json)) {
            ({ errorCode } = json);
            errorMessage += `错误码：${json.errorCode}<br />`;
            if (_.isObject(json.errorInfo)) {
                errorMessage += _.map(json.errorInfo, (info, paramName) =>
                    `参数 ${paramName}：${info.join('<br />')}`).join('<br />');
            } else {
                errorMessage += json.errorInfo;
            }
            throw Error();
        }
        if (!response.ok) throw Error();
        return json;
    } catch (e: unknown) {
        if (e instanceof Error) {
            const { message: exceptionMessage } = e;
            const text = `${errorMessage}<br />${exceptionMessage}`;
            notyShow('error', text);
            return { errorCode, errorInfo: text.replaceAll('<br />', '\n') };
        }
        throw e;
    } finally {
        NProgress.done();
        document.body.style.cursor = '';
    }
};
const reCAPTCHACheck = async (action = ''): Promise<{ reCAPTCHA?: string }> => new Promise((reslove, reject) => {
    if (process.env.NODE_ENV === 'production') {
        grecaptcha.ready(() => {
            grecaptcha.execute(process.env.VUE_APP_RECAPTCHA_SITE_KEY, { action }).then(
                reCAPTCHA => {
                    reslove({ reCAPTCHA });
                }, (...args) => {
                    reject(new Error(JSON.stringify(args)));
                }
            );
        });
    } else {
        reslove({});
    }
});
export const getRequesterWithReCAPTCHA = async <T extends ApiError | unknown, Q>
(endpoint: string, queryString?: Q, action = '') =>
    getRequester<T, Q>(endpoint, { ...queryString, ...await reCAPTCHACheck(action) } as Q);

export const apiForumList = async (): Promise<ApiError | ApiForumList> =>
    getRequester('/forums');
export const apiStatus = async (qp: ApiStatusQP): Promise<ApiError | ApiStatus> =>
    getRequesterWithReCAPTCHA('/status', qp);
export const apiStatsForumsPostCount = async (qp: ApiStatsForumPostCountQP): Promise<ApiError | ApiStatsForumPostCount> =>
    getRequesterWithReCAPTCHA('/stats/forums/postCount', qp);
export const apiUsersQuery = async (qp: ApiUsersQueryQP): Promise<ApiError | ApiUsersQuery> =>
    getRequesterWithReCAPTCHA('/users/query', qp);
export const apiPostsQuery = async (qp: ApiPostsQueryQP): Promise<ApiError | ApiPostsQuery> =>
    getRequesterWithReCAPTCHA('/posts/query', qp);
