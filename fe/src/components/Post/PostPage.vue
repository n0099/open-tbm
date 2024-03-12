<template>
    <PageCurrentButton :currentCursor="posts.pages.currentCursor" />
    <RendererList v-if="renderType === 'list'" :initialPosts="posts" />
    <RendererTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="isLastPageInPages && !isFetching && hasNextPage"
                    @click="() => $emit('clickNextPage')" />
</template>

<script setup lang="ts">
import RendererTable from './renderers/RendererTable.vue';
import RendererList from './renderers/list/RendererList.vue';
import PageCurrentButton from '../paginations/PageCurrentButton.vue';
import PageNextButton from '../paginations/PageNextButton.vue';
import type { PostRenderer } from '@/views/Post.vue';
import type { ApiPosts } from '@/api/index.d';

defineProps<{
    posts: ApiPosts['response'],
    renderType: PostRenderer,
    isFetching: boolean,
    hasNextPage: boolean,
    isLastPageInPages: boolean
}>();
defineEmits<{ clickNextPage: [] }>();
</script>
