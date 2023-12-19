<template>
    <Menu v-model="selectedThread" v-model:openKeys="expandedPages" @click="e => selectThread(e)"
          forceSubMenuRender :inlineIndent="16" mode="inline"
          :class="{ 'd-none': !isPostsNavExpanded }" :aria-expanded="isPostsNavExpanded"
          class="posts-nav col-xl d-xl-block sticky-top">
        <template v-for="posts in postPages">
            <SubMenu v-for="cursor in [posts.pages.currentCursor]" :key="`c${cursor}`" :title="`第${cursor}页`">
                <MenuItem v-for="thread in posts.threads" :key="`c${cursor}-t${thread.tid}`" :title="thread.title"
                          class="posts-nav-thread-item pb-2 border-bottom d-flex flex-wrap justify-content-between">
                    {{ thread.title }}
                    <div class="d-block btn-group p-1" role="group">
                        <template v-for="reply in thread.replies" :key="reply.pid">
                            <button v-for="isFirstReplyInView in [reply.pid === firstPostInView.pid]"
                                    :key="String(isFirstReplyInView)"
                                    @click="_ => navigate(cursor, null, reply.pid)" :data-pid="reply.pid"
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
    <a @click="_ => togglePostsNavExpanded"
       class="posts-nav-expanded col col-auto align-items-center d-flex d-xl-none shadow-sm vh-100 sticky-top">
        <!-- https://github.com/FortAwesome/vue-fontawesome/issues/313 -->
        <span v-show="isPostsNavExpanded"><FontAwesomeIcon icon="angle-left" /></span>
        <span v-show="!isPostsNavExpanded"><FontAwesomeIcon icon="angle-right" /></span>
    </a>
</template>

<script setup lang="ts">
import { isRouteUpdateTriggeredByPostsNavScrollEvent } from './views/ViewList.vue';
import { isApiError } from '@/api/index';
import type { ApiPostsQuery, Cursor } from '@/api/index.d';
import { assertRouteNameIsStr, routeNameSuffix, routeNameWithCursor } from '@/router';
import type { Pid, Tid, ToPromise } from '@/shared';
import { removeEnd } from '@/shared';

import { onUnmounted, ref, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToggle } from '@vueuse/core';
import { Menu, MenuItem, SubMenu } from 'ant-design-vue';
import type { MenuClickEventHandler } from 'ant-design-vue/lib/menu/src/interface';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import _ from 'lodash';

const props = defineProps<{ postPages: ApiPostsQuery[] }>();
const route = useRoute();
const router = useRouter();
const expandedPages = ref<string[]>([]);
const selectedThread = ref<string[]>([]);
const firstPostInViewDefault = { cursor: '', tid: 0, pid: 0 };
const firstPostInView = ref<{ cursor: Cursor, tid: Tid, pid: Pid }>(firstPostInViewDefault);
const [isPostsNavExpanded, togglePostsNavExpanded] = useToggle(false);

let isScrollTriggeredByNavigate = false;
const navigate = async (cursor: Cursor, tid: string | null, pid?: Pid | string) => {
    if (!await router.replace({
        hash: `#${pid ?? (tid === null ? '' : `t${tid}`)}`,
        params: { ...route.params, cursor }
    }))
        isScrollTriggeredByNavigate = true;
};
const selectThread: ToPromise<MenuClickEventHandler> = async ({ domEvent, key }) => {
    if ((domEvent.target as Element).tagName !== 'BUTTON') { // ignore clicks on reply link
        const [, p, t] = /page(\d+)-t(\d+)/u.exec(String(key)) ?? [];
        await navigate(p, t);
    }
};

