<template>
<div class="flex flex-row gap-6 justify-between size-screen bg-white" style="font-family: 'Noto Sans SC', sans-serif;">
    <div class="flex-1 flex-col basis-1/2 m-4">
        <p>{{ useSiteConfig().name }} {{ routePath }}</p>
        <div v-if="error instanceof ApiResponseError">
            <p class="text-7xl">{{ error.errorCode }}</p>
            <template v-if="_.isString(error.errorInfo)">
                <p v-for="(info, _k) in error.errorInfo.split('\n')" :key="_k">{{ info }}</p>
            </template>
            <template v-else-if="_.isObject(error.errorInfo)">
                <p class="whitespace-pre">{{ JSON.stringify(error.errorInfo, null, 4) }}</p>
            </template>
        </div>
        <h2 v-if="firstPostPageForumName !== undefined">{{ firstPostPageForumName }}吧</h2>
        <p v-if="firstImageBase64 !== null">右侧为查询结果中第一张图片（不一定来自第一条帖子）</p>
        <p v-if="currentQueryType !== 'postID'" class="m-0">下方为查询结果中第一条主题帖/回复帖/楼中楼</p>
        <h1 v-if="firstThreadTitle !== undefined">{{ firstThreadTitle }}</h1>
        <NewlineToBr is="h3" :text="firstPostContentTexts" wrapInSpan class="h-auto" />
        <template v-for="author in [firstPostAuthor]">
            <div :key="author.uid" v-if="author !== undefined" class="m-auto">
                <img v-if="authorPortraitImageBase64 !== null" :src="authorPortraitImageBase64" />
                <span v-if="author.name !== null">{{ author.name }}</span>
                <span v-if="author.displayName !== null">{{ author.displayName }}</span>
                <span v-if="author.name === null && author.displayName === null">{{ author.uid }}</span>
            </div>
        </template>
    </div>
    <div v-if="firstImageBase64 !== null" class="flex-auto basis-1/4">
        <img :src="firstImageBase64" class="h-screen object-contain" />
    </div>
</div>
</template>

<script setup lang="ts">
import type { UnwrapRef } from 'vue';
import _ from 'lodash';

const { currentQueryType, queryParam, initialPageCursor } = defineProps<{
    routePath: string,
    currentQueryType: UnwrapRef<QueryFormDeps['currentQueryType']>,
    queryParam: ApiPosts['queryParam'] | undefined,
    initialPageCursor?: string
}>();
const { data, error } = useApiPosts(ref(queryParam), { initialPageParam: initialPageCursor });

const { firstPostPage, firstPostPageForumName, firstThread } = useFirstPost(data);
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

const fetchImageAsBase64 = async (url: string): Promise<string | null> => {
    // eslint-disable-next-line @typescript-eslint/init-declarations
    let timeoutId: NodeJS.Timeout;
    const abortController = new AbortController();

    return Promise.race([
        new Promise<null>(resolve => { timeoutId = setTimeout(() => { resolve(null) }, 5000) }),
        (async () => {
            try {
                // https://github.com/vercel/satori/issues/626#issuecomment-2401402201
                const response = await fetch(url, { signal: abortController.signal });
                const arrayBuffer = await response.arrayBuffer();
                const base64 = Buffer.from(arrayBuffer).toString('base64');
                const type = response.headers.get('content-type');

                return `data:${type ?? 'image/png'};base64,${base64}`;
            } catch {
                return null;
            }
        })()
    ]).finally(() => { clearTimeout(timeoutId); abortController.abort() });
};
const authorPortraitImageBase64 = computedAsync(async () =>
    (firstPostAuthor.value === undefined ? null : await fetchImageAsBase64(toUserPortraitImageUrl(firstPostAuthor.value.portrait))));
const firstImageBase64 = computedAsync(async () =>
    (firstImage.value?.originSrc === undefined ? null : await fetchImageAsBase64(firstImage.value.originSrc)));
</script>
