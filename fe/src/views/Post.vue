<template>
    <div class="container">
        <QueryForm ref="queryFormRef" @query="submitQueryForm" :forumList="forumList" :isLoading="isLoading" />
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
import type { ApiError, ApiForumList, ApiPostsQuery } from '@/api/index.d';
import { apiForumList, apiPostsQuery, isApiError, throwIfApiError } from '@/api';
import { NavSidebar, PlaceholderError, PlaceholderPostList, PostViewPage, QueryForm } from '@/components/Post/exports.vue';
import { postListItemScrollPosition } from '@/components/Post/ViewList.vue';
import { compareRouteIsNewQuery, routePageParamNullSafe } from '@/router';
import { lazyLoadUpdate } from '@/shared/lazyLoad';
import type { ObjUnknown } from '@/shared';
import { notyShow, titleTemplate } from '@/shared';

import { computed, defineComponent, nextTick, reactive, ref, toRefs, watchEffect } from 'vue';
import { onBeforeRouteUpdate, useRoute } from 'vue-router';
import { useHead } from '@vueuse/head';
import { Menu, MenuItem } from 'ant-design-vue';
import _ from 'lodash';

export type PostViewRenderer = 'list' | 'table';
export default defineComponent({
    components: { Menu, MenuItem, PlaceholderError, PlaceholderPostList, QueryForm, NavSidebar, PostViewPage },
    setup() {
        const route = useRoute();
        const state = reactive<{
            title: string,
            forumList: ApiForumList,
            postPages: ApiPostsQuery[],
            isLoading: boolean,
            lastFetchError: ApiError | null,
            showPlaceholderPostList: boolean,
            renderType: PostViewRenderer,
            selectedRenderTypes: [PostViewRenderer]
        }>({
            title: '帖子查询',
            forumList: [],
            postPages: [],
            isLoading: false,
            lastFetchError: null,
            showPlaceholderPostList: true,
            renderType: 'list',
            selectedRenderTypes: ['list']
        });
        useHead({ title: computed(() => titleTemplate(state.title)) });
        const queryFormRef = ref<InstanceType<typeof QueryForm>>();
        const fetchPosts = async (queryParams: ObjUnknown[] | undefined, isNewQuery: boolean, page = 1) => {
            const startTime = Date.now();
            state.lastFetchError = null;
            state.showPlaceholderPostList = true;
            if (isNewQuery) state.postPages = [];
            state.isLoading = true;

            const postsQuery = await apiPostsQuery({
                query: JSON.stringify(queryParams),
                page: isNewQuery ? 1 : page
            }).finally(() => {
                state.showPlaceholderPostList = false;
                state.isLoading = false;
            });

            if (isApiError(postsQuery)) {
                state.lastFetchError = postsQuery;
                return false;
            }
            if (isNewQuery) state.postPages = [postsQuery];
            else state.postPages = _.sortBy([...state.postPages, postsQuery], i => i.pages.currentPage);

            { // update title
                const forumName = `${state.postPages[0].forum.name}吧`;
                const threadTitle = state.postPages[0].threads[0].title;
                switch (queryFormRef.value?.getCurrentQueryType()) {
                    case 'fid':
                    case 'search':
                        state.title = `第${page}页 - ${forumName} - 帖子查询`;
                        break;
                    case 'postID':
                        state.title = `第${page}页 - 【${forumName}】${threadTitle} - 帖子查询`;
                        break;
                }
            }
            const networkTime = Date.now() - startTime;
            await nextTick(); // wait for child components finish dom update
            notyShow('success', `已加载第${postsQuery.pages.currentPage}页 ${postsQuery.pages.itemsCount}条记录 耗时${((Date.now() - startTime) / 1000).toFixed(2)}s 网络${networkTime}ms`);
            lazyLoadUpdate();
            return true;
        };

        let isSubmitTriggeredByInitialLoad = true;
        let isRouteChangeTriggeredByQueryForm = false;
        const submitQueryForm = (e: ObjUnknown[]) => {
            isRouteChangeTriggeredByQueryForm = true;
            if (isSubmitTriggeredByInitialLoad) {
                fetchPosts(e, false, routePageParamNullSafe(route))
                    .then(isFetchSuccess => {
                        if (!isFetchSuccess) return;
                        const scrollPosition = postListItemScrollPosition(route);
                        const el = document.querySelector(scrollPosition.el);
                        if (el === null) return;
                        window.scrollTo(0, el.getBoundingClientRect().top + window.scrollY - scrollPosition.top);
                    });
            } else {
                fetchPosts(e, true);
            }
            isSubmitTriggeredByInitialLoad = false;
        };

        (async () => {
            state.forumList = throwIfApiError(await apiForumList());
            queryFormRef.value?.submit(true);
        })();
        watchEffect(() => {
            [state.renderType] = state.selectedRenderTypes;
        });

        onBeforeRouteUpdate(async (to, from) => {
            if (isRouteChangeTriggeredByQueryForm) {
                isRouteChangeTriggeredByQueryForm = false;
                return true;
            }
            const isNewQuery = compareRouteIsNewQuery(to, from);
            const page = routePageParamNullSafe(to);
            if (!isNewQuery && !_.isEmpty(_.filter(
                state.postPages,
                i => i.pages.currentPage === page
            ))) return true;
            const isFetchSuccess = await fetchPosts(queryFormRef.value?.parseRouteToGetFlattenParams(to), isNewQuery, page);
            return isNewQuery ? true : isFetchSuccess; // only pass pending route change after successful fetched
        });

        return { routePageParamNullSafe, ...toRefs(state), queryFormRef, submitQueryForm, fetchPosts };
    }
});
</script>

<style scoped>
.post-render-wrapper {
    padding-left: 10px;
}
@media (max-width: 1200px) {
    .post-render-wrapper {
        width: calc(100% - 15px); /* minus the width of .posts-nav-expanded in <NavSidebar> to prevent it warps new row */
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
        display: block !important; /* only show right margin spaces when enough to prevent too narrow to display <posts-nav> */
    }
}
</style>
