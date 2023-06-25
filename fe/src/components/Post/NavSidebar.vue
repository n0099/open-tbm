<template>
    <Menu v-model="selectedThread" v-model:openKeys="expandedPages" @click="selectThread"
          :forceSubMenuRender="true" :inlineIndent="16" mode="inline"
          :class="{ 'd-none': !isPostsNavExpanded }" :aria-expanded="isPostsNavExpanded"
          class="posts-nav col-xl d-xl-block sticky-top">
        <template v-for="posts in postPages">
            <SubMenu v-for="page in [posts.pages.currentPage]" :key="`page${page}`" :title="`第${page}页`">
                <MenuItem v-for="thread in posts.threads" :key="`page${page}-t${thread.tid}`" :title="thread.title"
                          class="posts-nav-thread-item pb-2 border-bottom d-flex flex-wrap justify-content-between">
                    {{ thread.title }}
                    <div class="d-block btn-group p-1" role="group">
                        <template v-for="reply in thread.replies" :key="reply.pid">
                            <button v-for="isFirstReplyInView in [reply.pid === firstPostInView.pid]" :key="isFirstReplyInView"
                                    @click="navigate(page, null, reply.pid)" :data-pid="reply.pid"
                                    :class="{
                                        'btn-info': isFirstReplyInView,
                                        'text-white': isFirstReplyInView,
                                        'rounded-3': isFirstReplyInView,
                                        'btn-light': !isFirstReplyInView
                                    }" class="posts-nav-reply-link btn">{{ reply.floor }}L</button>
                        </template>
                    </div>
                </MenuItem>
            </SubMenu>
        </template>
    </Menu>
    <a @click="togglePostsNavExpanded"
       class="posts-nav-expanded col col-auto align-items-center d-flex d-xl-none shadow-sm vh-100 sticky-top">
        <!-- https://github.com/FortAwesome/vue-fontawesome/issues/313 -->
        <span v-show="isPostsNavExpanded"><FontAwesomeIcon icon="angle-left" /></span>
        <span v-show="!isPostsNavExpanded"><FontAwesomeIcon icon="angle-right" /></span>
    </a>
</template>

<script setup lang="ts">
import { isRouteUpdateTriggeredByPostsNavScrollEvent } from './ViewList.vue';
import { isApiError } from '@/api/index';
import type { ApiPostsQuery } from '@/api/index.d';
import { assertRouteNameIsStr, routeNameWithPage } from '@/router';
import type { Pid, Tid } from '@/shared';
import { removeEnd } from '@/shared';

import { onUnmounted, reactive, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToggle } from '@vueuse/core';
import { Menu, MenuItem, SubMenu } from 'ant-design-vue';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';

const route = useRoute();
const router = useRouter();
const props = defineProps<{ postPages: ApiPostsQuery[] }>();
const state = reactive<{
    expandedPages: string[],
    selectedThread: string[],
    firstPostInView: { page: number, pid: Pid, tid: Tid }
}>({
    expandedPages: [],
    selectedThread: [],
    firstPostInView: { tid: 0, pid: 0, page: 0 }
});
const [isPostsNavExpanded, togglePostsNavExpanded] = useToggle(false);

let isScrollTriggeredByNavigate = false;
const navigate = (page: number | string, tid?: string, pid?: Pid | string) => {
    router.replace({
        hash: `#${pid ?? (tid === undefined ? '' : `t${tid}`)}`,
        params: { ...route.params, page }
    });
    isScrollTriggeredByNavigate = true;
};
const selectThread = ({ domEvent, key }: { domEvent: PointerEvent & { target: Element }, key: string }) => {
    if (domEvent.target.tagName !== 'BUTTON') { // ignore clicks on reply link
        const [, p, t] = /page(\d+)-t(\d+)/u.exec(key) ?? [];
        navigate(p, t);
    }
};

