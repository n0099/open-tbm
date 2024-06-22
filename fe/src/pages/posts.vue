<template>
    <div>
        <div class="container">
            <QueryForm :isLoading="isFetching" :queryFormDeps="queryFormDeps" />
            <Menu v-show="!_.isEmpty(data?.pages)" v-model:selectedKeys="selectedRenderTypes" mode="horizontal">
                <MenuItem key="list">列表视图</MenuItem>
                <MenuItem key="table">表格视图</MenuItem>
            </Menu>
        </div>
        <div v-if="!(data === undefined || _.isEmpty(data.pages))" class="container-fluid">
            <div class="row flex-nowrap">
                <PostNav v-if="renderType === 'list'" :postPages="data.pages" />
                <div class="post-page col mx-auto ps-0" :class="{ 'renderer-list': renderType === 'list' }">
                    <PostPage v-for="(page, pageIndex) in data.pages" :key="page.pages.currentCursor"
                              @clickNextPage="() => clickNextPage()" :posts="page" :renderType="renderType"
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
import { useApiPosts } from '@/api';
import type { ApiPosts, Cursor } from '@/api/index.d';
import type { UnixTimestamp } from '@/utils';
import { getQueryFormDeps } from '@/utils/post/queryForm';

import type { RouteLocationNormalized } from 'vue-router';
import { Menu, MenuItem } from 'ant-design-vue';
import _ from 'lodash';

export type PostRenderer = 'list' | 'table';

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const queryParam = ref<ApiPosts['queryParam']>();
const shouldFetch = ref(false);
const isRouteNewQuery = ref(false);
const initialPageCursor = ref<Cursor>('');
const { data, error, isPending, isFetching, isFetchedAfterMount, dataUpdatedAt, errorUpdatedAt, fetchNextPage, isFetchingNextPage, hasNextPage } =
    useApiPosts(queryParam, { initialPageParam: initialPageCursor });
const selectedRenderTypes = ref<[PostRenderer]>(['list']);
const renderType = computed(() => selectedRenderTypes.value[0]);
const queryFormDeps = getQueryFormDeps();
const { getCurrentQueryType, parseRouteToGetFlattenParams } = queryFormDeps;

useHead({
    title: computed(() => {
        const firstPostPage = data.value?.pages[0];
        if (firstPostPage === undefined)
            return '帖子查询';

        const forumName = `${firstPostPage.forum.name}吧`;
        const threadTitle = firstPostPage.threads[0].title;

        switch (getCurrentQueryType()) {
            case 'fid':
            case 'search':
                return `${forumName} - 帖子查询`;
            case 'postID':
                return `${threadTitle} - ${forumName} - 帖子查询`;
        }

        return '帖子查询';
    })
});

let startTime = 0;
watch(isFetching, () => {
    if (isFetching.value)
        startTime = Date.now();
});
watch([dataUpdatedAt, errorUpdatedAt], async (updatedAt: UnixTimestamp[]) => {
    const maxUpdatedAt = Math.max(...updatedAt);
    if (maxUpdatedAt === 0) // just starts to fetch, defer watching to next time
        return;
    const isCached = maxUpdatedAt < startTime;
    const networkTime = isCached ? 0 : maxUpdatedAt - startTime;
    await nextTick(); // wait for child components to finish dom update
    if (isRouteNewQuery.value)
        window.scrollTo({ top: 0 });
    const fetchedPage = data.value?.pages.find(i => i.pages.currentCursor === getRouteCursorParam(route));
    const postCount = _.sum(Object.values(fetchedPage?.pages.matchQueryPostCount ?? {}));
    const renderTime = (Date.now() - startTime - networkTime) / 1000;
    notyShow('success', `已加载${postCount}条记录
        前端耗时${renderTime.toFixed(2)}s
        ${isCached ? '使用前端本地缓存' : `后端+网络耗时${_.round(networkTime / 1000, 2)}s`}`);
});
watch(isFetchedAfterMount, async () => {
    if (isFetchedAfterMount.value && renderType.value === 'list') {
        await nextTick();
        scrollToPostListItemByRoute(route);
    }
});

const clickNextPage = async () => {
    await router.push(getNextCursorRoute(route, data.value?.pages.at(-1)?.pages.nextCursor));
    await fetchNextPage();
};
const parseRouteThenFetch = async (newRoute: RouteLocationNormalized) => {
    const setQueryParam = (newQueryParam?: ApiPosts['queryParam']) => {
        // prevent fetch with queryParam that's empty or parsed from invalid route
        shouldFetch.value = newQueryParam !== undefined;
        queryParam.value = newQueryParam;
    };
    const flattenParams = await parseRouteToGetFlattenParams(newRoute);
    if (flattenParams === false) {
        setQueryParam();

        return;
    }

    /** {@link initialPageCursor} only take effect when the queryKey of {@link useQuery()} is changed */
    initialPageCursor.value = getRouteCursorParam(newRoute);
    setQueryParam({ query: JSON.stringify(flattenParams) });
};

onBeforeRouteUpdate(async (to, from) => {
    const isTriggeredByQueryForm = useTriggerRouteUpdateStore()
        .isTriggeredBy('<QueryForm>@submit', { ...to, force: true });
    isRouteNewQuery.value = to.hash === ''
        && (isTriggeredByQueryForm || compareRouteIsNewQuery(to, from));
    await parseRouteThenFetch(to);

    /** must invoke {@link parseRouteThenFetch()} before {@link queryClient.resetQueries()} */
    /** to prevent refetch the old route when navigating to different route aka {@link compareRouteIsNewQuery()} is true */
    if (isTriggeredByQueryForm && !compareRouteIsNewQuery(to, from))
        await queryClient.resetQueries({ queryKey: ['posts'] });
});
await parseRouteThenFetch(route);
</script>

<style scoped>
.post-page {
    /* minus the inline-size of .post-nav-expand in <PostNav> to prevent overflow */
    inline-size: calc(100% - v-bind(scrollBarWidth));
}
@media (max-width: 575.98px) {
    .post-page {
        padding-inline-end: 0;
    }
}
@media (min-width: 1250px) {
    .renderer-list {
        flex: 1 0 auto;
        max-inline-size: 1000px;
    }
}
</style>
