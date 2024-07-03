<template>
<div>
    <div class="container">
        <LazyPostQueryForm :isLoading="isFetching" :queryFormDeps="queryFormDeps" />
        <AMenu v-if="!_.isEmpty(data?.pages)" v-model:selectedKeys="selectedRenderTypes" mode="horizontal">
            <AMenuItem key="list">列表视图</AMenuItem>
            <AMenuItem key="table">表格视图</AMenuItem>
        </AMenu>
    </div>
    <div v-if="!(data === undefined || _.isEmpty(data.pages) || _.isEmpty(route.params))" class="container-fluid">
        <div class="row flex-nowrap">
            <LazyPostNav v-if="renderType === 'list'" :postPages="data.pages" />
            <div class="post-page col mx-auto ps-0" :class="{ 'renderer-list': renderType === 'list' }">
                <PostPage
                    v-for="(page, pageIndex) in data.pages" :key="page.pages.currentCursor"
                    @clickNextPage="async () => await fetchNextPage()"
                    :posts="page" :renderType="renderType"
                    :isFetching="isFetching" :hasNextPage="hasNextPage"
                    :isLastPageInPages="pageIndex === data.pages.length - 1"
                    :nextPageRoute="getNextCursorRoute(route, page.pages.nextCursor)" />
            </div>
            <div v-if="renderType === 'list'" class="col d-none d-xxl-block p-0" />
        </div>
    </div>
    <div class="container">
        <PlaceholderError :error="error" class="border-top" />
        <PlaceholderPostList v-show="isPending || isFetchingNextPage" :isLoading="isFetching" />
    </div>
</div>
</template>

<script setup lang="ts">
import type { Comment, Person } from 'schema-dts';
import type { RouteLocationNormalized } from 'vue-router';
import { DateTime } from 'luxon';
import _ from 'lodash';

export type PostRenderer = 'list' | 'table';

const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();
const queryParam = ref<ApiPosts['queryParam']>();
const shouldFetch = ref(false);
const initialPageCursor = ref<Cursor>('');
const { data, error, isPending, isFetching, isFetched, dataUpdatedAt, errorUpdatedAt, fetchNextPage, isFetchingNextPage, hasNextPage } =
    useApiPosts(queryParam, { initialPageParam: initialPageCursor, enabled: shouldFetch });
const selectedRenderTypes = ref<[PostRenderer]>(['list']);
const renderType = computed(() => selectedRenderTypes.value[0]);
const queryFormDeps = getQueryFormDeps();
const { currentQueryType, parseRouteToGetFlattenParams } = queryFormDeps;

const firstPostPage = computed(() => data.value?.pages[0]);
const firstPostPageForum = computed(() => firstPostPage.value?.forum);
const firstThread = computed(() => firstPostPage.value?.threads[0]);
useHead({
    title: computed(() => {
        if (firstPostPage.value === undefined)
            return '帖子查询';
        switch (currentQueryType.value) {
            case 'fid':
            case 'search':
                return `${firstPostPageForum.value?.name}吧 - 帖子查询`;
            case 'postID':
                return `${firstThread.value?.title} - ${firstPostPageForum.value?.name}吧 - 帖子查询`;
            default:
                return '帖子查询';
        }
    })
});
defineOgImageComponent('Post', { routePath: route.path, firstPostPage, firstPostPageForum, firstThread, currentQueryType });

