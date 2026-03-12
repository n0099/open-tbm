import type { InfiniteData } from '@tanstack/vue-query';

export const usePostsSEO = (
    data: Ref<InfiniteData<ApiPosts['response']> | undefined>,
    currentQueryType: QueryFormDeps['currentQueryType']
) => {
    const firstPostPage = computed(() => data.value?.pages[0]);
    const firstPostPageForumName = computed((): string | undefined => {
        if (firstPostPage.value?.forums === undefined)
            return undefined;

        const forumNames = Object.values(firstPostPage.value.forums);
        if (forumNames.length === 1)
            return forumNames[0];

        return undefined;
    });
    const firstThread = computed(() => firstPostPage.value?.threads[0]);
    useHead({
        title: computed(() => {
            const titleParts = ['帖子查询'];
            if (currentQueryType.value !== 'empty') {
                if (['fid', 'postID'].includes(currentQueryType.value) && firstPostPageForumName.value !== undefined)
                    titleParts.unshift(`${firstPostPageForumName.value}吧`);
                if (currentQueryType.value === 'postID' && firstThread.value?.title !== undefined)
                    titleParts.unshift(firstThread.value.title);
            }

            return titleParts.join(' - ');
        })
    });
    (() => {
        const route = useRoute();
        const firstThreadTitle = computed(() => firstThread.value?.title);
        const firstReplyContent = computed(() => firstThread.value?.replies[0]);
        const firstSubReplyContent = computed(() => firstReplyContent.value?.subReplies[0]);
        const firstPostContentTexts = computed(() =>
            extractContentTexts((firstSubReplyContent.value ?? firstReplyContent.value)?.content));
        const getUser = computed(() => baseGetUser(firstPostPage.value?.users ?? []));
        const firstPostAuthor = computed(() => undefinedOr(
            (firstSubReplyContent.value ?? firstReplyContent.value)?.authorUid,
            uid => getUser.value(uid)
        ));
        const firstImage = computed(() => firstPostPage.value
            ?.threads.flatMap(thread =>
                thread.replies.flatMap(reply =>
                    reply.content?.find(i => i.type === 3)))
            .find(i => i !== undefined));

        // https://github.com/nuxt-modules/og-image/blob/39412488d08af4d27cb3a7881d4cf44a773fbb3c/src/runtime/server/util/kit.ts#L13
        // https://github.com/nuxt/nuxt/issues/22786
        defineOgImageComponent('Post', {
            routePath: route.path,
            currentQueryType,
            firstPostPageForumName,
            firstThreadTitle,
            firstPostContentTexts,
            firstPostAuthor,
            firstImage
        });
    })();
    usePostsSchemaOrg(data);
};
