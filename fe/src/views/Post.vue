<template>
    <div class="container">
        <QueryForm ref="queryFormRef" :forumList="forumList" :isLoading="isLoading" />
        <p>当前页数：{{ routePageParamNullSafe($route) }}</p>
        <Menu v-show="postPages.length !== 0" v-model:selectedKeys="selectedRenderTypes" mode="horizontal">
            <MenuItem key="list">列表视图</MenuItem>
            <MenuItem key="table">表格视图</MenuItem>
        </Menu>
    </div>
    <div v-show="postPages.length !== 0" class="container-fluid">
        <div class="row justify-content-center">
            <NavSidebar v-if="renderType === 'list'" :postPages="postPages" />
            <div class="post-render-wrapper col" :class="{
                'post-render-list-wrapper': renderType === 'list',
                'col-xl-10': renderType === 'list'
            }">
                <PostViewPage v-for="(posts, pageIndex) in postPages" :key="posts.pages.currentPage"
                              :renderType="renderType" :posts="posts"
                              :isLoadingNewPage="isLoading"
                              :isLastPageInPages="pageIndex === postPages.length - 1" />
            </div>

            <div v-show="renderType === 'list'" class="post-render-list-right-padding col-xl d-none p-0" />
        </div>
    </div>
    <div class="container">
        <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" class="border-top" />
        <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
    </div>
</template>

<script lang="ts">
import { computed, nextTick, ref, watchEffect } from 'vue';

export const isRouteUpdateTriggeredBySubmitQueryForm = ref(false);
</script>

<script setup lang="ts">
import type { ApiError, ApiForumList, ApiPostsQuery, Cursor } from '@/api/index.d';
import { apiForumList, apiPostsQuery, isApiError, throwIfApiError } from '@/api';
import { NavSidebar, PlaceholderError, PlaceholderPostList, PostViewPage, QueryForm } from '@/components/Post/exports.vue';
import { postListItemScrollPosition } from '@/components/Post/ViewList.vue';
import { compareRouteIsNewQuery, routePageParamNullSafe } from '@/router';
import { lazyLoadUpdate } from '@/shared/lazyLoad';
import type { ObjUnknown } from '@/shared';
import { notyShow, titleTemplate } from '@/shared';

import type { RouteLocationNormalized } from 'vue-router';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import { useHead } from '@vueuse/head';
import { Menu, MenuItem } from 'ant-design-vue';
import _ from 'lodash';

export type PostViewRenderer = 'list' | 'table';

const route = useRoute();
const title = ref<string>('帖子查询');
const forumList = ref<ApiForumList>([]);
const postPages = ref<ApiPostsQuery[]>([]);
const isLoading = ref<boolean>(false);
const lastFetchError = ref<ApiError | null>(null);
const showPlaceholderPostList = ref<boolean>(true);
const renderType = ref<PostViewRenderer>('list');
const selectedRenderTypes = ref<[PostViewRenderer]>(['list']);
const queryFormRef = ref<typeof QueryForm>();
useHead({ title: computed(() => titleTemplate(title.value)) });

const fetchPosts = async (queryParams: ObjUnknown[], isNewQuery: boolean, cursor: Cursor) => {
    const startTime = Date.now();
    lastFetchError.value = null;
    showPlaceholderPostList.value = true;
    if (isNewQuery) postPages.value = [];
    isLoading.value = true;

    const postsQuery = await apiPostsQuery({
        query: JSON.stringify(queryParams),
        cursor: isNewQuery ? undefined : cursor
    }).finally(() => {
        showPlaceholderPostList.value = false;
        isLoading.value = false;
    });

    if (isApiError(postsQuery)) {
        lastFetchError.value = postsQuery;
        return false;
    }
    if (isNewQuery) postPages.value = [postsQuery];
    else postPages.value.push(postsQuery); // todo: unshift when fetching previous page

    const forumName = `${postPages.value[0].forum.name}吧`;
    const threadTitle = postPages.value[0].threads[0].title;
    switch (queryFormRef.value?.getCurrentQueryType()) {
        case 'fid':
        case 'search':
            title.value = `${forumName} - 帖子查询`;
            break;
        case 'postID':
            title.value = `${threadTitle} - ${forumName} - 帖子查询`;
            break;
    }

    const networkTime = Date.now() - startTime;
    await nextTick(); // wait for child components finish dom update
    const postCount = _.sum(Object.values(postsQuery.pages.matchQueryPostCount));
    const renderTime = ((Date.now() - startTime - networkTime) / 1000).toFixed(2);
    notyShow('success', `已加载${postCount}条记录 前端耗时${renderTime}s 后端/网络耗时${networkTime}ms`);
    lazyLoadUpdate();
    return true;
};
const parseRouteThenFetch = async (_route: RouteLocationNormalized, isNewQuery: boolean, cursor: Cursor) => {
    if (queryFormRef.value === undefined) return false;
    const flattenParams = queryFormRef.value.parseRouteToGetFlattenParams(_route);
    if (flattenParams === false) return false;
    // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
    const isFetchSuccess = await fetchPosts(flattenParams, isNewQuery, cursor);
    if (isFetchSuccess && renderType.value === 'list') {
        const scrollPosition = postListItemScrollPosition(_route);
        const el = document.querySelector(scrollPosition.el);
        if (el === null) return isFetchSuccess;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
        window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY + scrollPosition.top);
    }
    return isFetchSuccess;
};

onBeforeRouteUpdate(async (to, from) => {
    const isNewQuery = isRouteUpdateTriggeredBySubmitQueryForm.value || compareRouteIsNewQuery(to, from);
    isRouteUpdateTriggeredBySubmitQueryForm.value = false;
    const page = routePageParamNullSafe(to);
    if (!isNewQuery && !_.isEmpty(_.filter(
        postPages.value,
        i => i.pages.currentPage === page
    ))) return true;
    const isFetchSuccess = await parseRouteThenFetch(to, isNewQuery, page);
    return isNewQuery ? true : isFetchSuccess; // only pass pending route update after successful fetched
});
watchEffect(() => {
    [renderType.value] = selectedRenderTypes.value;
});

(async () => {
    forumList.value = throwIfApiError(await apiForumList());
    parseRouteThenFetch(route, true, routePageParamNullSafe(route));
})();
</script>

<style scoped>
.post-render-wrapper {
    padding-left: 10px;
}
@media (max-width: 1200px) {
    .post-render-wrapper {
        /* minus the width of .posts-nav-expanded in <NavSidebar> to prevent it warps new row */
        width: calc(100% - 15px);
    }
}
@media (min-width: 1200px) {
    .post-render-wrapper {
        padding-left: 15px;
    }
    .post-render-list-wrapper {
        max-width: 1000px;
    }
}
@media (min-width: 1400px) {
    .post-render-list-right-padding {
        /* only show right margin spaces when enough to prevent too narrow to display <posts-nav> */
        display: block !important;
    }
}
</style>
