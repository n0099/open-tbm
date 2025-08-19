<template>
<nav
    class="post-nav col p-0 sticky-top border-0"
    :class="{ 'd-none': !isPostNavExpanded }" :aria-expanded="isPostNavExpanded">
    <AMenu
        v-model:selectedKeys="selectedThreads" v-model:openKeys="expandedPages" @click="selectThread($event)"
        forceSubMenuRender :inlineIndent="16" mode="inline">
        <template v-for="posts in data?.pages ?? []">
            <ASubMenu
                v-for="cursor in [posts.pages.currentCursor]"
                :key="pageMenuKey(cursor)" :eventKey="pageMenuKey(cursor)" :title="cursorTemplate(cursor)">
                <AMenuItem
                    v-for="thread in posts.threads" :key="threadMenuKey(cursor, thread.tid)"
                    ref="threadMenuItemsRef" :title="thread.title"
                    :class="menuThreadClasses(thread)" class="post-nav-thread border ps-2 ps-lg-3 pe-1">
                    {{ thread.title }}
                    <div class="d-block btn-group p-1 text-wrap" role="group">
                        <template v-for="reply in thread.replies" :key="reply.pid">
                            <NuxtLink
                                @click.prevent="navigate(cursor, reply)" :to="routeHash(reply)"
                                :class="menuReplyClasses(cursor, reply)" class="post-nav-reply btn ms-0 px-2">
                                {{ reply.floor }}L
                            </NuxtLink>
                        </template>
                    </div>
                </AMenuItem>
            </ASubMenu>
        </template>
    </AMenu>
</nav>
<div
    :class="{
        'border-start': isPostNavExpanded,
        'border-end': !isPostNavExpanded
    }"
    class="post-nav-expand col-auto align-items-center d-flex sticky-top border-light-subtle">
    <a
        v-if="!hydrationStore.isHydratingOrSSR"
        @click="togglePostNavExpanded()" class="text-primary">
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

const { queryParam } = defineProps<{ queryParam?: ApiPosts['queryParam'] }>();
const route = useRoute();
const router = useRouter();
const highlightPostStore = useHighlightPostStore();
const { viewportTopmostPost } = storeToRefs(useViewportTopmostPostStore());
const hydrationStore = useHydrationStore();
const { data } = useApiPosts(computed(() => queryParam));
const expandedPages = ref<string[]>([]);
const selectedThreads = ref<[string]>();
const threadMenuItemsRef = useTemplateRef('threadMenuItemsRef');

useNoScript(`<style>
    /* cannot use logical property as overriding existing physical property */
    .post-nav > .ant-menu-root {
        padding-left: 0;
    }
    @media (max-width: 900px) {
        .post-nav {
            display: none;
        }
    }
</style>`);
const [isPostNavExpanded, togglePostNavExpanded] = useToggle(true);
onMounted(() => togglePostNavExpanded(matchMedia('(min-width: 900px)').matches));
const postNavDisplay = ref('none'); // using media query in css instead of js before hydrate
onMounted(() => { postNavDisplay.value = 'unset' });

type PostIdObj = Partial<Record<PostIDStr, string | number>>;
const routeHash = (postIdObj: PostIdObj) => {
    if (postIdObj.spid !== undefined)
        return `#spid/${postIdObj.spid}`;
    if (postIdObj.pid !== undefined)
        return `#pid/${postIdObj.pid}`;
    if (postIdObj.tid !== undefined)
        return `#tid/${postIdObj.tid}`;

    throw new Error(JSON.stringify(postIdObj));
};
const navigate = async (cursor: Cursor, postIdObj: PostIdObj) =>
    router.replace({
        hash: routeHash(postIdObj),
        params: { ...route.params, cursor }
    });
const cursorKey = (cursor?: Cursor) => `cursor/${cursor ?? ''}`;
const pageMenuKey = (cursor?: Cursor) => `${queryParam?.query}/${cursorKey(cursor)}`;
const threadMenuKey = (cursor: Cursor | undefined, tid: Tid) => `${cursorKey(cursor)}/tid/${tid}`;
const selectThread: ToPromise<MenuClickEventHandler> = async ({ domEvent, key }) => {
    if (!(domEvent.target as Element).classList.contains('post-nav-reply')) { // ignore clicks on reply link
        const [, cursor, tid] = /c(.*)-t(\d+)/u.exec(key.toString()) ?? [];
        await navigate(cursor ?? throwError(), { tid });
    }
};