const scrollStop = _.debounce(() => {
    const reduceFindTopmostElementInView = (topOffset: number) =>
        (result: { top: number, el: Element }, curEl: Element) => {
            const elTop = curEl.getBoundingClientRect().top - topOffset;

            // ignore element which its y coord is ahead of the top of viewport
            if (elTop >= 0 && result.top > elTop)
                return { top: elTop, el: curEl };

            return result;
        };
    const findFirstDomInView = (selector: string, topOffset = 0): Element =>
        [...document.querySelectorAll(selector)].reduce(
            reduceFindTopmostElementInView(topOffset),
            { top: Infinity, el: document.createElement('null') }
        ).el;

    const currentFirstPostInView = {
        t: findFirstDomInView('.thread-title'),

        // 80px (5rem) is the top offset of .reply-title, aka `.reply-title { top: 5rem; }`
        p: findFirstDomInView('.reply-title', 80)
    };
    const firstPostIDInView = _.mapValues(currentFirstPostInView, i =>
        Number(i.parentElement?.getAttribute('data-post-id')));

    // when there's no thread or reply item in the viewport
    // currentFirstPostInView.* will be the initial <null> element and firstPostIDInView.* will be NaN
    if (Number.isNaN(firstPostIDInView.t)) {
        firstPostInView.value = firstPostInViewDefault;
        void router.replace({ hash: '' }) // empty route hash
            .then(failed => {
                if (!failed)
                    isRouteUpdateTriggeredByPostsNavScrollEvent.value = true;
            });

        return;
    }
    const firstPostCursorInView = _.mapValues(currentFirstPostInView,
        i => i.closest('.post-render-list')?.getAttribute('data-cursor') ?? '');

    const replaceRouteHash = async (cursor: Cursor, postID: Pid | Tid, hashPrefix = '') => {
        assertRouteNameIsStr(route.name);
        const hash = `#${hashPrefix}${postID}`;
        if (!await router.replace(cursor === '' // to prevent '/page/1' occurs in route path
            ? { hash, name: removeEnd(route.name, routeNameSuffix.cursor), params: _.omit(route.params, 'cursor') }
            : { hash, name: routeNameWithCursor(route.name), params: { ...route.params, cursor } }))
            isRouteUpdateTriggeredByPostsNavScrollEvent.value = true;
    };

    // is the first reply belonged to the first thread, true when the first thread has no reply,
    // the first reply will belong to another thread that comes after the first thread in view
    if (_.chain(props.postPages)
        .map(i => i.threads)
        .flatten()
        .filter({ tid: firstPostIDInView.t })
        .map(i => i.replies)
        .flatten()
        .filter({ pid: firstPostIDInView.p })
        .isEmpty()
        .value()) {
        firstPostInView.value = { tid: firstPostIDInView.t, pid: 0, cursor: firstPostCursorInView.t };
        void replaceRouteHash(firstPostCursorInView.t, firstPostIDInView.t, 't');
    } else {
        firstPostInView.value = { tid: firstPostIDInView.t, pid: firstPostIDInView.p, cursor: firstPostCursorInView.p };
        void replaceRouteHash(firstPostCursorInView.p, firstPostIDInView.p);
    }
}, 200);
const removeScrollEventListener = () => { document.removeEventListener('scroll', scrollStop) };
onUnmounted(removeScrollEventListener);

watchEffect(() => {
    if (_.isEmpty(props.postPages) || isApiError(props.postPages))
        removeScrollEventListener();
    else
        document.addEventListener('scroll', scrollStop, { passive: true });
    expandedPages.value = props.postPages.map(i => `c${i.pages.currentCursor}`);
});
watchEffect(() => {
    const { cursor, tid, pid } = firstPostInView.value;
    selectedThread.value = [`c${cursor}_t${tid}`];
    if (isScrollTriggeredByNavigate) {
        isScrollTriggeredByNavigate = false;

        return;
    }

    // scroll menu to the link to reply in <ViewList>
    // which is the topmost one in the viewport (nearest to top border of viewport)
    const replyEl = document.querySelector(`.posts-nav-reply-link[data-pid='${pid}']`);
    const navMenuEl = replyEl?.closest('.posts-nav');
    if (replyEl !== null && navMenuEl
        && navMenuEl.getBoundingClientRect().top === 0) // is navMenuEl sticking to the top border of viewport
        navMenuEl.scrollBy(0, replyEl.getBoundingClientRect().top - 150); // 100px offset to scroll down replyEl
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
