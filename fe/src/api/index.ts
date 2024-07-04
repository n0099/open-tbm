import type { PublicRuntimeConfig } from 'nuxt/schema';
import type { Enabled, InfiniteData, Query, UseInfiniteQueryOptions, UseQueryOptions } from '@tanstack/vue-query';
import { QueryObserver } from '@tanstack/vue-query';
import nProgress from 'nprogress';
import { FetchError } from 'ofetch';
import _ from 'lodash';

export class ApiResponseError extends Error {
    public constructor(
        public readonly errorCode: number,
        public readonly errorInfo: Record<string, unknown[]> | string,
        public readonly fetchError?: FetchError
    ) {
        super(JSON.stringify({ fetchError, errorCode, errorInfo }));
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
        nProgress.start();
        document.body.style.cursor = 'progress';
    }
    try {
        return await $fetch<TResponse>(
            `${config.beUrl}/api/${endpoint}`,
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
            throw new ApiResponseError(e.data.errorCode, e.data.errorInfo, e);
        throw e;
    } finally {
        if (import.meta.client) {
            nProgress.done();
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
    (
        queryParam?: Ref<TQueryParam | undefined>,
        options?: MaybeRef<Partial<Exclude<UseQueryOptions<TResponse, ApiErrorClass>, Ref>>>
    ) => {
        const config = useRuntimeConfig().public;
        const clientRequestHeaders = useRequestHeaders(['Authorization']);
        const ret = useQuery<TResponse, ApiErrorClass>({
            queryKey: [endpoint, queryParam],
            queryFn: async () => queryFn<TResponse, TQueryParam>(
                config,
                clientRequestHeaders,
                endpoint,
                queryParam?.value
            ),
            ...options
        });
        onServerPrefetch(async () => { // https://github.com/TanStack/query/issues/7609
            const enabled = unref(options)?.enabled;
            if ((_.isFunction(enabled)
                ? (enabled as Exclude<Enabled<TResponse, ApiErrorClass>, boolean>)(
                    new QueryObserver<TResponse, ApiErrorClass>(
                        useQueryClient(),
                        { queryKey: [endpoint, queryParam?.value] }
                    ).getCurrentQuery() as unknown as Query<TResponse, ApiErrorClass>
                )
                : unref(enabled) ?? true
            ) === true)
                await ret.suspense();
        });

        return ret;
    };
const useApiWithCursor = <
    TApi extends Api<TResponse, TQueryParam>,
    TResponse extends CursorPagination = TApi['response'],
    TQueryParam extends ObjUnknown = TApi['queryParam']>
(endpoint: string, queryFn: QueryFunctions) => {
    type Data = InfiniteData<TResponse, Cursor>;
    type QueryKey = [string, TQueryParam | undefined];
    type QueryOptions = UseInfiniteQueryOptions<TResponse, ApiErrorClass, Data, TResponse, QueryKey, Cursor>;

    return (
        queryParam?: Ref<TQueryParam | undefined>,
        options?: Partial<QueryOptions>
    ) => {
        const config = useRuntimeConfig().public;
        const clientRequestHeaders = useRequestHeaders(['Authorization']);
        type TQueryParamWithCursor = TQueryParam & { cursor?: Cursor };
        const ret = useInfiniteQuery<TResponse, ApiErrorClass, Data, QueryKey, Cursor>({
            queryKey: [endpoint, queryParam] as QueryOptions['queryKey'],
            queryFn: async ({ pageParam }) =>
                queryFn<TResponse, TQueryParamWithCursor>(
                    config,
                    clientRequestHeaders,
                    endpoint,
                    { ...queryParam?.value, cursor: pageParam === '' ? undefined : pageParam } as TQueryParamWithCursor
                ),
            getNextPageParam: lastPage => lastPage.pages.nextCursor,
            initialPageParam: '',
            ...options
        });
        onServerPrefetch(async () => { // https://github.com/TanStack/query/issues/7609
            const enabled = options?.enabled;
            if ((_.isFunction(enabled)
                ? (enabled as Exclude<Enabled<TResponse, ApiErrorClass, Data, QueryKey>, boolean>)(
                    new QueryObserver<TResponse, ApiErrorClass, Data, TResponse, QueryKey>(
                        useQueryClient(),
                        { queryKey: [endpoint, queryParam?.value] }
                    ).getCurrentQuery() as unknown as Query<TResponse, ApiErrorClass, Data, QueryKey>
                )
                : unref(enabled) ?? true
            ) === true)
                await ret.suspense();
        });

        return ret;
    };
};
export const useApiForums = useApi<ApiForums>('forums', queryFunction);
export const useApiUsers = useApi<ApiUsers>('users', queryFunctionWithReCAPTCHA);
export const useApiPosts = useApiWithCursor<ApiPosts>('posts', queryFunctionWithReCAPTCHA);
