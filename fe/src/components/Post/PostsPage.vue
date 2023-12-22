<template>
    <PageCurrentButton :currentCursor="props.posts.pages.currentCursor" />
    <ViewList v-if="renderType === 'list'" :initialPosts="posts" />
    <ViewTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages && props.posts.pages.hasMore"
                    :nextCursorRoute="nextCursorRoute" />
</template>

<script setup lang="ts">
import ViewList from './views/ViewList.vue';
import ViewTable from './views/ViewTable.vue';

import { PageCurrentButton, PageNextButton, useNextCursorRoute } from '../paginations/usePaginationButtons';
import type { PostViewRenderer } from '@/views/Post.vue';
import type { ApiPostsQuery } from '@/api/index.d';

const props = defineProps<{
    posts: ApiPostsQuery,
    renderType: PostViewRenderer,
    isLoadingNewPage: boolean,
    isLastPageInPages: boolean
}>();
const nextCursorRoute = useNextCursorRoute(props.posts.pages.nextCursor);
</script>