// https://schema.org/Comment
/* eslint-disable @typescript-eslint/naming-convention */
const baseUrlWithDomain = useSiteConfig().url;
const definePostComment = <T extends Post>(post: T, postIDKey: keyof T & PostIDOf<T>): Comment => ({
    '@type': 'Comment',
    '@id': (post[postIDKey] as Tid | Pid | Spid).toString(),
    url: baseUrlWithDomain + router.resolve({
        name: `posts/${postIDKey}`,
        params: { [postIDKey]: post[postIDKey] as Tid | Pid | Spid }
    }).fullPath,
    dateCreated: DateTime.fromSeconds(post.createdAt).toISO(),
    datePublished: DateTime.fromSeconds(post.postedAt).toISO(),
    upvoteCount: post.agreeCount,
    downvoteCount: post.disagreeCount
});
const userToPerson = (uid: BaiduUserID): Exclude<Person, string> => ({
    '@type': 'Person',
    '@id': uid.toString(),
    url: baseUrlWithDomain + router.resolve(toUserRoute(uid)).fullPath
});
const definePostAuthorPerson = (post: Post, { getUser }: UserProvision): Pick<Comment, 'author'> => {
    const uid = post.authorUid;
    const user = getUser(uid);

    return {
        author: {
            ...userToPerson(uid),
            name: [user.name, user.displayName].filter(i => i !== null),
            image: toUserPortraitImageUrl(user.portrait),
            sameAs: toUserProfileUrl(user)
        }
    };
};
const defineThreadComment = (thread: Thread, userProvision: UserProvision): Comment => ({
    ...definePostComment(thread, 'tid'),
    ...definePostAuthorPerson(thread, userProvision),
    sameAs: tiebaPostLink(thread.tid),
    headline: thread.title,
    commentCount: thread.replyCount
});
const extractContentImagesUrl = (content: PostContent | null) => {
    const ret = content?.filter(i => i.type === 3).map(i => imageUrl(i.originSrc)).filter(i => i !== undefined);

    return _.isEmpty(ret) ? undefined : ret;
};
const extractContentUserMentions = (content: PostContent | null) => {
    const ret = content?.filter(i => i.type === 4).filter(i => i.uid !== undefined)
        // eslint-disable-next-line @typescript-eslint/no-non-null-assertion
        .map((i): Person => ({ ...userToPerson(i.uid!), name: i.text }));

    return _.isEmpty(ret) ? undefined : ret;
};
const postContentToComment = (content: PostContent | null): Partial<Comment> => ({
    text: extractContentTexts(content),
    image: extractContentImagesUrl(content),
    mentions: extractContentUserMentions(content)
});
const defineReplyComment = (reply: Reply, userProvision: UserProvision): Comment => ({
    ...definePostComment(reply, 'pid'),
    ...definePostAuthorPerson(reply, userProvision),
    sameAs: tiebaPostLink(reply.tid, reply.pid),
    parentItem: { '@type': 'Comment', '@id': reply.tid.toString() },
    ...postContentToComment(reply.content),
    commentCount: reply.subReplyCount
});
const defineSubReplyComment = (userProvision: UserProvision) => (subReply: SubReply): Comment => ({
    ...definePostComment(subReply, 'spid'),
    ...definePostAuthorPerson(subReply, userProvision),
    sameAs: tiebaPostLink(subReply.tid, subReply.pid, subReply.spid),
    parentItem: { '@type': 'Comment', '@id': subReply.pid.toString() },
    ...postContentToComment(subReply.content)
});
/* eslint-enable @typescript-eslint/naming-convention */
useSchemaOrg(computed(() => data.value?.pages.flatMap(page => {
    const getUser = baseGetUser(page.users);
    const renderUsername = baseRenderUsername(getUser);

    return page.threads.flatMap(thread => [
        defineThreadComment(thread, { getUser, renderUsername }),
        ...thread.replies.flatMap(reply => [
            defineReplyComment(reply, { getUser, renderUsername }),
            ...reply.subReplies.map(defineSubReplyComment({ getUser, renderUsername }))
        ])
    ]);
})));

