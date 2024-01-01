import type { ApiError, ApiForumList, ApiPosts, ApiPostsParam, ApiStatsForumPostCount, ApiStatsForumPostCountQueryParam, ApiStatus, ApiStatusQueryParam, ApiUsers, ApiUsersParam } from '@/api/index.d';
import { notyShow } from '@/shared';
import nprogress from 'nprogress';
import { stringify } from 'qs';
import * as _ from 'lodash';

// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const isApiError = (response: ApiError | unknown): response is ApiError =>
    _.isObject(response) && 'errorCode' in response && 'errorInfo' in response;
export const throwIfApiError = <TResponse>(response: ApiError | TResponse): TResponse => {
    if (isApiError(response))
        throw new Error(JSON.stringify(response));

    return response;
};
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const getRequester = async <TResponse extends ApiError | unknown, TQueryParam>
(endpoint: string, queryString?: TQueryParam & { reCAPTCHA?: string }): Promise<ApiError | TResponse> => {
    nprogress.start();
    document.body.style.cursor = 'progress';
    let errorCode = 0;
    let errorMessage = `GET ${endpoint}<br />`;
    const errorUUID = crypto.randomUUID();
    try {
        const response = await fetch(
            `${import.meta.env.VITE_API_URL_PREFIX}${endpoint}`
                + `${_.isEmpty(queryString) ? '' : '?'}${stringify(queryString)}`,
            { headers: { Accept: 'application/json' } }
        );
        errorCode = response.status;
        errorMessage += `HTTP ${response.status} `;
        const json = await response.json() as TResponse;
        if (isApiError(json)) {
            ({ errorCode } = json);
            errorMessage += `错误码：${json.errorCode}<br />`;
            errorMessage += _.isObject(json.errorInfo)
                ? _.map(json.errorInfo, (info, paramName) =>
                    `参数 ${paramName}：${info.join('<br />')}`).join('<br />')
                : json.errorInfo;
            throw new Error(errorUUID);
        }
        if (!response.ok)
            throw new Error(errorUUID);

        return json;
    } catch (e: unknown) {
        if (e instanceof Error && e.message === errorUUID) {
            const { message: exceptionMessage } = e;
            const text = `${errorMessage}<br />${exceptionMessage}`;
            notyShow('error', text);

            return { errorCode, errorInfo: text.replaceAll('<br />', '\n') };
        }
        throw e;
    } finally {
        nprogress.done();
        document.body.style.cursor = '';
    }
};
const reCAPTCHACheck = async (action = '')
: Promise<{ reCAPTCHA?: string }> => new Promise((reslove, reject) => {
    if (import.meta.env.PROD) {
        grecaptcha.ready(() => {
            grecaptcha.execute(import.meta.env.VITE_RECAPTCHA_SITE_KEY, { action }).then(
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
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const getRequesterWithReCAPTCHA = async <TResponse extends ApiError | unknown, TQueryParam>
(endpoint: string, queryString?: TQueryParam, action = '') =>
    getRequester<TResponse, TQueryParam>(endpoint,
        { ...queryString, ...await reCAPTCHACheck(action) } as TQueryParam & { reCAPTCHA?: string });

export const apiForumList = async (): Promise<ApiError | ApiForumList> =>
    getRequester('/forums');
export const apiStatus = async (queryParam: ApiStatusQueryParam): Promise<ApiError | ApiStatus> =>
    getRequesterWithReCAPTCHA('/status', queryParam);
export const apiStatsForumsPostCount = async (queryParam: ApiStatsForumPostCountQueryParam): Promise<ApiError | ApiStatsForumPostCount> =>
    getRequesterWithReCAPTCHA('/stats/forums/postCount', queryParam);
export const apiUsers = async (queryParam: ApiUsersParam): Promise<ApiError | ApiUsers> =>
    getRequesterWithReCAPTCHA('/users', queryParam);
export const apiPosts = async (queryParam: ApiPostsParam): Promise<ApiError | ApiPosts> =>
    getRequesterWithReCAPTCHA('/posts', queryParam);
