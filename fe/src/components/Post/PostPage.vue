<template>
    <PageCurrentButton :currentCursor="props.posts.pages.currentCursor" />
    <RendererList v-if="renderType === 'list'" :initialPosts="posts" />
    <RendererTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages && props.posts.pages.hasMore"
                    :nextCursorRoute="nextCursorRoute" />
</template>

<script setup lang="ts">
import RendererList from './renderers/RendererList.vue';
import RendererTable from './renderers/RendererTable.vue';

import { PageCurrentButton, PageNextButton, useNextCursorRoute } from '../paginations/usePaginationButtons';
import type { PostRenderer } from '@/views/Post.vue';
import type { ApiPosts } from '@/api/index.d';

const props = defineProps<{
    posts: ApiPosts,
    renderType: PostRenderer,
    isLoadingNewPage: boolean,
    isLastPageInPages: boolean
}>();
const nextCursorRoute = useNextCursorRoute(props.posts.pages.nextCursor);
</script>
