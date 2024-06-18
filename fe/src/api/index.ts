import type { Api, ApiError, ApiForums, ApiPosts, ApiUsers, Cursor, CursorPagination } from '@/api/index.d';
import type { Ref } from 'vue';
import type { InfiniteData, QueryKey, UseInfiniteQueryOptions, UseQueryOptions } from '@tanstack/vue-query';
import { useInfiniteQuery, useQuery } from '@tanstack/vue-query';
import nprogress from 'nprogress';
import { stringify } from 'qs';
import _ from 'lodash';

export class ApiResponseError extends Error {
    public constructor(
        public readonly errorCode: number,
        public readonly errorInfo: Record<string, unknown[]> | string
    ) {
        super(JSON.stringify({ errorCode, errorInfo }));
    }
}
export class FetchResponseError extends Error {
    public constructor(public readonly bodyText: string) {
        super(bodyText);
    }
}
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const isApiError = (response: ApiError | unknown): response is ApiError => _.isObject(response)
    && 'errorCode' in response && _.isNumber(response.errorCode)
    && 'errorInfo' in response && (_.isObject(response.errorInfo) || _.isString(response.errorInfo));
export const queryFunction = async <TResponse, TQueryParam>
(endpoint: string, queryParam?: TQueryParam, signal?: AbortSignal): Promise<TResponse> => {
    nprogress.start();
    document.body.style.cursor = 'progress';
    try {
        const response = await fetch(
            `${useRuntimeConfig().public.apiBaseURL}${endpoint}`
                + `${_.isEmpty(queryParam) ? '' : '?'}${stringify(queryParam)}`,
            { headers: { Accept: 'application/json' }, signal }
        );

        /** must be cloned before any {@link Response.text()} */
        // to prevent `Failed to execute 'clone' on 'Response': Response body is already used`
        const response2 = response.clone();
        const json = await response.json() as TResponse;
        if (isApiError(json))
            throw new ApiResponseError(json.errorCode, json.errorInfo);
        if (!response.ok)
            throw new FetchResponseError(await response2.text());

        return json;
    } finally {
        nprogress.done();
        document.body.style.cursor = '';
    }
};
const checkReCAPTCHA = async (action = '') =>
    new Promise<{ reCAPTCHA?: string }>((reslove, reject) => {
        const config = useRuntimeConfig().public;
        if (config.recaptchaSiteKey === '') {
            reslove({});
        } else {
            grecaptcha.ready(() => {
                grecaptcha.execute(config.recaptchaSiteKey, { action }).then(
                    reCAPTCHA => {
                        reslove({ reCAPTCHA });
                    }, (...args) => {
                        reject(new Error(JSON.stringify(args)));
                    }
                );
            });
        }
    });
const queryFunctionWithReCAPTCHA = async <TResponse, TQueryParam>
(endpoint: string, queryParam?: TQueryParam, signal?: AbortSignal, action = ''): Promise<TResponse> =>
    queryFunction<TResponse, TQueryParam & { reCAPTCHA?: string }>(
        endpoint,
        { ...queryParam as TQueryParam, ...await checkReCAPTCHA(action) },
        signal
    );

export type ApiErrorClass = ApiResponseError | FetchResponseError;
type QueryFunctions = typeof queryFunction | typeof queryFunctionWithReCAPTCHA;
const useApi = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse = TApi['response'],
    TQueryParam = TApi['queryParam']>
(endpoint: string, queryFn: QueryFunctions) =>
    (queryParam?: Ref<TQueryParam | undefined>, options?: Partial<UseQueryOptions<TResponse, ApiErrorClass>>) =>
        useQuery<TResponse, ApiErrorClass>({
            queryKey: [endpoint, queryParam],
            queryFn: async () => queryFn<TResponse, TQueryParam>(`/${endpoint}`, queryParam?.value),
            ...options
        });
const useApiWithCursor = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse = TApi['response'] & CursorPagination,
    TQueryParam = TApi['queryParam']>
(endpoint: string, queryFn: QueryFunctions) =>
    (queryParam?: Ref<TQueryParam | undefined>, options?: Partial<UseInfiniteQueryOptions<
        TResponse & CursorPagination, ApiErrorClass,
        InfiniteData<TResponse & CursorPagination, Cursor>,
        TResponse & CursorPagination,
        QueryKey, Cursor
    >>) =>
        useInfiniteQuery<
            TResponse & CursorPagination, ApiErrorClass,
            InfiniteData<TResponse & CursorPagination, Cursor>,
            QueryKey, Cursor
        >({
            queryKey: [endpoint, queryParam],
            queryFn: async ({ pageParam }) => queryFn<TResponse & CursorPagination, TQueryParam & { cursor?: Cursor }>(
                `/${endpoint}`,
                { ...queryParam?.value as TQueryParam, cursor: pageParam === '' ? undefined : pageParam }
            ),
            getNextPageParam: lastPage => lastPage.pages.nextCursor,
            initialPageParam: '',
            ...options
        });

export const useApiForums = () => useApi<ApiForums>('forums', queryFunction)();
export const useApiUsers = useApi<ApiUsers>('users', queryFunctionWithReCAPTCHA);
export const useApiPosts = useApiWithCursor<ApiPosts>('posts', queryFunctionWithReCAPTCHA);
