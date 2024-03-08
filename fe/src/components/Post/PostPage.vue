<template>
    <PageCurrentButton :currentCursor="props.posts.pages.currentCursor" />
    <RendererList v-if="renderType === 'list'" :initialPosts="posts" />
    <RendererTable v-else-if="renderType === 'table'" :posts="posts" />
    <!-- eslint-disable vue/v-on-handler-style -->
    <PageNextButton v-if="isLastPageInPages && !isFetching && hasNextPage"
                    @click="async () => await fetchNextPage()" />
    <!-- eslint-enable vue/v-on-handler-style -->
</template>

<script setup lang="ts">
import RendererTable from './renderers/RendererTable.vue';
import RendererList from './renderers/list/RendererList.vue';
import PageCurrentButton from '../paginations/PageCurrentButton.vue';
import PageNextButton from '../paginations/PageNextButton.vue';
import type { PostRenderer } from '@/views/Post.vue';
import type { ApiPosts } from '@/api/index.d';
import type { InfiniteQueryObserverBaseResult } from '@tanstack/vue-query';

const props = defineProps<{
    posts: ApiPosts['response'],
    renderType: PostRenderer,
    isFetching: boolean,
    fetchNextPage: InfiniteQueryObserverBaseResult['fetchNextPage'],
    hasNextPage: boolean,
    isLastPageInPages: boolean
}>();
</script>
