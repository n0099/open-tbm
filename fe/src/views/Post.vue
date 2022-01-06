<template>
    <div class="container">
        <QueryForm @query="fetchPosts($event, true)" :forumList="forumList" :isLoading="isLoading" ref="queryFormRef" />
        <p>当前页数：{{ currentRoutePage }}</p>
        <Menu v-show="postPages.length !== 0" v-model:selectedKeys="selectedRenderTypes" mode="horizontal">
            <MenuItem key="list">列表视图</MenuItem>
            <MenuItem key="table">表格视图</MenuItem>
        </Menu>
    </div>
    <div v-show="postPages.length !== 0" class="container-fluid">
        <div class="row justify-content-center">
            <NavSidebar v-if="renderType === 'list'" :postPages="postPages" :firstPostInView="firstPostInView" />
            <div :class="{
                'post-render-wrapper': true,
                'post-render-list-wrapper': renderType === 'list',
                'col': true,
                'col-xl-10': renderType === 'list'
            }">
                <template v-for="(posts, pageIndex) in postPages" :key="posts.pages.currentPage">
                    <PagePreviousButton @loadPage="loadPage($event)" :pageInfo="posts.pages" />
                    <ViewList v-if="renderType === 'list'" :initialPosts="posts" />
                    <ViewTable v-else-if="renderType === 'table'" :posts="posts" />
                    <PageNextButton v-if="!isLoading && pageIndex === postPages.length - 1"
                                    @loadPage="loadPage($event)" :currentPage="posts.pages.currentPage" />
                </template>
            </div>
            <div v-show="renderType === 'list'" class="post-render-list-right-padding col-xl d-none p-0"></div>
        </div>
    </div>
    <div class="container">
        <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" />
        <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
    </div>
</template>

<script lang="ts">
import type { ApiError, ApiForumList, ApiPostsQuery } from '@/api/index.d';
import { apiForumList, apiPostsQuery, isApiError, throwIfApiError } from '@/api';
import PlaceholderError from '@/components/PlaceholderError.vue';
import PlaceholderPostList from '@/components/PlaceholderPostList.vue';
import { NavSidebar, PageNextButton, PagePreviousButton, QueryForm, ViewList, ViewTable } from '@/components/Post/exports.vue';
import type { ObjUnknown } from '@/shared';
import { notyShow } from '@/shared';

import { defineComponent, reactive, ref, toRefs, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Menu, MenuItem } from 'ant-design-vue';
import _ from 'lodash';

export default defineComponent({
    components: { Menu, MenuItem, PlaceholderError, PlaceholderPostList, QueryForm, ViewList, ViewTable, NavSidebar },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const state = reactive<{
            forumList: ApiForumList,
            postPages: ApiPostsQuery[],
            currentQueryParams: ObjUnknown,
            currentRoutePage: number,
            isLoading: boolean,
            lastFetchError: ApiError | null,
            showPlaceholderPostList: boolean,
            renderType: 'list' | 'table',
            selectedRenderTypes: ['list' | 'table']
        }>({
            forumList: [],
            postPages: [],
            currentQueryParams: {},
            currentRoutePage: 1,
            isLoading: false,
            lastFetchError: null,
            showPlaceholderPostList: true,
            renderType: 'list',
            selectedRenderTypes: ['list'],
        });
        const queryFormRef = ref<InstanceType<typeof QueryForm>>();
        const loadPage = page => {
            if (_.map(state.postPages, 'pages.currentPage').includes(page)) $(`.post-previous-page[data-page='${page}']`)[0].scrollIntoView(); // scroll to page if already loaded
            router.push(_.merge(route.name.startsWith('param')
                ? { path: `/page/${page}/${route.params.pathMatch}` }
                : {
                    name: route.name.endsWith('+p') ? route.name : `${route.name}+p`,
                    params: { ...route.params, page }
                }));
        };
        const fetchPosts = async (queryParams: ObjUnknown, isNewQuery: boolean) => {
            const startTime = Date.now();
            state.lastFetchError = null;
            state.showPlaceholderPostList = true;
            if (isNewQuery) state.postPages = [];
            state.isLoading = true;
            const postsQuery = await apiPostsQuery({
                query: JSON.stringify(queryParams),
                page: isNewQuery ? 1 : parseInt(route.params.page ?? '1')
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
                const page = state.currentRoutePage;
                const forumName = `${state.postPages[0].forum.name}吧`;
                const threadTitle = state.postPages[0].threads[0].title;
                switch (queryFormRef.value?.getCurrentQueryType()) {
                    case 'fid':
                    case 'search':
                        document.title = `第${page}页 - ${forumName} - 贴子查询 - 贴吧云监控`;
                        break;
                    case 'postID':
                        document.title = `第${page}页 - 【${forumName}】${threadTitle} - 贴子查询 - 贴吧云监控`;
                        break;
                }
            }
            notyShow('success', `已加载第${postsQuery.pages.currentPage}页 ${postsQuery.pages.itemsCount}条记录 耗时${Date.now() - startTime}ms`);
            return true;
        };

        watchEffect(() => {
            state.currentRoutePage = parseInt(route.params.page ?? '1');
            [state.renderType] = state.selectedRenderTypes;
        });

        (async () => {
            state.forumList = throwIfApiError(await apiForumList());
        })();

        return { ...toRefs(state), queryFormRef, fetchPosts, loadPage };
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
