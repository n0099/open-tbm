<template>
<div>
    <aside class="container">
        <PostQueryForm :isLoading="isFetching" :queryFormDeps="queryFormDeps" />
    </aside>
    <PostQueryPlanVisualizer v-if="data !== undefined" :data="data" class="border-bottom" />
    <aside class="container">
        <AMenu v-if="!_.isEmpty(data?.pages)" v-model:selectedKeys="selectedRenderTypes" mode="horizontal">
            <AMenuItem key="list">列表视图</AMenuItem>
            <AMenuItem key="table">表格视图</AMenuItem>
        </AMenu>
    </aside>
    <div v-if="!(data === undefined || _.isEmpty(data.pages))" class="container-fluid">
        <div class="row flex-nowrap">
            <PostNav v-if="renderType === 'list'" :queryParam="queryParam" />
            <div class="post-page col ps-0" :class="{ 'renderer-list': renderType === 'list' }">
                <PostPage
                    v-for="(page, pageIndex) in data.pages"
                    :key="`${queryParam?.query}/cursor/${page.pages.currentCursor}`"
                    @clickNextPage="fetchNextPage()"
                    :posts="page" :renderType="renderType"
                    :isFetching="isFetching" :hasNextPage="hasNextPage"
                    :isLastPageInPages="pageIndex === data.pages.length - 1" />
            </div>
            <div v-if="renderType === 'list'" class="col d-none d-xxl-block p-0" />
        </div>
    </div>
    <div class="container">
        <PlaceholderError :error="error" class="border-top" />
        <PlaceholderPostList v-show="isPending || isFetchingNextPage" :isLoading="isFetching" />
    </div>
</div>
</template>

<script setup lang="ts">
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import type { dehydrate } from '@tanstack/vue-query';
import _ from 'lodash';

export type PostRenderer = 'list' | 'table';

const route = useRoute();
const queryClient = useQueryClient();
const queryParam = ref<ApiPosts['queryParam']>();
const shouldFetch = ref(false);
const initialPageCursor = ref<Cursor>('');
const selectedRenderTypes = ref<[PostRenderer]>(['list']);
const renderType = computed(() => selectedRenderTypes.value[0]);
const triggerRouteUpdateStore = useTriggerRouteUpdateStore();
const queryFormDeps = getQueryFormDeps();
const { currentQueryType, parseRouteToGetFlattenParams } = queryFormDeps;
const { data, error, isPending, isFetching, isFetched, dataUpdatedAt, errorUpdatedAt, fetchNextPage, isFetchingNextPage, hasNextPage } =
    useApiPosts(queryParam, { initialPageParam: initialPageCursor, enabled: shouldFetch });
usePostsSEO(data, currentQueryType);

const queryStartedAtSSR = useState('postsQuerySSRStartTime', () => 0);
let queryStartedAt = 0;
watchSyncEffect(() => {
    if (!isFetching.value)
        return;
    if (import.meta.server)
        queryStartedAtSSR.value = Date.now();
    if (import.meta.client)
        queryStartedAt = Date.now();
});
watch([dataUpdatedAt, errorUpdatedAt], async (updatedAt: UnixTimestamp[]) => {
    const maxUpdatedAt = Math.max(...updatedAt);
    if (maxUpdatedAt === 0) // just starts to fetch, defer watching to next time
        return;
    const isQueriedBySSR = queryStartedAtSSR.value !== 0 && queryStartedAt === 0;
    if (isQueriedBySSR)
        queryStartedAt = queryStartedAtSSR.value;
    const isQueryCached = maxUpdatedAt < queryStartedAt;
    const networkDuration = isQueryCached ? 0 : maxUpdatedAt - queryStartedAt;
    await nextTick(); // wait for child components to finish dom update
    const renderDuration = Date.now() - queryStartedAt - networkDuration;

    const fetchedPage = data.value?.pages.find(i =>
        i.pages.currentCursor === getRouteCursorParam(route));
    const postCount = _.sum(Object.values(fetchedPage?.pages.matchQueryPostCount ?? {}));
    notyShow('success', `已加载${postCount}条记录
        ${isQueryCached
            ? '使用前端本地缓存'
            : `
        前端耗时${_.round(renderDuration / 1000, 2)}s
        ${isQueriedBySSR ? '使用服务端渲染预请求' : ''}
        后端+网络耗时${_.round(networkDuration / 1000, 2)}s`}`);
});
whenever(isFetched, async () => {
    if (renderType.value === 'list') {
        await nextTick();
        scrollToPostListItemByRoute(route);
    }
});
if (import.meta.server) {
    const nuxt = useNuxtApp();
    watchOnce(error, () => {
        void nuxt.runWithContext(() => { responseWithError(error.value) });
    }, { flush: 'sync' });
}

