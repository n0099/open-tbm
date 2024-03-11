<template>
    <Menu v-model:selectedKeys="selectedThreads" v-model:openKeys="expandedPages" @click="e => selectThread(e)"
          forceSubMenuRender :inlineIndent="16" mode="inline"
          :class="{ 'd-none': !isPostNavExpanded }" :aria-expanded="isPostNavExpanded"
          class="post-nav col p-0 vh-100 sticky-top border-0">
        <template v-for="posts in postPages">
            <SubMenu v-for="cursor in [posts.pages.currentCursor]" :key="`c${cursor}`" :title="cursorTemplate(cursor)">
                <MenuItem v-for="thread in posts.threads" :key="`c${cursor}-t${thread.tid}`"
                          :data-key="`c${cursor}-t${thread.tid}`" :title="thread.title"
                          class="post-nav-thread ps-2 ps-lg-3 pe-1 border-bottom">
                    {{ thread.title }}
                    <div class="d-block btn-group p-1 text-wrap" role="group">
                        <template v-for="reply in thread.replies" :key="reply.pid">
                            <a v-for="isTopmostReply in [reply.pid === viewportTopmostPost.pid]"
                               :key="isTopmostReply.toString()"
                               @click.prevent="_ => navigate(cursor, null, reply.pid)"
                               :data-pid="reply.pid" :href="`#${reply.pid}`"
                               :class="{
                                   'rounded-3': isTopmostReply,
                                   'btn-info': isTopmostReply,
                                   'btn-light': !isTopmostReply,
                                   'btn-outline-warning': !isTopmostReply && route.hash === `#${reply.pid}`,
                                   'text-white': isTopmostReply,
                                   'text-body-secondary': !isTopmostReply
                               }" class="post-nav-reply btn ms-0 px-2">{{ reply.floor }}L</a>
                        </template>
                    </div>
                </MenuItem>
            </SubMenu>
        </template>
    </Menu>
    <div :class="{
             'border-start': isPostNavExpanded,
             'border-end': !isPostNavExpanded
         }"
         class="post-nav-expand col-auto align-items-center d-flex vh-100 sticky-top border-light-subtle">
        <a @click="_ => togglePostNavExpanded()" class="text-primary">
            <FontAwesomeIcon v-show="isPostNavExpanded" icon="angle-left" />
            <FontAwesomeIcon v-show="!isPostNavExpanded" icon="angle-right" />
        </a>
    </div>
</template>

<script setup lang="ts">
import type { ApiPosts, Cursor } from '@/api/index.d';
import { getReplyTitleTopOffset } from '@/components/Post/renderers/list';
import type { Pid, Tid, ToPromise } from '@/shared';
import { cursorTemplate, scrollBarWidth } from '@/shared';
import { useElementRefsStore } from '@/stores/elementRefs';

import { onUnmounted, ref, watchEffect } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToggle } from '@vueuse/core';
import { Menu, MenuItem, SubMenu } from 'ant-design-vue';
import type { MenuClickEventHandler } from 'ant-design-vue/lib/menu/src/interface';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import scrollIntoView from 'scroll-into-view-if-needed';
import * as _ from 'lodash-es';

const props = defineProps<{ postPages: Array<ApiPosts['response']> }>();
const route = useRoute();
const router = useRouter();
const elementRefsStore = useElementRefsStore();
const expandedPages = ref<string[]>([]);
const selectedThreads = ref<string[]>([]);
const viewportTopmostPostDefault = { cursor: '', tid: 0, pid: 0 };
const viewportTopmostPost = ref<{ cursor: Cursor, tid: Tid, pid: Pid }>(viewportTopmostPostDefault);
const [isPostNavExpanded, togglePostNavExpanded] = useToggle(matchMedia('(min-width: 900px)').matches);

const navigate = async (cursor: Cursor, tid: string | null, pid?: Pid | string) =>
    router.replace({
        hash: `#${pid ?? (tid === null ? '' : `t${tid}`)}`,
        params: { ...route.params, cursor }
    });
const selectThread: ToPromise<MenuClickEventHandler> = async ({ domEvent, key }) => {
    if (!(domEvent.target as Element).classList.contains('post-nav-reply')) { // ignore clicks on reply link
        const [, cursor, tid] = /c(.*)-t(\d+)/u.exec(key.toString()) ?? [];
        await navigate(cursor, tid);
    }
};

