<template>
    <div class="container">
        <QueryForm @query="query($event)" :forumList="forumList" />
        <p>当前页数：{{ currentRoutePage }}</p>
        <Menu v-show="postPages.length !== 0" v-model="renderType" mode="horizontal">
            <MenuItem key="list">列表视图</MenuItem>
            <MenuItem key="table">表格视图</MenuItem>
        </Menu>
    </div>
    <div v-show="postPages.length !== 0" class="container-fluid">
        <div class="justify-content-center row">
            <NavSidebar :post-pages="postPages" :aria-expanded="postsNavExpanded"
                         class="posts-nav col-xl d-none d-xl-block vh-100 sticky-top" />
            <!-- fixme: Cannot read property 'value' of undefined @ invokeDirectiveHook()
            <a @click="postsNavExpanded = !postsNavExpanded"
               class="posts-nav-collapse col col-auto align-items-center d-flex d-xl-none shadow-sm vh-100 sticky-top">
                <FontAwesomeIcon v-show="postsNavExpanded" icon="angle-left" />
                <FontAwesomeIcon v-show="!postsNavExpanded" icon="angle-right" />
            </a>-->
            <div :class="{
                'post-render-wrapper': true,
                'post-render-list-wrapper': renderType === 'list',
                'col': true,
                'col-xl-auto': true,
                'col-xl-10': renderType !== 'list' // let wrapper, except .post-render-list-wrapper, takes over right margin spaces, aka .post-render-list-wrapper-placeholder
            }">
                <template v-for="(posts, pageIndex) in postPages">
                    <PagePreviousButton @load-page="loadPage($event)" :page-info="posts.pages" />
                    <ViewList v-if="renderType === 'list'" :key="posts.pages.currentPage" :initial-posts="posts" />
                    <ViewTable v-else-if="renderType === 'table'" :key="posts.pages.currentPage" :posts="posts" />
                    <PageNextButton v-if="!isLoading && pageIndex === postPages.length - 1"
                                    @load-page="loadPage($event)" :current-page="posts.pages.currentPage" />
                </template>
            </div>
            <div v-show="renderType === 'list'" class="post-render-list-wrapper-placeholder col-xl d-none"></div>
        </div>
    </div>
    <div class="container">
        <PlaceholderError v-if="lastFetchError !== null" :error="lastFetchError" />
        <PlaceholderPostList v-show="showPlaceholderPostList" :isLoading="isLoading" />
    </div>
</template>

<script lang="ts">
import type { ApiError, ApiForumList } from '@/api/index.d';
import { apiForumList, isApiError } from '@/api';
import PlaceholderError from '@/components/PlaceholderError.vue';
import PlaceholderPostList from '@/components/PlaceholderPostList.vue';
import { NavSidebar, PageNextButton, PagePreviousButton, QueryForm, ViewList, ViewTable } from '@/components/Post/exports.vue';

import { computed, defineComponent, onBeforeMount, onMounted, reactive, toRefs, watch, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Menu, MenuItem } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';

