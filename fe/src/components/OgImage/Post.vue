<template>
<div class="flex flex-row justify-between size-screen">
    <div class="m-6">
        <p>{{ useSiteConfig().name }} {{ routePath }}</p>
        <h2>{{ firstPostPageForum?.name }}吧</h2>
        <template v-if="currentQueryType !== 'postID'">
            <p class="m-0">右侧为查询结果中第一张图片（不一定来自第一条帖子）</p>
            <p class="m-0">下方为查询结果中第一条主题帖/回复帖/楼中楼</p>
        </template>
        <h1>{{ firstThread?.title }}</h1>
        <h3 class="h-auto">{{ firstPostContentTexts }}</h3>
        <template v-for="author in [firstPostAuthor]">
            <div :key="author.uid" v-if="author !== undefined" class="m-auto">
                <img :src="toUserPortraitImageUrl(author.portrait)" class="size-24" />
                <span v-if="author.name !== null">{{ author.name }}</span>
                <span v-if="author.displayName !== null">{{ author.displayName }}</span>
                <span v-if="author.name === null && author.displayName === null">{{ author.uid }}</span>
            </div>
        </template>
    </div>
    <img
        v-if="firstImage !== undefined"
        :src="imageUrl(firstImage?.originSrc)"
        class="h-screen object-contain" />
</div>
</template>

<script setup lang="ts">
import type { UnwrapRef } from 'vue';

const props = defineProps<{
    routePath: string,
    firstPostPage?: ApiPosts['response'],
    firstPostPageForum?: ApiPosts['response']['forum'],
    firstThread?: ApiPosts['response']['threads'][number],
    currentQueryType: UnwrapRef<QueryFormDeps['currentQueryType']>
}>();
const firstReplyContent = computed(() => props.firstThread?.replies[0]);
const firstSubReplyContent = computed(() => firstReplyContent.value?.subReplies[0]);
const firstPostContentTexts = computed(() =>
    extractContentTexts((firstSubReplyContent.value ?? firstReplyContent.value)?.content));
const getUser = computed(() => baseGetUser(props.firstPostPage?.users ?? []));
const firstPostAuthor = computed(() => undefinedOr(
    (firstSubReplyContent.value ?? firstReplyContent.value)?.authorUid,
    uid => getUser.value(uid)
));
const firstImage = computed(() => props.firstPostPage
    ?.threads.flatMap(thread =>
        thread.replies.flatMap(reply =>
            reply.content?.find(i => i.type === 3)))
    .find(i => i !== undefined));
</script>
