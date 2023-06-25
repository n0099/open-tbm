<template>
    <PagePrevButton :page="posts.pages" :pageRoutes="pageRoutes" />
    <ViewList v-if="renderType === 'list'" :initialPosts="posts" />
    <ViewTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages" :pageRoutes="pageRoutes" />
</template>

<script setup lang="ts">
import { ViewList, ViewTable } from './';
import { PageNextButton, PagePrevButton, usePageRoutes } from '../usePageNextAndPrevButton';
import type { PostViewRenderer } from '@/views/Post.vue';
import type { ApiPostsQuery } from '@/api/index.d';

const props = defineProps<{
    posts: ApiPostsQuery,
    renderType: PostViewRenderer,
    isLoadingNewPage: boolean,
    isLastPageInPages: boolean
}>();
const pageRoutes = usePageRoutes(props.posts.pages.currentPage);
</script>