const parseRouteThenFetch = (newRoute: RouteLocationNormalizedLoaded, isTriggeredByQueryForm: boolean) => {
    const setQueryParam = (newQueryParam?: ApiPosts['queryParam']) => {
        queryParam.value = newQueryParam;
        const dehydratedVueQuery = useState('vue-query-nuxt').value as ReturnType<typeof dehydrate> | undefined;
        const postQuery = dehydratedVueQuery?.queries.find(query => _.isEqual(query.queryKey, [apiPostsEndpoint, newQueryParam]));
        if (isTriggeredByQueryForm || postQuery?.state.status !== 'error')
            shouldFetch.value = newQueryParam !== undefined;
    };
    const flattenParams = parseRouteToGetFlattenParams(newRoute);
    if (flattenParams === false) {
        setQueryParam();

        return;
    }

    /** {@link initialPageCursor} only take effect when the queryKey of {@link useQuery()} is changed */
    initialPageCursor.value = getRouteCursorParam(newRoute);
    setQueryParam({ query: JSON.stringify(flattenParams) });
};

/** {@link onBeforeRouteUpdate()} fires too early for allowing navigation guard to cancel updating */
// but we don't need cancelling and there's no onAfterRoute*() events instead of navigation guard available
/** watch on {@link useRoute()} directly won't get reactive since it's not returning {@link ref} but a plain {@link Proxy} */
// https://old.reddit.com/r/Nuxt/comments/15bwb24/how_to_watch_for_route_change_works_on_dev_not/jtspj6b/
/** watch deeply on object {@link route.query} and {@link route.params} for nesting params */
/** and allowing reconstruct partial route to pass it as a param of {@link compareRouteIsNewQuery()} */
/** ignoring string {@link route.name} or {@link route.path} since switching root level route */
// will unmounted the component of current page route and unwatch this watcher
watchDeep(() => [route.query, route.params], async (_discard, [oldQuery, oldParams]) => {
    const to = route;
    const from = { query: oldQuery, params: oldParams } as RouteLocationNormalizedLoaded;
    const isTriggeredByQueryForm = triggerRouteUpdateStore
        .isTriggeredBy('<PostQueryForm>@submit', Object.assign(to, { force: true }));
    const isNewQuery = compareRouteIsNewQuery(to, from);
    if (to.hash === '' && (isTriggeredByQueryForm || isNewQuery))
        void nextTick(() => { window.scrollTo({ top: 0 }) });
    if (isTriggeredByQueryForm && !isNewQuery)
        await queryClient.resetQueries({ queryKey: ['posts'] });
    if (isTriggeredByQueryForm || isNewQuery)
        parseRouteThenFetch(to, isTriggeredByQueryForm);
});
await nextTick(); // prevent hydartion mismatch due to race condition
parseRouteThenFetch(route, false);
</script>

<style scoped>
.post-page {
    /* minus the inline-size of .post-nav-expand in <PostNav> to prevent overflow */
    inline-size: calc(100% - v-bind(scrollBarWidth));
}
@media (width <= 575.98px) {
    .post-page {
        padding-inline-end: 0;
    }
}
@media (width >= 1250px) {
    .renderer-list {
        flex: 1 0 auto;
        max-inline-size: 1000px;
    }
}
</style>