export default defineComponent({
    components: { FontAwesomeIcon, Menu, MenuItem, PlaceholderError, PlaceholderPostList, PageNextButton, PagePreviousButton, QueryForm, ViewList, ViewTable, NavSidebar },
    setup() {
        const route = useRoute();
        const router = useRouter();
        const state = reactive<{
            forumList: ApiForumList,
            postPages: unknown[],
            currentRoutePage: number,
            isLoading: boolean,
            lastFetchError: ApiError | null,
            showPlaceholderPostList: boolean,
            renderType: 'list' | 'raw' | 'table',
            postsNavExpanded: boolean,
            scrollStopDebounce: Function | null
        }>({
            forumList: [],
            postPages: [],
            currentRoutePage: 1,
            isLoading: false,
            lastFetchError: null,
            showPlaceholderPostList: false,
            renderType: 'list',
            postsNavExpanded: false,
            scrollStopDebounce: null
        });
        const updateTitle = () => {
            const page = state.currentRoutePage;
            const forumName = `${state.postPages[0].forum.name}吧`;
            const threadTitle = state.postPages[0].threads[0].title;
            switch (this.$refs.queryForm.currentQueryType()) {
                case 'fid':
                case 'search':
                    document.title = `第${page}页 - ${forumName} - 贴子查询 - 贴吧云监控`;
                    break;
                case 'postID':
                    document.title = `第${page}页 - 【${forumName}】${threadTitle} - 贴子查询 - 贴吧云监控`;
                    break;
            }
        };
        const scrollStop = () => {
            const findFirstPostInView = topOffset => (result, dom) => { // partial invoke
                const top = dom.getBoundingClientRect().top - topOffset;
                result.top = result.top === undefined ? Infinity : result.top;
                if (top >= 0 && result.top > top) { // ignore doms which y coord is ahead of top of viewport
                    return result = { dom, top };
                }
                return result;
            };
            const firstThreadDomInView = $(_.reduce($('.thread-title'), findFirstPostInView(0), {}).dom);
            const firstThreadTidInView = parseInt(firstThreadDomInView.parent().prop('id').substr(1));
            const firstReplyDomInView = $(_.reduce($('.reply-title'), findFirstPostInView(62), {}).dom); // 62px is the top offset of reply title
            const firstReplyPidInView = parseInt(firstReplyDomInView.parent().prop('id'));
            const newRouteParams = { ...route.params, 0: route.params.pathMatch }; // [vue-router] missing param for named route "param+p": Expected "0" to be defined
            if (_.chain(state.postPages)
                .map('threads')
                .flatten()
                .filter({ tid: firstThreadTidInView })
                .map('replies')
                .flatten()
                .filter({ pid: firstReplyPidInView })
                .isEmpty()
                .value()) { // is first reply belongs to first thread, true when first thread have no reply so the first reply will be some other threads reply which comes after first thread in view
                const page = firstThreadDomInView.parents('.post-render-list').data('page');
                window.$sharedData.firstPostInView = _.merge(window.$sharedData.firstPostInView, { page, tid: firstThreadTidInView, pid: 0 });
                router.replace({ hash: `#t${firstThreadTidInView}`, params: { ...newRouteParams, page } });
            } else { // _.merge() prevents vue data binding observer in prototypes being cleared
                const page = firstReplyDomInView.parents('.post-render-list').data('page');
                window.$sharedData.firstPostInView = _.merge(window.$sharedData.firstPostInView, { page, tid: firstThreadTidInView, pid: firstReplyPidInView });
                router.replace({ hash: `#${firstReplyPidInView}`, params: { ...newRouteParams, page } });
            }
            updateTitle();
        };
        state.scrollStopDebounce = _.debounce(scrollStop, 200);
        const loadPage = page => {
            if (_.map(state.postPages, 'pages.currentPage').includes(page)) $(`.post-previous-page[data-page='${page}']`)[0].scrollIntoView(); // scroll to page if already loaded

            router.push(_.merge(route.name.startsWith('param')
                ? { path: `/page/${page}/${route.params.pathMatch}` }
                : {
                    name: route.name.endsWith('+p') ? route.name : `${route.name}+p`,
                    params: { ...route.params, page }
                }));
        };
        const query = ({ queryParams, shouldReplacePage }) => {
            state.lastFetchError = null;
            if (shouldReplacePage) {
                state.postPages = []; // clear posts pages data before request to show loading placeholder
            } else if (!_.isEmpty(_.filter(_.map(state.postPages, 'pages.currentPage'), i => i === state.currentRoutePage))) {
                state.isLoading = false;
                return; // cancel request when requesting page have already been loaded
            }

            const ajaxStartTime = Date.now();
            if (window.$previousPostsQueryAjax !== undefined) { // cancel previous pending ajax to prevent conflict
                window.$previousPostsQueryAjax.abort();
            }
            $$reCAPTCHACheck().then(reCAPTCHA => {
                window.$previousPostsQueryAjax = $.getJSON(`${$$baseUrl}/api/postsQuery`, $.param({ query: JSON.stringify(queryParams), page: state.currentRoutePage, reCAPTCHA }));
                window.$previousPostsQueryAjax
                    .done(ajaxData => {
                        if (shouldReplacePage) { // is loading next page data on the same query params or requesting new query with different params
                            state.postPages = [ajaxData];
                        } else {
                            // insert after existing previous page, if not exist will be inserted at start
                            state.postPages.splice(_.findIndex(state.postPages, { pages: { currentPage: state.currentRoutePage - 1 } }) + 1, 0, ajaxData);
                        }
                        new Noty({ timeout: 3000, type: 'success', text: `已加载第${ajaxData.pages.currentPage}页 ${ajaxData.pages.itemsCount}条贴子 耗时${Date.now() - ajaxStartTime}ms` }).show();
                        this.updateTitle();
                    })
                    .fail(jqXHR => {
                        state.postPages = [];
                        if (jqXHR.responseJSON !== undefined) {
                            const error = jqXHR.responseJSON;
                            if (_.isObject(error.errorInfo)) { // response when laravel failed validate, same with ajaxError jquery event @ layout.blade.php
                                state.lastFetchError = { code: error.errorCode, info: _.map(error.errorInfo, (info, paramName) => `参数 ${paramName}：${info.join('<br />')}`).join('<br />') };
                            } else {
                                state.lastFetchError = { code: error.errorCode, info: error.errorInfo };
                            }
                        }
                    })
                    .always(() => { state.isLoading = false });
            });
        };

        watch(route, (to) => {
            delete to.params[0]; // fixme: [vue-router] missing param for named route "param+p": Expected "0" to be defined
            if (to.hash === '#') { // remove empty hash
                to.hash = '';
            }
        });
        watchEffect(() => {
            state.currentRoutePage = parseInt(route.params.page ?? '1');
        });
        watch(() => state.renderType, renderType => {
            if (state.scrollStopDebounce === null) return;
            if (renderType === 'list') window.addEventListener('scroll', state.scrollStopDebounce, { passive: true });
            else window.removeEventListener('scroll', state.scrollStopDebounce);
        }, { immediate: true });
        (async () => {
            const forumListResult = await apiForumList();
            if (isApiError(forumListResult)) return;
            state.forumList = forumListResult;
        })();

        return { ...toRefs(state), query, loadPage };
    }
});

