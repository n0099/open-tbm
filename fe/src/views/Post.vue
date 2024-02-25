<template>
    <div class="container">
        <QueryForm ref="queryFormRef" :isLoading="isFetching" />
        <p>当前页数：{{ getRouteCursorParam(route) }}</p>
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
                          :renderType="renderType" :posts="page"
                          :currentRoute="lastFetchingRoute" :isLoadingNewPage="isFetching"
                          :isLastPageInPages="pageIndex === data.pages.length - 1" />
            </div>
            <div v-if="renderType === 'list'" class="col d-none d-xxl-block p-0" />
        </div>
    </div>
    <div class="container">
        <PlaceholderError :error="error" class="border-top" />
        <PlaceholderPostList :isLoading="isFetching" />
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
import { compareRouteIsNewQuery, getRouteCursorParam } from '@/router';
import type { ObjUnknown } from '@/shared';
import { notyShow, scrollBarWidth, titleTemplate } from '@/shared';
import { useTriggerRouteUpdateStore } from '@/stores/triggerRouteUpdate';

import { computed, nextTick, onMounted, ref, watch } from 'vue';
import type { RouteLocationNormalized } from 'vue-router';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import { watchOnce } from '@vueuse/core';
import { Menu, MenuItem } from 'ant-design-vue';
import { useHead } from '@unhead/vue';
import * as _ from 'lodash-es';

export type PostRenderer = 'list' | 'table';

const route = useRoute();
const queryParam = ref<ApiPosts['queryParam']>();
const shouldFetch = ref<boolean>(false);
const { data, error, isFetching, isFetchedAfterMount, dataUpdatedAt, errorUpdatedAt } = useApiPosts(queryParam, shouldFetch);
const selectedRenderTypes = ref<[PostRenderer]>(['list']);
const renderType = computed(() => selectedRenderTypes.value[0]);
const queryFormRef = ref<InstanceType<typeof QueryForm>>();
const lastFetchingRoute = ref<RouteLocationNormalized>(route);
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

const fetchPosts = (queryParams: ObjUnknown[], cursor: Cursor) => {
    const startTime = Date.now();
    queryParam.value = {
        query: JSON.stringify(queryParams),
        cursor: cursor === '' ? undefined : cursor
    };
    shouldFetch.value = true;
    watchOnce(isFetchedAfterMount, value => {
        if (value)
            shouldFetch.value = false;
    });
    watchOnce([dataUpdatedAt, errorUpdatedAt], async updatedAt => {
        const networkTime = (_.max(updatedAt) ?? 0) - startTime;
        await nextTick(); // wait for child components finish dom update
        const fetchedPage = data.value?.pages.find(i => i.pages.currentCursor === cursor);
        const postCount = _.sum(Object.values(fetchedPage?.pages.matchQueryPostCount ?? {}));
        const renderTime = (Date.now() - startTime - networkTime) / 1000;
        notyShow('success', `已加载${postCount}条记录
            前端耗时${renderTime.toFixed(2)}s
            后端+网络耗时${(networkTime / 1000).toFixed(2)}s`);
    });
};

watch(isFetchedAfterMount, async () => {
    if (isFetchedAfterMount.value && renderType.value === 'list') {
        await nextTick();
        scrollToPostListItemByRoute(lastFetchingRoute.value);
    }
});

const parseRouteThenFetch = async (newRoute: RouteLocationNormalized, cursor: Cursor) => {
    if (queryFormRef.value === undefined)
        return;
    const flattenParams = await queryFormRef.value.parseRouteToGetFlattenParams(newRoute);
    if (flattenParams === false)
        return;
    lastFetchingRoute.value = newRoute;
    fetchPosts(flattenParams, cursor);
};
onBeforeRouteUpdate(async (to, from) => {
    const isNewQuery = useTriggerRouteUpdateStore()
        .isTriggeredBy('<QueryForm>@submit', { ...to, force: true })
        || compareRouteIsNewQuery(to, from);
    const cursor = getRouteCursorParam(to);
    if (isNewQuery || _.isEmpty(_.filter(
        data.value?.pages,
        i => i.pages.currentCursor === cursor
    )))
        await parseRouteThenFetch(to, cursor);
});
onMounted(async () => {
    await parseRouteThenFetch(route, getRouteCursorParam(route));
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
