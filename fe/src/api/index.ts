import type { QueryFunctionContext } from '@tanstack/vue-query';
import type { ApiError, ApiForums, ApiPosts, ApiPostsParam, ApiStatsForumPostCount, ApiStatsForumPostCountQueryParam, ApiStatus, ApiStatusQueryParam, ApiUsers, ApiUsersParam } from '@/api/index.d';
import { useQuery } from '@tanstack/vue-query';
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
export const getRequester = <TResponse, TQueryParam>(endpoint: string, queryParam?: TQueryParam) =>
    async (queryContext: QueryFunctionContext): Promise<TResponse> => {
        nprogress.start();
        document.body.style.cursor = 'progress';
        try {
            const response = await fetch(
                `${import.meta.env.VITE_API_URL_PREFIX}${endpoint}`
                    + `${_.isEmpty(queryParam) ? '' : '?'}${stringify(queryParam)}`,
                { headers: { Accept: 'application/json' }, signal: queryContext.signal }
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
const getRequesterWithReCAPTCHA = <TResponse, TQueryParam>
(endpoint: string, queryParam?: TQueryParam, action = '') =>
    async (queryContext: QueryFunctionContext): Promise<TResponse> =>
        getRequester<TResponse, TQueryParam & { reCAPTCHA?: string }>(
            endpoint,
            { ...queryParam as TQueryParam, ...await reCAPTCHACheck(action) }
        )(queryContext);

type ApiErrorClass = ApiResponseError | FetchResponseError;
export const useApiForums = () => useQuery<ApiForums, ApiErrorClass>({
    queryKey: ['forums'],
    queryFn: getRequester<ApiForums, never>('/forums')
});
const useApiWithReCAPTCHA = <TApi, TApiQueryParam>(endpoint: string) => (queryParam: TApiQueryParam) =>
    useQuery<TApi, ApiErrorClass>({
        queryKey: [endpoint, queryParam],
        queryFn: getRequesterWithReCAPTCHA<TApi, TApiQueryParam>(`/${endpoint}`, queryParam)
    });
export const useApiStatus = useApiWithReCAPTCHA<ApiStatus, ApiStatusQueryParam>('status');
export const useApiStatsForumsPostCount = useApiWithReCAPTCHA<ApiStatsForumPostCount, ApiStatsForumPostCountQueryParam>('stats/forums/postCount');
export const useApiUsers = useApiWithReCAPTCHA<ApiUsers, ApiUsersParam>('users');
export const useApiPosts = useApiWithReCAPTCHA<ApiPosts, ApiPostsParam>('posts');
export const apiStatus = async (queryParam: ApiStatusQueryParam): Promise<ApiStatus> =>
    getRequesterWithReCAPTCHA('/status', queryParam);
export const apiStatsForumsPostCount = async (queryParam: ApiStatsForumPostCountQueryParam): Promise<ApiStatsForumPostCount> =>
    getRequesterWithReCAPTCHA('/stats/forums/postCount', queryParam);
export const apiUsers = async (queryParam: ApiUsersParam): Promise<ApiUsers> =>
    getRequesterWithReCAPTCHA('/users', queryParam);
export const apiPosts = async (queryParam: ApiPostsParam): Promise<ApiPosts> =>
    getRequesterWithReCAPTCHA('/posts', queryParam);
