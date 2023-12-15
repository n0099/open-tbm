<template>
    <PagePrevButton :currentPageCursor="props.posts.pages.currentPageCursor" />
    <ViewList v-if="renderType === 'list'" :initialPosts="posts" />
    <ViewTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages" :nextPageRoute="nextPageRoute" />
</template>

<script setup lang="ts">
import { ViewList, ViewTable } from '.';
import { PageNextButton, PagePrevButton, useNextPageRoute } from '../usePageNextAndPrevButton';
import type { PostViewRenderer } from '@/views/Post.vue';
import type { ApiPostsQuery } from '@/api/index.d';

const props = defineProps<{
    posts: ApiPostsQuery,
    renderType: PostViewRenderer,
    isLoadingNewPage: boolean,
    isLastPageInPages: boolean
}>();
const nextPageRoute = useNextPageRoute(props.posts.pages.currentPageCursor);
</script>
