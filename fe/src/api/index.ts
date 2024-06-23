import type { PublicRuntimeConfig } from 'nuxt/schema';
import type { InfiniteData, QueryKey, UseInfiniteQueryOptions, UseQueryOptions } from '@tanstack/vue-query';
import nprogress from 'nprogress';
import { FetchError } from 'ofetch';
import _ from 'lodash';

export class ApiResponseError extends Error {
    public constructor(
        public readonly errorCode: number,
        public readonly errorInfo: Record<string, unknown[]> | string
    ) {
        super(JSON.stringify({ errorCode, errorInfo }));
    }
}
// eslint-disable-next-line @typescript-eslint/no-redundant-type-constituents
export const isApiError = (response: ApiError | unknown): response is ApiError => _.isObject(response)
    && 'errorCode' in response && _.isNumber(response.errorCode)
    && 'errorInfo' in response && (_.isObject(response.errorInfo) || _.isString(response.errorInfo));
export const queryFunction = async <TResponse, TQueryParam extends ObjUnknown>
(
    config: PublicRuntimeConfig,
    requestHeaders: Record<string, string>,
    endpoint: string,
    queryParam?: TQueryParam,
    signal?: AbortSignal
): Promise<TResponse> => {
    if (import.meta.client) {
        nprogress.start();
        document.body.style.cursor = 'progress';
    }
    try {
        return await $fetch<TResponse>(
            `${config.apiEndpointPrefix}${endpoint}`,
            {
                query: queryParam,
                headers: {
                    Accept: 'application/json',
                    ...requestHeaders
                },
                signal
            }
        ) as TResponse;
    } catch (e: unknown) {
        if (e instanceof FetchError && isApiError(e.data))
            throw new ApiResponseError(e.data.errorCode, e.data.errorInfo);
        throw e;
    } finally {
        if (import.meta.client) {
            nprogress.done();
            document.body.style.cursor = '';
        }
    }
};
const checkReCAPTCHA = async (config: PublicRuntimeConfig, action = '') =>
    new Promise<{ reCAPTCHA?: string }>((reslove, reject) => {
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
const queryFunctionWithReCAPTCHA = async <TResponse, TQueryParam extends ObjUnknown>
(
    config: PublicRuntimeConfig,
    requestHeaders: Record<string, string>,
    endpoint: string,
    queryParam?: TQueryParam,
    signal?: AbortSignal,
    action = ''
): Promise<TResponse> =>
    queryFunction<TResponse, TQueryParam & { reCAPTCHA?: string }>(
        config,
        requestHeaders,
        endpoint,
        { ...queryParam, ...await checkReCAPTCHA(config, action) } as TQueryParam,
        signal
    );

export type ApiErrorClass = ApiResponseError | FetchError;
type QueryFunctions = typeof queryFunction | typeof queryFunctionWithReCAPTCHA;
const useApi = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse = TApi['response'],
    TQueryParam extends ObjUnknown = TApi['queryParam']>
(endpoint: string, queryFn: QueryFunctions) =>
    (queryParam?: Ref<TQueryParam | undefined>, options?: Partial<UseQueryOptions<TResponse, ApiErrorClass>>) => {
        const config = useRuntimeConfig().public;
        const clientRequestHeaders = useRequestHeaders(['Authorization']);
        const ret = useQuery<TResponse, ApiErrorClass>({
            queryKey: [endpoint, queryParam],
            queryFn: async () => queryFn<TResponse, TQueryParam>(
                config,
                clientRequestHeaders,
                `/${endpoint}`,
                queryParam?.value
            ),
            ...options
        });
        onServerPrefetch(ret.suspense);

        return ret;
    };
const useApiWithCursor = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse = TApi['response'] & CursorPagination,
    TQueryParam extends ObjUnknown = TApi['queryParam']>
(endpoint: string, queryFn: QueryFunctions) =>
    (queryParam?: Ref<TQueryParam | undefined>, options?: Partial<UseInfiniteQueryOptions<
        TResponse & CursorPagination, ApiErrorClass,
        InfiniteData<TResponse & CursorPagination, Cursor>,
        TResponse & CursorPagination,
        QueryKey, Cursor
    >>) => {
        const config = useRuntimeConfig().public;
        const clientRequestHeaders = useRequestHeaders(['Authorization']);
        type TQueryParamWithCursor = TQueryParam & { cursor?: Cursor };
        const ret = useInfiniteQuery<
            TResponse & CursorPagination, ApiErrorClass,
            InfiniteData<TResponse & CursorPagination, Cursor>,
            QueryKey, Cursor
        >({
            queryKey: [endpoint, queryParam],
            queryFn: async ({ pageParam }) =>
                queryFn<TResponse & CursorPagination, TQueryParamWithCursor>(
                    config,
                    clientRequestHeaders,
                    `/${endpoint}`,
                    { ...queryParam?.value, cursor: pageParam === '' ? undefined : pageParam } as TQueryParamWithCursor
                ),
            getNextPageParam: lastPage => lastPage.pages.nextCursor,
            initialPageParam: '',
            ...options
        });
        onServerPrefetch(ret.suspense);

        return ret;
    };

export const useApiForums = useApi<ApiForums>('forums', queryFunction);
export const useApiUsers = useApi<ApiUsers>('users', queryFunctionWithReCAPTCHA);
export const useApiPosts = useApiWithCursor<ApiPosts>('posts', queryFunctionWithReCAPTCHA);
