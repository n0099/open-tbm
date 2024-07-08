<template>
<AMenu
    v-model:selectedKeys="selectedThreads" v-model:openKeys="expandedPages" @click="e => selectThread(e)"
    forceSubMenuRender :inlineIndent="16" mode="inline"
    :class="{ 'd-none': !isPostNavExpanded }" :aria-expanded="isPostNavExpanded"
    class="post-nav col p-0 vh-100 sticky-top border-0">
    <template v-for="posts in postPages">
        <ASubMenu
            v-for="cursor in [posts.pages.currentCursor]"
            :key="`c${cursor}`" :eventKey="`c${cursor}`" :title="cursorTemplate(cursor)">
            <AMenuItem
                v-for="thread in posts.threads" :key="threadMenuKey(cursor, thread.tid)"
                :data-key="threadMenuKey(cursor, thread.tid)" :title="thread.title"
                :class="menuThreadClasses(thread)" class="post-nav-thread border ps-2 ps-lg-3 pe-1">
                {{ thread.title }}
                <div class="d-block btn-group p-1 text-wrap" role="group">
                    <template v-for="reply in thread.replies" :key="reply.pid">
                        <NuxtLink
                            @click.prevent="_ => navigate(cursor, null, reply.pid)"
                            :data-pid="reply.pid" :to="routeHash(null, reply.pid)"
                            :class="menuReplyClasses(reply)" class="post-nav-reply btn ms-0 px-2">
                            {{ reply.floor }}L
                        </NuxtLink>
                    </template>
                </div>
            </AMenuItem>
        </ASubMenu>
    </template>
</AMenu>
<div
    :class="{
        'border-start': isPostNavExpanded,
        'border-end': !isPostNavExpanded
    }"
    class="post-nav-expand col-auto align-items-center d-flex vh-100 sticky-top border-light-subtle">
    <a
        v-if="!useHydrationStore().isHydratingOrSSR()"
        @click="_ => togglePostNavExpanded()" class="text-primary">
        <FontAwesome v-show="isPostNavExpanded" :icon="faAngleLeft" />
        <FontAwesome v-show="!isPostNavExpanded" :icon="faAngleRight" />
    </a>
</div>
</template>

<script setup lang="ts">
import type { MenuClickEventHandler } from 'ant-design-vue/lib/menu/src/interface';
import scrollIntoView from 'scroll-into-view-if-needed';
import { faAngleLeft, faAngleRight } from '@fortawesome/free-solid-svg-icons';
import _ from 'lodash';

const props = defineProps<{ postPages: Array<ApiPosts['response']> }>();
const route = useRoute();
const router = useRouter();
const elementRefsStore = useElementRefsStore();
const highlightPostStore = useHighlightPostStore();
const expandedPages = ref<string[]>([]);
const selectedThreads = ref<string[]>([]);
const viewportTopmostPost = ref<{ cursor: Cursor, tid: Tid, pid: Pid }>({ cursor: '', tid: 0, pid: 0 });

const [isPostNavExpanded, togglePostNavExpanded] = useToggle(true);
onMounted(() => togglePostNavExpanded(matchMedia('(min-width: 900px)').matches));
const noScriptStyle = `<style>
    @media (max-width: 900px) {
        .post-nav {
            display: none;
        }
    }
</style>`; // https://github.com/nuxt/nuxt/issues/13848
useHead({ noscript: [{ innerHTML: noScriptStyle }] });
const postNavDisplay = ref('none'); // using media query in css instead of js before hydrate
onMounted(() => { postNavDisplay.value = 'unset' });

const threadMenuKey = (cursor: Cursor, tid: Tid) => `c${cursor}-t${tid}`;
const routeHash = (tid: Tid | string | null, pid?: Pid | string) => `#${pid ?? (tid === null ? '' : `t${tid}`)}`;
const navigate = async (cursor: Cursor, tid: string | null, pid?: Pid | string) =>
    router.replace({
        hash: routeHash(tid, pid),
        params: { ...route.params, cursor }
    });
const selectThread: ToPromise<MenuClickEventHandler> = async ({ domEvent, key }) => {
    if (!(domEvent.target as Element).classList.contains('post-nav-reply')) { // ignore clicks on reply link
        const [, cursor, tid] = /c(.*)-t(\d+)/u.exec(key.toString()) ?? [];
        await navigate(cursor, tid);
    }
};

const menuThreadClasses = (thread: Thread) => {
    if (useHydrationStore().isHydrating())
        return 'border-only-bottom border-bottom';
    const isRouteHash = route.hash === routeHash(thread.tid);
    const isHighlighting = highlightPostStore.isHighlightingPost(thread, 'tid');

    return { /* eslint-disable @typescript-eslint/naming-convention */
        'border-only-bottom': !(isRouteHash || isHighlighting),
        'border-primary': isRouteHash,
        'border-bottom': !isRouteHash,
        'border-warning': isHighlighting
    };
    /* eslint-enable @typescript-eslint/naming-convention */
};
const menuReplyClasses = (reply: Reply) => {
    if (useHydrationStore().isHydrating())
        return 'btn-light text-body-secondary';
    const isRouteHash = route.hash === routeHash(null, reply.pid);
    const isHighlighting = highlightPostStore.isHighlightingPost(reply, 'pid');
    const isTopmost = reply.pid === viewportTopmostPost.value.pid;

    return { /* eslint-disable @typescript-eslint/naming-convention */
        ...keysWithSameValue(['rounded-3', 'btn-info', 'text-white'], isTopmost),
        ...keysWithSameValue(['btn-light', 'text-body-secondary'], !isTopmost),
        'btn-outline-warning': isHighlighting,
        'btn-outline-primary': !isTopmost && isRouteHash
    };
    /* eslint-enable @typescript-eslint/naming-convention */
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
        thread: findTopmostElement(elementRefsStore.get('<PostRendererList>.thread-title')),
        reply: findTopmostElement(elementRefsStore.get('<PostRendererList>.reply-title'), getReplyTitleTopOffset())
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
    expandedPages.value = props.postPages.map(i => `c${i.pages.currentCursor}`);
    if (!import.meta.client)
        return;
    if (!isPostNavExpanded.value || _.isEmpty(props.postPages))
        removeScrollEventListener();
    else
        document.addEventListener('scroll', scrollStop, { passive: true });
    if (isPostNavExpanded.value)
        scrollStop();
});
watch(viewportTopmostPost, (to, from) => {
    if (_.isEqual(_.omit(to, 'pid'), _.omit(from, 'pid')))
        return;
    const { cursor, tid } = to;
    const menuKey = threadMenuKey(cursor, tid);
    selectedThreads.value = [menuKey];

    if (!import.meta.client)
        return;
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
    .post-nav {
        display: v-bind(postNavDisplay);
    }
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
:deep(.post-nav-thread.border-only-bottom) { /* invisible border to prevent reflow triggered by using border-width: 0px */
    border-top-color: transparent !important;
    border-left-color: transparent !important;
    border-right-color: transparent !important;
}

.post-nav-reply:hover {
    border-radius: var(--bs-border-radius) !important;
}
</style>
