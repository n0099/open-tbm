<template>
<div class="flex flex-row gap-6 justify-between size-screen bg-white">
    <div class="flex-1 flex-col basis-1/2 m-4">
        <p>{{ useSiteConfig().name }} {{ routePath }}</p>
        <h2 v-if="firstPostPageForumName !== undefined">{{ firstPostPageForumName }}吧</h2>
        <template v-if="currentQueryType !== 'postID'">
            <p class="m-0">右侧为查询结果中第一张图片（不一定来自第一条帖子）</p>
            <p class="m-0">下方为查询结果中第一条主题帖/回复帖/楼中楼</p>
        </template>
        <h1>{{ firstThreadTitle }}</h1>
        <NewlineToBr is="h3" :text="firstPostContentTexts" wrapInSpan class="h-auto" />
        <template v-for="author in [firstPostAuthor]">
            <div :key="author.uid" v-if="author !== undefined" class="m-auto">
                <SkippableImage :src="toUserPortraitImageUrl(author.portrait)" />
                <span v-if="author.name !== null">{{ author.name }}</span>
                <span v-if="author.displayName !== null">{{ author.displayName }}</span>
                <span v-if="author.name === null && author.displayName === null">{{ author.uid }}</span>
            </div>
        </template>
    </div>
    <div v-if="firstImageUrl !== undefined" class="flex-auto basis-1/4">
        <SkippableImage :src="firstImageUrl" class="h-screen object-contain" />
    </div>
</div>
</template>

<script setup lang="ts">
import type { UnwrapRef } from 'vue';

defineProps<{
    routePath: string,
    currentQueryType: UnwrapRef<QueryFormDeps['currentQueryType']>,
    firstPostPageForumName?: string,
    firstThreadTitle?: string,
    firstPostContentTexts: string,
    firstPostAuthor?: User,
    firstImageUrl?: string
}>();
</script>