window.$previousPostsQueryAjax = undefined;
window.$sharedData = {
    firstPostInView: { tid: 0, pid: 0 }
};
window.$getUserInfo = dataSource => uid => // curry invoke
    _.find(dataSource, { uid }) || { // thread latest replier uid might be unknown
        id: 0,
        uid: 0,
        name: '未知用户',
        displayName: null,
        avatarUrl: null,
        gender: 0,
        fansNickname: null,
        iconInfo: []
    };

const postsQueryVue = {
    router: {
        scrollBehavior(to, from, savedPosition) {
            if (to.hash !== '') {
                // skip scroll to dom when route update is triggered by scrollStop()
                if (!(window.$sharedData.firstPostInView.pid === parseInt(to.hash.substr(1))
                    || window.$sharedData.firstPostInView.tid === parseInt(to.hash.substr(2)))) {
                    return { // https://stackoverflow.com/questions/37270787/uncaught-syntaxerror-failed-to-execute-queryselector-on-document
                        selector: `.post-render-list[data-page='${to.params.page}'] [id='${to.hash.substr(1)}']`
                    };
                }
            } else if (savedPosition) {
                return savedPosition;
            }
        }
    }
};
</script>

<style scoped>
.posts-nav-collapse {
    padding: 2px;
    font-size: 1.3em;
    background-color: whitesmoke;
}
.post-render-wrapper {
    padding-left: 10px;
}
.post-render-list-wrapper-placeholder {
    padding: 0;
}

@media (max-width: 1200px) {
    .post-render-wrapper {
        width: calc(100% - 15px); /* minus width of <posts-nav-collapse> to prevent it warps new row */
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
    .post-render-list-wrapper-placeholder {
        display: block !important; /* only show right margin spaces when enough to prevent too narrow to display <posts-nav> */
    }
}
</style>