const scrollStop = _.debounce(() => {
    const reduceFindTopmostElementInView = (topOffset: number) => (result: { top: number, el: Element }, curEl: Element) => {
        const elTop = curEl.getBoundingClientRect().top - topOffset;
        // ignore element which its y coord is ahead of the top of viewport
        if (elTop >= 0 && result.top > elTop) return { top: elTop, el: curEl };
        return result;
    };
    const findFirstDomInView = (selector: string, topOffset = 0): Element =>
        [...document.querySelectorAll(selector)].reduce(
            reduceFindTopmostElementInView(topOffset),
            { top: Infinity, el: document.createElement('null') }
        ).el;

    const firstPostInView = {
        t: findFirstDomInView('.thread-title'),
        p: findFirstDomInView('.reply-title', 80) // 80px (5rem) is the top offset of .reply-title, aka `.reply-title { top: 5rem; }`
    };
    const firstPostIDInView = _.mapValues(firstPostInView, i =>
        Number(i.parentElement?.getAttribute('data-post-id')));
    // when there's no thread or reply item in the viewport, firstPostInView.* will be the initial <null> element and firstPostIDInView.* will be NaN
    if (Number.isNaN(firstPostIDInView.t)) {
        firstPostInView.value = { tid: 0, pid: 0, page: 0 };
        router.replace({ hash: '' }); // empty route hash
        isRouteUpdateTriggeredByPostsNavScrollEvent.value = true;
        return;
    }
    const firstPostPageInView = _.mapValues(firstPostInView, i =>
        Number(i.closest('.post-render-list')?.getAttribute('data-page')));

    const replaceRouteHash = (page: number, postID: Pid | Tid, hashPrefix = '') => {
        assertRouteNameIsStr(route.name);
        const hash = `#${hashPrefix}${postID}`;
        router.replace(page === 1 // to prevent '/page/1' occurs in route path
            ? { hash, name: removeEnd(route.name, '+p'), params: _.omit(route.params, 'page') }
            : { hash, name: routeNameWithPage(route.name), params: { ...route.params, page } });
    };
    // is first reply belongs to first thread, true when first thread have no reply, so the first reply will belongs to other thread which comes after first thread in view
    if (_.chain(props.postPages)
        .map(i => i.threads)
        .flatten()
        .filter({ tid: firstPostIDInView.t })
        .map(i => i.replies)
        .flatten()
        .filter({ pid: firstPostIDInView.p })
        .isEmpty()
        .value()) {
        firstPostInView.value = { tid: firstPostIDInView.t, pid: 0, page: firstPostPageInView.t };
        replaceRouteHash(firstPostPageInView.t, firstPostIDInView.t, 't');
    } else {
        firstPostInView.value = { tid: firstPostIDInView.t, pid: firstPostIDInView.p, page: firstPostPageInView.p };
        replaceRouteHash(firstPostPageInView.p, firstPostIDInView.p);
    }
    isRouteUpdateTriggeredByPostsNavScrollEvent.value = true;
}, 200);
const removeScrollEventListener = () => { document.removeEventListener('scroll', scrollStop) };
onUnmounted(removeScrollEventListener);

watchEffect(() => {
    if (_.isEmpty(props.postPages) || isApiError(props.postPages)) removeScrollEventListener();
    else document.addEventListener('scroll', scrollStop, { passive: true });
    expandedPages.value = props.postPages.map(i => `page${i.pages.currentPage}`);
});
watchEffect(() => {
    const { page, tid, pid } = firstPostInView.value;
    selectedThread.value = [`page${page}_t${tid}`];
    if (isScrollTriggeredByNavigate) {
        isScrollTriggeredByNavigate = false;
        return;
    }
    // scroll menu to the link to reply in <ViewList> which is the topmost one in the viewport (nearest to top border of viewport)
    const replyEl = document.querySelector(`.posts-nav-reply-link[data-pid='${pid}']`) as HTMLElement | null;
    const navMenuEl = replyEl?.closest('.posts-nav');
    if (replyEl !== null && navMenuEl
        && navMenuEl.getBoundingClientRect().top === 0 // is navMenuEl sticking to the top border of viewport
    ) navMenuEl.scrollBy(0, replyEl.getBoundingClientRect().top - 150); // 100px offset to scroll down replyEl
});
</script>

<style>
/* to override styles for dom under another component <MenuItem>, we have to declare in global scope */
.posts-nav-thread-item {
    height: auto !important; /* to show reply nav buttons under thread menu items */
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    white-space: normal;
}
</style>

<style scoped>
.posts-nav-expanded {
    padding: 2px;
    font-size: 1.3rem;
    background-color: #f5f5f5;
}

.posts-nav {
    padding: 0 10px 0 0; /* padding-right: 10px to match with the width of ::-webkit-scrollbar */
    overflow: hidden;
    max-height: 100vh;
    border-top: 1px solid #f0f0f0;
}
.posts-nav:hover {
    padding: 0;
    overflow-y: auto;
}
@media (max-width: 1200px) {
    .posts-nav[aria-expanded=true] {
        width: fit-content;
        max-width: 35%;
    }
}
</style>