const menuThreadClasses = (thread: Thread) => {
    if (hydrationStore.isHydrating)
        return 'border-only-bottom border-bottom';
    const isRouteHash = route.hash === routeHash(thread);
    const isHighlighting = highlightPostStore.isHighlightingPost(thread, 'tid');

    return { /* eslint-disable @typescript-eslint/naming-convention */
        'border-only-bottom': !(isRouteHash || isHighlighting),
        'border-primary': isRouteHash,
        'border-bottom': !isRouteHash,
        'border-warning': isHighlighting
    };
    /* eslint-enable @typescript-eslint/naming-convention */
};
const menuReplyClasses = (cursor: Cursor, reply: Reply) => {
    if (hydrationStore.isHydrating)
        return 'btn-light text-body-secondary';
    const isRouteHash = route.hash === routeHash(reply);
    const isHighlighting = highlightPostStore.isHighlightingPost(reply, 'pid');
    const isTopmost = viewportTopmostPost.value?.cursor === cursor
        && viewportTopmostPost.value.tid === reply.tid
        && viewportTopmostPost.value.pid === reply.pid;

    return { /* eslint-disable @typescript-eslint/naming-convention */
        ...keysWithSameValue(['rounded-3', 'btn-info', 'text-white'], isTopmost),
        ...keysWithSameValue(['btn-light', 'text-body-secondary'], !isTopmost),
        'btn-outline-warning': isHighlighting,
        'btn-outline-primary': !isTopmost && isRouteHash
    };
    /* eslint-enable @typescript-eslint/naming-convention */
};

const expandSiblingPagesByCursor = (cursor: Cursor) => {
    const pages = data.value?.pages;
    const pageIndex = pages?.findIndex(page => page.pages.currentCursor === cursor);
    if (pageIndex === undefined || pageIndex === -1)
        return;
    expandedPages.value = [pages?.[pageIndex - 1], pages?.[pageIndex], pages?.[pageIndex + 1]]
        .filter(page => page !== undefined)
        .map(page => page.pages.currentCursor)
        .map(pageMenuKey);
};
watchImmediate(() => data.value?.pages, () => {
    expandSiblingPagesByCursor(getRouteCursorParam(route));
});
watch(viewportTopmostPost, (to, from) => {
    if (to === undefined)
        return;
    const { cursor, tid } = to;
    if (_.isEqual(_.omit(to, 'pid'), _.omit(from, 'pid')))
        return;
    const menuKey = threadMenuKey(cursor, tid);
    selectedThreads.value = [menuKey];

    if (!import.meta.client)
        return;
    expandSiblingPagesByCursor(cursor);
    const threadEl = (threadMenuItemsRef.value
        ?.find(i => i?.$.vnode.key === menuKey)
        ?.$el as Element | null)
        ?.nextElementSibling ?? null;
    if (threadEl !== null)
        scrollIntoView(threadEl, { scrollMode: 'if-needed', boundary: document.querySelector('.post-nav') });
});
</script>

<style scoped>
:deep(.post-nav-thread) {
    block-size: auto !important; /* show reply nav buttons under thread menu items */
    white-space: normal;
    line-height: 2rem;
    content-visibility: auto;
    contain-intrinsic-block-size: auto 6rem;
}
:deep(.post-nav-thread.border-only-bottom) { /* invisible border to prevent reflow triggered by using border-width: 0px */
    border-block-start-color: transparent !important;
    border-inline-start-color: transparent !important;
    border-inline-end-color: transparent !important;
}

.post-nav {
    overflow: hidden;
}
.post-nav, .post-nav-expand {
    max-height: 100dvh;
}
.post-nav:hover {
    overflow-y: auto;
}

.post-nav-expand {
    inline-size: v-bind(scrollBarWidth);
    padding: .125rem;
    font-size: 1.3rem;
}
.post-nav-reply:hover {
    border-radius: var(--bs-border-radius) !important;
}

@media (width >= 900px) {
    .post-nav:hover + .post-nav-expand {
        display: none !important;
    }
}
@media (width >= 900px) and (width <= 1250px) {
    /* keeping .post-nav:hover to replace .post-nav-expand with scrollbar
       without shifting when the inline-size of .post-nav excess 30% */
    .post-nav[aria-expanded="true"] {
        flex: 0 1 30%;
        max-inline-size: calc(30% + v-bind(scrollBarWidth));
    }
    .post-nav:hover {
        flex-grow: 1 !important;
    }
}
@media (width <= 900px) {
    .post-nav {
        display: v-bind(postNavDisplay);
    }
    .post-nav[aria-expanded="true"], .post-nav[aria-expanded="true"] + .post-nav-expand {
        position: fixed;
        z-index: 1040;
    }
    .post-nav[aria-expanded="true"] {
        /* linear regression of {dvw,inline-size}: {{456,456},{768,384(50%)}} https://www.wolframalpha.com/input?i=linear+regression+%7B%7B456%2C456%7D%2C%7B768%2C384%7D%7D */
        inline-size: calc(-0.2308 * 100dvw + 561.2px - v-bind(scrollBarWidth));
    }
    .post-nav[aria-expanded="true"] + .post-nav-expand {
        /* merge .post-nav-expand into the scrollbar of .post-nav */
        inset-inline-start: min(-0.2308 * 100dvw + 561.2px - v-bind(scrollBarWidth) * 2, 100dvw - v-bind(scrollBarWidth) * 2);
        /* after merge narrow the block-size from 100dvh to fit-content for interactive with the scrollbar */
        block-size: auto !important;
        /* https://stackoverflow.com/questions/28455100/how-to-center-div-vertically-inside-of-absolutely-positioned-parent-div/28456704#28456704 */
        inset-block-start: 50%;
        transform: translateY(-50%);
    }
}
</style>
