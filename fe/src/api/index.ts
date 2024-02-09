import type { ApiError, ApiForums, ApiPosts, ApiPostsParam, ApiStatsForumPostCount, ApiStatsForumPostCountQueryParam, ApiStatus, ApiStatusQueryParam, ApiUsers, ApiUsersParam } from '@/api/index.d';
import nprogress from 'nprogress';
import { stringify } from 'qs';
import * as _ from 'lodash-es';

class ApiResponseError extends Error {
    public constructor(
        public readonly errorCode: number,
        public readonly errorInfo: Record<string, unknown[]> | string
    ) {
        super(JSON.stringify({ errorCode, errorInfo }));
    }
}
class FetchResponseError extends Error {
    public constructor(public readonly response: Response) {
        super(JSON.stringify(response));
    }
}
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const isApiError = (response: ApiError | unknown): response is ApiError => _.isObject(response)
    && 'errorCode' in response && _.isNumber(response.errorCode)
    && 'errorInfo' in response && (_.isObject(response.errorInfo) || _.isString(response.errorInfo));
export const throwIfApiError = <TResponse>(response: ApiError | TResponse): TResponse => {
    if (isApiError(response))
        throw new Error(JSON.stringify(response));

    return response;
};
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const getRequester = async <TResponse extends ApiError | unknown, TQueryParam>
(endpoint: string, queryString?: TQueryParam): Promise<ApiError | TResponse> => {
    nprogress.start();
    document.body.style.cursor = 'progress';
    try {
        const response = await fetch(
            `${import.meta.env.VITE_API_URL_PREFIX}${endpoint}`
                + `${_.isEmpty(queryString) ? '' : '?'}${stringify(queryString)}`,
            { headers: { Accept: 'application/json' } }
        );
        const json = await response.json() as TResponse;
        if (!response.ok)
            throw new FetchResponseError(response);
        if (isApiError(json))
            throw new ApiResponseError(json.errorCode, json.errorInfo);

        return json;
    } finally {
        nprogress.done();
        document.body.style.cursor = '';
    }
};
const reCAPTCHACheck = async (action = '') =>
    new Promise<{ reCAPTCHA?: string }>((reslove, reject) => {
        if (import.meta.env.VITE_RECAPTCHA_SITE_KEY === '') {
            reslove({});
        } else {
            grecaptcha.ready(() => {
                grecaptcha.execute(import.meta.env.VITE_RECAPTCHA_SITE_KEY, { action }).then(
                    reCAPTCHA => {
                        reslove({ reCAPTCHA });
                    }, (...args) => {
                        reject(new Error(JSON.stringify(args)));
                    }
                );
            });
        }
    });
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
const getRequesterWithReCAPTCHA = async <TResponse extends ApiError | unknown, TQueryParam>
(endpoint: string, queryString?: TQueryParam, action = '') =>
    getRequester<TResponse, TQueryParam & { reCAPTCHA?: string }>(endpoint,
        { ...queryString as TQueryParam, ...await reCAPTCHACheck(action) });

export const apiForums = async (): Promise<ApiError | ApiForums> =>
    getRequester('/forums');
export const apiStatus = async (queryParam: ApiStatusQueryParam): Promise<ApiError | ApiStatus> =>
    getRequesterWithReCAPTCHA('/status', queryParam);
export const apiStatsForumsPostCount = async (queryParam: ApiStatsForumPostCountQueryParam): Promise<ApiError | ApiStatsForumPostCount> =>
    getRequesterWithReCAPTCHA('/stats/forums/postCount', queryParam);
export const apiUsers = async (queryParam: ApiUsersParam): Promise<ApiError | ApiUsers> =>
    getRequesterWithReCAPTCHA('/users', queryParam);
export const apiPosts = async (queryParam: ApiPostsParam): Promise<ApiError | ApiPosts> =>
    getRequesterWithReCAPTCHA('/posts', queryParam);
