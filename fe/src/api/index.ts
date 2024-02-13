import type { Api, ApiError, ApiForums, ApiPosts, ApiStatsForumPostCount, ApiStatus, ApiUsers, Cursor, CursorPagination } from '@/api/index.d';
import type { InfiniteData, QueryFunctionContext, QueryKey } from '@tanstack/vue-query';
import { useInfiniteQuery, useQuery } from '@tanstack/vue-query';
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
type ReqesuterGetter = typeof getRequester | typeof getRequesterWithReCAPTCHA;
const useApi = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse = TApi['response'],
    TQueryParam = TApi['queryParam']>
(endpoint: string, requesterGetter: ReqesuterGetter) => (queryParam?: TQueryParam) =>
    useQuery<TResponse, ApiErrorClass, TResponse>({
        queryKey: [endpoint, queryParam],
        queryFn: requesterGetter<TResponse, TQueryParam>(`/${endpoint}`, queryParam)
    });
const useApiWithCursor = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse = TApi['response'] & CursorPagination,
    TQueryParam = TApi['queryParam']>
(endpoint: string, requesterGetter: ReqesuterGetter) => (queryParam?: TQueryParam) =>
    useInfiniteQuery<TResponse & CursorPagination, ApiErrorClass, InfiniteData<TResponse & CursorPagination, Cursor>, QueryKey, Cursor>({
        queryKey: [endpoint, queryParam],
        queryFn: requesterGetter<TResponse & CursorPagination, TQueryParam>(`/${endpoint}`, queryParam),
        initialPageParam: '',
        getNextPageParam: lastPage => lastPage.pages.nextCursor
    });

export const useApiForums = () => useApi<ApiForums>('forums', getRequester)();
export const useApiStatus = useApi<ApiStatus>('status', getRequesterWithReCAPTCHA);
export const useApiStatsForumsPostCount = useApi<ApiStatsForumPostCount>('stats/forums/postCount', getRequesterWithReCAPTCHA);
export const useApiUsers = useApi<ApiUsers>('users', getRequesterWithReCAPTCHA);
export const useApiPosts = useApiWithCursor<ApiPosts>('posts', getRequesterWithReCAPTCHA);
