<template>
    <Menu v-model:selectedKeys="selectedThread" v-model:openKeys="expandedPages" @click="e => selectThread(e)"
          forceSubMenuRender :inlineIndent="16" mode="inline"
          :class="{ 'd-none': !isPostsNavExpanded }" :aria-expanded="isPostsNavExpanded"
          class="posts-nav col p-0 vh-100 sticky-top border-0">
        <template v-for="posts in postPages">
            <SubMenu v-for="cursor in [posts.pages.currentCursor]" :key="`c${cursor}`" :title="cursorTemplate(cursor)">
                <MenuItem v-for="thread in posts.threads" :key="`c${cursor}-t${thread.tid}`"
                          :data-key="`c${cursor}-t${thread.tid}`" :title="thread.title"
                          class="posts-nav-thread ps-2 ps-lg-3 pe-1 border-bottom">
                    {{ thread.title }}
                    <div class="d-block btn-group p-1 text-wrap" role="group">
                        <template v-for="reply in thread.replies" :key="reply.pid">
                            <a v-for="isFirstReplyInView in [reply.pid === firstPostInView.pid]"
                               :key="isFirstReplyInView.toString()"
                               @click.prevent="_ => navigate(cursor, null, reply.pid)"
                               :data-pid="reply.pid" :href="`#${reply.pid}`"
                               :class="{
                                   'rounded-3': isFirstReplyInView,
                                   'btn-info': isFirstReplyInView,
                                   'btn-light': !isFirstReplyInView,
                                   'btn-outline-warning': !isFirstReplyInView && route.hash === `#${reply.pid}`,
                                   'text-white': isFirstReplyInView,
                                   'text-body-secondary': !isFirstReplyInView
                               }" class="posts-nav-reply btn ms-0 px-2">{{ reply.floor }}L</a>
                        </template>
                    </div>
                </MenuItem>
            </SubMenu>
        </template>
    </Menu>
    <div :class="{
             'border-start': isPostsNavExpanded,
             'border-end': !isPostsNavExpanded
         }"
         class="posts-nav-expand col-auto align-items-center d-flex vh-100 sticky-top border-light-subtle">
        <a @click="_ => togglePostsNavExpanded()" class="text-primary">
            <FontAwesomeIcon v-show="isPostsNavExpanded" icon="angle-left" />
            <FontAwesomeIcon v-show="!isPostsNavExpanded" icon="angle-right" />
        </a>
    </div>
</template>

<script setup lang="ts">
import { getReplyTitleTopOffset } from '@/components/Post/views/ViewList.vue';
import { isApiError } from '@/api/index';
import type { ApiPostsQuery, Cursor } from '@/api/index.d';
import type { Pid, Tid, ToPromise } from '@/shared';
import { cursorTemplate, scrollBarWidth } from '@/shared';

import { onUnmounted, ref, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Menu, MenuItem, SubMenu } from 'ant-design-vue';
import { useToggle } from '@vueuse/core';
import type { MenuClickEventHandler } from 'ant-design-vue/lib/menu/src/interface';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import scrollIntoView from 'scroll-into-view-if-needed';
import _ from 'lodash';

const props = defineProps<{ postPages: ApiPostsQuery[] }>();
const route = useRoute();
const router = useRouter();
const expandedPages = ref<string[]>([]);
const selectedThread = ref<string[]>([]);
const firstPostInViewDefault = { cursor: '', tid: 0, pid: 0 };
const firstPostInView = ref<{ cursor: Cursor, tid: Tid, pid: Pid }>(firstPostInViewDefault);
const [isPostsNavExpanded, togglePostsNavExpanded] = useToggle(matchMedia('(min-width: 900px)').matches);

const navigate = async (cursor: Cursor, tid: string | null, pid?: Pid | string) =>
    router.replace({
        hash: `#${pid ?? (tid === null ? '' : `t${tid}`)}`,
        params: { ...route.params, cursor }
    });
const selectThread: ToPromise<MenuClickEventHandler> = async ({ domEvent, key }) => {
    if (!(domEvent.target as Element).classList.contains('posts-nav-reply')) { // ignore clicks on reply link
        const [, cursor, tid] = /c(.*)-t(\d+)/u.exec(key.toString()) ?? [];
        await navigate(cursor, tid);
    }
};

