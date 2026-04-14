import type { InfiniteData } from '@tanstack/vue-query';

export const useFirstPost = (data: Ref<InfiniteData<ApiPosts['response']> | undefined>) => {
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

    return { firstPostPage, firstPostPageForumName, firstThread };
};

export const usePostsSEO = (
    currentQueryType: QueryFormDeps['currentQueryType'],
    queryParam: Ref<ApiPosts['queryParam'] | undefined>,
    initialPageCursor: Ref<string>,
    data: Ref<InfiniteData<ApiPosts['response']> | undefined>
) => {
    const { firstPostPageForumName, firstThread } = useFirstPost(data);
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
    watchSyncEffect(() => defineOgImage('Post', {
        // https://github.com/nuxt-modules/og-image/blob/39412488d08af4d27cb3a7881d4cf44a773fbb3c/src/runtime/server/util/kit.ts#L13
        // https://github.com/nuxt/nuxt/issues/22786
        routePath: useRoute().path,
        currentQueryType,
        queryParam,
        initialPageCursor
    }));
    usePostsSchemaOrg(data);
};
