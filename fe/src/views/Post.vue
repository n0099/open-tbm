<template>
    <div class="container">
        <QueryForm ref="queryFormRef" :forumList="forumList" :isLoading="isLoading" />
        <p>当前页数：{{ getRouteCursorParam($route) }}</p>
        <Menu v-show="!_.isEmpty(postPages)" v-model:selectedKeys="selectedRenderTypes" mode="horizontal">
            <MenuItem key="list">列表视图</MenuItem>
            <MenuItem key="table">表格视图</MenuItem>
        </Menu>
    </div>
    <div v-show="!_.isEmpty(postPages)" class="container-fluid">
        <div class="row flex-nowrap">
            <PostNav v-if="renderType === 'list'" :postPages="postPages" />
            <div class="post-page col mx-auto ps-0" :class="{ 'renderer-list': renderType === 'list' }">
                <PostPage v-for="(posts, pageIndex) in postPages" :key="posts.pages.currentCursor"
                          :renderType="renderType" :posts="posts"
                          :isLoadingNewPage="isLoading"
                          :isLastPageInPages="pageIndex === postPages.length - 1" />
            </div>
            <div v-if="renderType === 'list'" class="col d-none d-xxl-block p-0" />
        </div>
    </div>
    <div class="container">
        <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" class="border-top" />
        <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
    </div>
</template>

<script setup lang="ts">
import PostNav from '@/components/Post/PostNav.vue';
import PostPage from '@/components/Post/PostPage.vue';
import QueryForm from '@/components/Post/queryForm/QueryForm.vue';
import PlaceholderError from '@/components/placeholders/PlaceholderError.vue';
import PlaceholderPostList from '@/components/placeholders/PlaceholderPostList.vue';

import { apiForumList, apiPosts, isApiError, throwIfApiError } from '@/api';
import type { ApiError, ApiForumList, ApiPosts, Cursor } from '@/api/index.d';
import { getReplyTitleTopOffset, postListItemScrollPosition } from '@/components/Post/renderers/rendererList';
import { compareRouteIsNewQuery, getRouteCursorParam } from '@/router';
import type { ObjUnknown } from '@/shared';
import { notyShow, scrollBarWidth, titleTemplate } from '@/shared';
import { useTriggerRouteUpdateStore } from '@/stores/triggerRouteUpdate';

import { computed, nextTick, onBeforeMount, ref, watchEffect } from 'vue';
import type { RouteLocationNormalized } from 'vue-router';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import { Menu, MenuItem } from 'ant-design-vue';
import { useHead } from '@unhead/vue';
import * as _ from 'lodash';

export type PostRenderer = 'list' | 'table';

const route = useRoute();
const title = ref<string>('帖子查询');
const forumList = ref<ApiForumList>([]);
const postPages = ref<ApiPosts[]>([]);
const isLoading = ref<boolean>(false);
const lastFetchError = ref<ApiError | null>(null);
const showPlaceholderPostList = ref<boolean>(true);
const renderType = ref<PostRenderer>('list');
const selectedRenderTypes = ref<[PostRenderer]>(['list']);
const queryFormRef = ref<typeof QueryForm>();
useHead({ title: computed(() => titleTemplate(title.value)) });

const fetchPosts = async (queryParams: ObjUnknown[], isNewQuery: boolean, cursor: Cursor) => {
    const startTime = Date.now();
    lastFetchError.value = null;
    showPlaceholderPostList.value = true;
    if (isNewQuery)
        postPages.value = [];
    isLoading.value = true;

    const query = await apiPosts({
        query: JSON.stringify(queryParams),
        cursor: isNewQuery ? undefined : cursor
    }).finally(() => {
        showPlaceholderPostList.value = false;
        isLoading.value = false;
    });

    if (isApiError(query)) {
        lastFetchError.value = query;

        return false;
    }
    if (isNewQuery)
        postPages.value = [query];
    else
        postPages.value.push(query); // todo: unshift when fetching previous page

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
    const postCount = _.sum(Object.values(query.pages.matchQueryPostCount));
    const renderTime = ((Date.now() - startTime - networkTime) / 1000).toFixed(2);
    notyShow('success', `已加载${postCount}条记录 前端耗时${renderTime}s 后端/网络耗时${networkTime}ms`);

    return true;
};

const scrollToPostListItem = (el: Element) => {
    // simply invoke el.scrollIntoView() for only once will scroll the element to the top of the viewport
    // and then some other elements above it such as img[loading='lazy'] may change its box size
    // that would lead to reflow resulting in the element being pushed down or up out of viewport
    // due to document.scrollingElement.scrollTop changed a lot
    const tryScroll = () => {
        // not using a passive callback by IntersectionObserverto to prevent getBoundingClientRect() caused force reflow
        // due to it will only emit once the configured thresholds are reached
        // thus the top offset might be far from 0 that is top aligned with viewport when the callback is called
        // since the element is still near the bottom of viewport at that point of time
        // even if the thresholds steps by each percentage like [0.01, 0.02, ..., 1] to let triggers callback more often
        // 1% of a very high element is still a big number that may not emit when scrolling ends
        // and the element reached the top of viewport
        const elTop = el.getBoundingClientRect().top;
        const replyTitleTopOffset = getReplyTitleTopOffset();
        if (Math.abs(elTop) < replyTitleTopOffset + (window.innerHeight * 0.05)) // at most 5dvh tolerance
            removeEventListener('scrollend', tryScroll);
        else
            document.documentElement.scrollBy({ top: elTop - replyTitleTopOffset });
    };
    tryScroll();
    addEventListener('scrollend', tryScroll);
};
const parseRouteThenFetch = async (_route: RouteLocationNormalized, isNewQuery: boolean, cursor: Cursor) => {
    if (queryFormRef.value === undefined)
        return false;
    const flattenParams = await queryFormRef.value.parseRouteToGetFlattenParams(_route);
    if (flattenParams === false)
        return false;
    const isFetchSuccess = await fetchPosts(flattenParams, isNewQuery, cursor);
    if (isFetchSuccess && renderType.value === 'list') {
        (() => {
            const scrollPosition = postListItemScrollPosition(_route);
            if (scrollPosition === false)
                return;
            const el = document.querySelector(scrollPosition.el);
            if (el === null)
                return;
            requestIdleCallback(function retry(deadline) {
                if (deadline.timeRemaining() > 0)
                    scrollToPostListItem(el);
                else
                    requestIdleCallback(retry);
            });
        })();
    }

    return isFetchSuccess;
};

onBeforeRouteUpdate(async (to, from) => {
    const isNewQuery = useTriggerRouteUpdateStore().isTriggeredBy('<QueryForm>@submit', to)
        || compareRouteIsNewQuery(to, from);
    const cursor = getRouteCursorParam(to);
    if (!(isNewQuery || _.isEmpty(_.filter(
        postPages.value,
        i => i.pages.currentCursor === cursor
    ))))
        return true;
    const isFetchSuccess = await parseRouteThenFetch(to, isNewQuery, cursor);

    return isNewQuery ? true : isFetchSuccess; // only pass pending route update after successful fetched
});
watchEffect(() => {
    [renderType.value] = selectedRenderTypes.value;
});

onBeforeMount(async () => {
    forumList.value = throwIfApiError(await apiForumList());
    await parseRouteThenFetch(route, true, getRouteCursorParam(route));
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