const scrollStop = _.debounce(() => {
    const findFirstElementInView = (selector: string, topOffset = 0): Element =>
        // eslint-disable-next-line unicorn/no-array-reduce
        [...document.querySelectorAll(selector)].reduce(
            (acc: { top: number, el: Element }, el: Element) => {
                const elTop = el.getBoundingClientRect().top - topOffset;

                // ignore element which its y coord is ahead of the top of viewport
                if (elTop >= 0 && acc.top > elTop)
                    return { top: elTop, el };

                return acc;
            },
            { top: Infinity, el: document.createElement('null') }
        ).el;

    const firstPostElementInView = {
        thread: findFirstElementInView('.thread-title'),
        reply: findFirstElementInView('.reply-title', getReplyTitleTopOffset())
    };
    const firstPostIDInView = _.mapValues(firstPostElementInView, i =>
        Number(i.parentElement?.getAttribute('data-post-id')));

    const firstPostCursorInView = _.mapValues(firstPostElementInView,
        i => i.closest('.post-render-list')?.getAttribute('data-cursor') ?? '');

    firstPostInView.value = {
        tid: firstPostIDInView.thread,

        // is the first reply belonged to the first thread, true when the first thread has no reply,
        // the first reply will belong to another thread that comes after the first thread in view
        ..._.chain(props.postPages)
            .map(i => i.threads)
            .flatten()
            .filter({ tid: firstPostIDInView.thread })
            .map(i => i.replies)
            .flatten()
            .filter({ pid: firstPostIDInView.reply })
            .isEmpty()
            .value()
            ? { pid: 0, cursor: firstPostCursorInView.thread }
            : { pid: firstPostIDInView.reply, cursor: firstPostCursorInView.reply }
    };
}, 200);
const removeScrollEventListener = () => { document.removeEventListener('scroll', scrollStop) };
onUnmounted(removeScrollEventListener);

watchEffect(() => {
    if (!isPostsNavExpanded.value || _.isEmpty(props.postPages) || isApiError(props.postPages))
        removeScrollEventListener();
    else
        document.addEventListener('scroll', scrollStop, { passive: true });
    expandedPages.value = props.postPages.map(i => `c${i.pages.currentCursor}`);
});
watchEffect(() => {
    const { cursor, tid } = firstPostInView.value;
    const menuKey = `c${cursor}-t${tid}`;
    selectedThread.value = [menuKey];

    const threadEl = document.querySelector(`.posts-nav-thread[data-key='${menuKey}']`);
    if (threadEl !== null)
        scrollIntoView(threadEl, { scrollMode: 'if-needed', boundary: document.querySelector('.posts-nav') });
});
</script>

<style scoped>
.posts-nav-expand {
    width: v-bind(scrollBarWidth);
    padding: .125rem;
    font-size: 1.3rem;
}

.posts-nav {
    overflow: hidden;
}
.posts-nav:hover {
    overflow-y: auto;
}
@media (min-width: 900px) {
    .posts-nav:hover + .posts-nav-expand {
        display: none !important;
    }
}
@media (min-width: 900px) and (max-width: 1250px) {
    /* keeping .posts-nav:hover to replace .posts-nav-expand with scrollbar
       without shifting when the width of .posts-nav excess 30% */
    .posts-nav[aria-expanded=true] {
        flex: 0 1 30%;
        max-width: calc(30% + v-bind(scrollBarWidth));
    }
    .posts-nav:hover {
        flex-grow: 1 !important;
    }
}

@media (max-width: 900px) {
    .posts-nav[aria-expanded=true], .posts-nav[aria-expanded=true] + .posts-nav-expand {
        position: fixed;
        z-index: 1040;
    }
    .posts-nav[aria-expanded=true] {
        /* linear regression of vw,width: 456,456 768,384(50%) https://www.wolframalpha.com/input?i=y%3D-0.2308x%2B561.2 */
        width: calc(-0.2308 * 100vw + 561.2px - v-bind(scrollBarWidth));
    }
    .posts-nav[aria-expanded=true] + .posts-nav-expand {
        /* merge .posts-nav-expand into the scrollbar of .posts-nav */
        inset-inline-start: min(-0.2308 * 100vw + 561.2px - v-bind(scrollBarWidth) * 2, 100vw - v-bind(scrollBarWidth) * 2);
    }
    .posts-nav[aria-expanded=true] + .posts-nav-expand {
        /* after merge narrow the height from 100vh to fit-content for interactive with the scrollbar */
        height: auto !important;
        /* https://stackoverflow.com/questions/28455100/how-to-center-div-vertically-inside-of-absolutely-positioned-parent-div/28456704#28456704 */
        inset-block-start: 50%;
        transform: translateY(-50%);
    }
}

:deep(.posts-nav-thread) {
    height: auto !important; /* show reply nav buttons under thread menu items */
    white-space: normal;
    line-height: 2rem;
    content-visibility: auto;
    contain-intrinsic-height: auto 6rem;
}

.posts-nav-reply:hover {
    border-radius: var(--bs-border-radius) !important;
}
</style>
