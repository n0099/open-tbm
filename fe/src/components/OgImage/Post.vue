<template>
<div class="flex flex-row justify-between size-screen">
    <div class="m-8">
        <h2>{{ firstPostPageForum?.name }}吧</h2>
        <h3 v-if="queryType !== 'postID'">下方为查询结果中第一条主题帖及其回复帖 右侧为查询结果中第一张图片</h3>
        <h1>{{ firstThread?.title }}</h1>
        <h3>{{ firstReplyContentTexts }}</h3>
    </div>
    <img
        v-if="firstImage !== undefined"
        :src="`https://imgsrc.baidu.com/forum/pic/item/${firstImage?.originSrc}.jpg`"
        class="h-screen object-contain" />
</div>
</template>

<script setup lang="ts">
const props = defineProps<{
    firstPostPage?: ApiPosts['response'],
    firstPostPageForum?: ApiPosts['response']['forum'],
    firstThread?: ApiPosts['response']['threads'][number],
    queryType: ReturnType<ReturnType<typeof getQueryFormDeps>['getCurrentQueryType']>
}>();
const firstReplyContent = computed(() => props.firstThread?.replies[0].content);
const firstReplyContentTexts = computed(() => firstReplyContent.value
    ?.reduce((acc, i) => acc + ('text' in i ? i.text ?? '' : ''), ''));
const firstImage = computed(() => props.firstPostPage
    ?.threads.flatMap(thread =>
        thread.replies.flatMap(reply =>
            reply.content?.find(i => i.type === 3)))
    .find(i => i !== undefined));
</script>
