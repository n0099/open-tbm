<template>
    <div class="container">
        <QueryForm ref="queryFormRef" :isLoading="isFetching" />
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
</template>

<script setup lang="ts">
import PostNav from '@/components/Post/PostNav.vue';
import PostPage from '@/components/Post/PostPage.vue';
import QueryForm from '@/components/Post/queryForm/QueryForm.vue';
import PlaceholderError from '@/components/placeholders/PlaceholderError.vue';
import PlaceholderPostList from '@/components/placeholders/PlaceholderPostList.vue';

import { useApiPosts } from '@/api';
import type { ApiPosts, Cursor } from '@/api/index.d';
import { scrollToPostListItemByRoute } from '@/components/Post/renderers/list/index';
import { compareRouteIsNewQuery, getNextCursorRoute, getRouteCursorParam } from '@/router';
import type { UnixTimestamp } from '@/shared';
import { notyShow, scrollBarWidth, titleTemplate } from '@/shared';
import { useTriggerRouteUpdateStore } from '@/stores/triggerRouteUpdate';

import { computed, nextTick, onMounted, ref, watch } from 'vue';
import type { RouteLocationNormalized } from 'vue-router';
import { onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router';
import { Menu, MenuItem } from 'ant-design-vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useHead } from '@unhead/vue';
import * as _ from 'lodash-es';

export type PostRenderer = 'list' | 'table';

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const queryParam = ref<ApiPosts['queryParam']>();
const shouldFetch = ref<boolean>(false);
const initialPageCursor = ref<Cursor>('');
const { data, error, isPending, isFetching, isFetchedAfterMount, dataUpdatedAt, errorUpdatedAt, fetchNextPage, isFetchingNextPage, hasNextPage } =
    useApiPosts(queryParam, { initialPageParam: initialPageCursor, enabled: shouldFetch });
const selectedRenderTypes = ref<[PostRenderer]>(['list']);
const renderType = computed(() => selectedRenderTypes.value[0]);
const queryFormRef = ref<InstanceType<typeof QueryForm>>();
useHead({
    title: computed(() => titleTemplate((() => {
        const firstPostPage = data.value?.pages[0];
        if (firstPostPage === undefined)
            return '帖子查询';

        const forumName = `${firstPostPage.forum.name}吧`;
        const threadTitle = firstPostPage.threads[0].title;
        // eslint-disable-next-line @typescript-eslint/switch-exhaustiveness-check
        switch (queryFormRef.value?.getCurrentQueryType()) {
            case 'fid':
            case 'search':
                return `${forumName} - 帖子查询`;
            case 'postID':
                return `${threadTitle} - ${forumName} - 帖子查询`;
        }

        return '帖子查询';
    })()))
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
    const fetchedPage = data.value?.pages.find(i => i.pages.currentCursor === getRouteCursorParam(route));
    const postCount = _.sum(Object.values(fetchedPage?.pages.matchQueryPostCount ?? {}));
    const renderTime = (Date.now() - startTime - networkTime) / 1000;
    notyShow('success', `已加载${postCount}条记录
        前端耗时${renderTime.toFixed(2)}s
        ${isCached ? '使用本地缓存' : `后端+网络耗时${_.round(networkTime / 1000, 2)}s`}`);
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
    if (queryFormRef.value === undefined)
        return;
    const flattenParams = await queryFormRef.value.parseRouteToGetFlattenParams(newRoute);
    if (flattenParams === false)
        return;

    /** {@link initialPageCursor} only take effect when the queryKey of {@link useQuery()} is changed */
    initialPageCursor.value = getRouteCursorParam(newRoute);
    queryParam.value = { query: JSON.stringify(flattenParams) };
};

onBeforeRouteUpdate(async (to, from) => {
    const isTriggeredByQueryForm = useTriggerRouteUpdateStore()
        .isTriggeredBy('<QueryForm>@submit', { ...to, force: true });
    if (isTriggeredByQueryForm || compareRouteIsNewQuery(to, from))
        window.scrollTo({ top: 0 });
    await parseRouteThenFetch(to);

    /** must invoke {@link parseRouteThenFetch()} before {@link queryClient.resetQueries()} */
    /** to prevent refetch the old route when navigating to different route aka {@link compareRouteIsNewQuery()} is true */
    if (isTriggeredByQueryForm)
        await queryClient.resetQueries({ queryKey: ['posts'] });
});
onMounted(async () => {
    await parseRouteThenFetch(route);
    shouldFetch.value = true; // prevent eager fetching with the initial empty queryParam
});
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