const queryStartedAtSSR = useState('postsQuerySSRStartTime', () => 0);
let queryStartedAt = 0;
watchSyncEffect(() => {
    if (!isFetching.value)
        return;
    if (import.meta.server)
        queryStartedAtSSR.value = Date.now();
    if (import.meta.client)
        queryStartedAt = Date.now();
});
watch([dataUpdatedAt, errorUpdatedAt], async (updatedAt: UnixTimestamp[]) => {
    const maxUpdatedAt = Math.max(...updatedAt);
    if (maxUpdatedAt === 0) // just starts to fetch, defer watching to next time
        return;
    const isQueriedBySSR = queryStartedAtSSR.value !== 0 && queryStartedAt === 0;
    if (isQueriedBySSR)
        queryStartedAt = queryStartedAtSSR.value;
    const isQueryCached = maxUpdatedAt < queryStartedAt;
    const networkDuration = isQueryCached ? 0 : maxUpdatedAt - queryStartedAt;
    await nextTick(); // wait for child components to finish dom update
    const renderDuration = Date.now() - queryStartedAt - networkDuration;

    const fetchedPage = data.value?.pages.find(i =>
        i.pages.currentCursor === getRouteCursorParam(route));
    const postCount = _.sum(Object.values(fetchedPage?.pages.matchQueryPostCount ?? {}));
    notyShow('success', `已加载${postCount}条记录
        前端耗时${_.round(renderDuration / 1000, 2)}s
        ${isQueriedBySSR ? '使用服务端渲染预请求' : ''}
        ${isQueryCached ? '使用前端本地缓存' : `后端+网络耗时${_.round(networkDuration / 1000, 2)}s`}`);
});
watch(isFetched, async () => {
    if (isFetched.value && renderType.value === 'list') {
        await nextTick();
        scrollToPostListItemByRoute(route);
    }
});
if (import.meta.server) {
    const nuxt = useNuxtApp();
    watchOnce(error, () => {
        void nuxt.runWithContext(() => { responseWithError(error.value) });
    }, { flush: 'sync' });
}

const parseRouteThenFetch = async (newRoute: RouteLocationNormalized) => {
    const setQueryParam = (newQueryParam?: ApiPosts['queryParam']) => {
        // prevent fetch with queryParam that's empty or parsed from invalid route
        shouldFetch.value = newQueryParam !== undefined;
        queryParam.value = newQueryParam;
    };
    const flattenParams = await parseRouteToGetFlattenParams(newRoute);
    if (flattenParams === false) {
        setQueryParam();

        return;
    }

    /** {@link initialPageCursor} only take effect when the queryKey of {@link useQuery()} is changed */
    initialPageCursor.value = getRouteCursorParam(newRoute);
    setQueryParam({ query: JSON.stringify(flattenParams) });
};

/** {@link onBeforeRouteUpdate()} fires too early for allowing navigation guard to cancel updating */
// but we don't need cancelling and there's no onAfterRoute*() events instead of navigation guard available
/** watch on {@link useRoute()} directly won't get reactive since it's not returning {@link ref} but a plain {@link Proxy} */
// https://old.reddit.com/r/Nuxt/comments/15bwb24/how_to_watch_for_route_change_works_on_dev_not/jtspj6b/
/** watch deeply on object {@link route.query} and {@link route.params} for nesting params */
/** and allowing reconstruct partial route to pass it as a param of {@link compareRouteIsNewQuery()} */
/** ignoring string {@link route.name} or {@link route.path} since switching root level route */
// will unmounted the component of current page route and unwatch this watcher
watchDeep(() => [route.query, route.params], async (_discard, oldQueryAndParams) => {
    const [to, from] = [route, { query: oldQueryAndParams[0], params: oldQueryAndParams[1] } as RouteLocationNormalized];
    const isTriggeredByQueryForm = useTriggerRouteUpdateStore()
        .isTriggeredBy('<PostQueryForm>@submit', _.merge(to, { force: true }));
    if (to.hash === '' && (isTriggeredByQueryForm || compareRouteIsNewQuery(to, from)))
        void nextTick(() => { window.scrollTo({ top: 0 }) });
    await parseRouteThenFetch(to);

    /** must invoke {@link parseRouteThenFetch()} before {@link queryClient.resetQueries()} */
    /** to prevent refetch the old route when navigating to different route aka {@link compareRouteIsNewQuery()} is true */
    if (isTriggeredByQueryForm && !compareRouteIsNewQuery(to, from))
        await queryClient.resetQueries({ queryKey: ['posts'] });
});
await parseRouteThenFetch(route);
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