const scrollStop = _.debounce(() => {
    // eslint-disable-next-line unicorn/no-array-reduce
    const findTopmostElement = (elements: Element[], topOffset = 0): Element => elements.reduce(
        (acc: { top: number, el: Element }, el: Element) => {
            const elTop = el.getBoundingClientRect().top - topOffset;

            // ignore element which its y coord is ahead of the top of viewport
            if (elTop >= 0 && acc.top > elTop)
                return { top: elTop, el };

            return acc;
        },
        { top: Infinity, el: document.createElement('null') }
    ).el;

    const viewportTopmostPostElement = {
        thread: findTopmostElement(elementRefsStore.get('<RendererList>.thread-title')),
        reply: findTopmostElement(elementRefsStore.get('<RendererList>.reply-title'), getReplyTitleTopOffset())
    };
    const viewportTopmostPostID = _.mapValues(viewportTopmostPostElement, i =>
        Number(i.parentElement?.getAttribute('data-post-id')));

    const viewportTopmostPostCursor = _.mapValues(viewportTopmostPostElement,
        i => i.closest('.post-render-list')?.getAttribute('data-cursor') ?? '');

    viewportTopmostPost.value = {
        tid: viewportTopmostPostID.thread,

        // is the topmost reply belonged to the topmost thread, true when the topmost thread has no reply,
        // the topmost reply will belong to another thread that comes after the topmost thread in view
        ..._.chain(props.postPages)
            .map(i => i.threads)
            .flatten()
            .filter({ tid: viewportTopmostPostID.thread })
            .map(i => i.replies)
            .flatten()
            .filter({ pid: viewportTopmostPostID.reply })
            .isEmpty()
            .value()
            ? { pid: 0, cursor: viewportTopmostPostCursor.thread }
            : { pid: viewportTopmostPostID.reply, cursor: viewportTopmostPostCursor.reply }
    };
}, 200);
const removeScrollEventListener = () => { document.removeEventListener('scroll', scrollStop) };
onUnmounted(removeScrollEventListener);

watchEffect(() => {
    if (!isPostNavExpanded.value || _.isEmpty(props.postPages))
        removeScrollEventListener();
    else
        document.addEventListener('scroll', scrollStop, { passive: true });
    if (isPostNavExpanded.value)
        scrollStop();
    expandedPages.value = props.postPages.map(i => `c${i.pages.currentCursor}`);
});
watchEffect(() => {
    const { cursor, tid } = viewportTopmostPost.value;
    const menuKey = `c${cursor}-t${tid}`;
    selectedThreads.value = [menuKey];

    const threadEl = document.querySelector(`.post-nav-thread[data-key='${menuKey}']`);
    if (threadEl !== null)
        scrollIntoView(threadEl, { scrollMode: 'if-needed', boundary: document.querySelector('.post-nav') });
});
</script>

<style scoped>
.post-nav-expand {
    inline-size: v-bind(scrollBarWidth);
    padding: .125rem;
    font-size: 1.3rem;
}

.post-nav {
    overflow: hidden;
}
.post-nav:hover {
    overflow-y: auto;
}
@media (min-width: 900px) {
    .post-nav:hover + .post-nav-expand {
        display: none !important;
    }
}
@media (min-width: 900px) and (max-width: 1250px) {
    /* keeping .post-nav:hover to replace .post-nav-expand with scrollbar
       without shifting when the inline-size of .post-nav excess 30% */
    .post-nav[aria-expanded=true] {
        flex: 0 1 30%;
        max-inline-size: calc(30% + v-bind(scrollBarWidth));
    }
    .post-nav:hover {
        flex-grow: 1 !important;
    }
}

@media (max-width: 900px) {
    .post-nav[aria-expanded=true], .post-nav[aria-expanded=true] + .post-nav-expand {
        position: fixed;
        z-index: 1040;
    }
    .post-nav[aria-expanded=true] {
        /* linear regression of vw,inline-size: 456,456 768,384(50%) https://www.wolframalpha.com/input?i=y%3D-0.2308x%2B561.2 */
        inline-size: calc(-0.2308 * 100vw + 561.2px - v-bind(scrollBarWidth));
    }
    .post-nav[aria-expanded=true] + .post-nav-expand {
        /* merge .post-nav-expand into the scrollbar of .post-nav */
        inset-inline-start: min(-0.2308 * 100vw + 561.2px - v-bind(scrollBarWidth) * 2, 100vw - v-bind(scrollBarWidth) * 2);
    }
    .post-nav[aria-expanded=true] + .post-nav-expand {
        /* after merge narrow the block-size from 100vh to fit-content for interactive with the scrollbar */
        block-size: auto !important;
        /* https://stackoverflow.com/questions/28455100/how-to-center-div-vertically-inside-of-absolutely-positioned-parent-div/28456704#28456704 */
        inset-block-start: 50%;
        transform: translateY(-50%);
    }
}

:deep(.post-nav-thread) {
    block-size: auto !important; /* show reply nav buttons under thread menu items */
    white-space: normal;
    line-height: 2rem;
    content-visibility: auto;
    contain-intrinsic-block-size: auto 6rem;
}

.post-nav-reply:hover {
    border-radius: var(--bs-border-radius) !important;
}
</style>
