import type { InfiniteData } from '@tanstack/vue-query';

export const usePostsSEO = (
    data: Ref<InfiniteData<ApiPosts['response']> | undefined>,
    currentQueryType: QueryFormDeps['currentQueryType']
) => {
    const route = useRoute();
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
    defineOgImageComponent('Post', { routePath: route.path, firstPostPage, firstPostPageForumName, firstThread, currentQueryType });
    usePostsSchemaOrg(data);
};
