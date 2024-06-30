<template>
<PageCurrentButton :currentCursor="posts.pages.currentCursor" />
<PostRendererList v-if="renderType === 'list'" :initialPosts="posts" />
<div v-else-if="renderType === 'table'">
    <!-- https://github.com/vuejs/core/issues/5446 -->
    <PostRendererTable :posts="posts" />
</div>
<PageNextButton
    v-if="isLastPageInPages && !isFetching && hasNextPage"
    @click="() => $emit('clickNextPage')" :nextPageRoute="nextPageRoute" />
</template>

<script setup lang="ts">
import type { PostRenderer } from '@/pages/posts.vue';
import type { RouteLocationRaw } from 'vue-router';

defineProps<{
    posts: ApiPosts['response'],
    renderType: PostRenderer,
    isFetching: boolean,
    hasNextPage: boolean,
    isLastPageInPages: boolean,
    nextPageRoute: RouteLocationRaw
}>();
defineEmits<{ clickNextPage: [] }>();
</script>

<style scoped>
:deep(.tieba-user-portrait-small) {
    inline-size: 1.6rem;
    block-size: 1.6rem;
    object-fit: contain;
}

:deep(.tieba-user-portrait-large) {
    inline-size: 6rem;
    block-size: 6rem;
    object-fit: contain;
}
</style>
