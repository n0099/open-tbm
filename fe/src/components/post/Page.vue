<template>
<article>
    <PageCurrentButton :currentCursor="posts.pages.currentCursor" />
    <Suspense suspensible :timeout="0">
        <template #fallback>
            <PlaceholderPostList isLoading />
        </template>
        <LazyPostRendererList v-if="renderType === 'list'" :posts="posts" />
        <div v-else-if="renderType === 'table'">
            <!-- https://github.com/vuejs/core/issues/5446 -->
            <LazyPostRendererTable :posts="posts" />
        </div>
    </Suspense>
    <PageNextButton
        v-if="isLastPageInPages && !isFetching && hasNextPage"
        @click="() => $emit('clickNextPage')" :nextPageRoute="nextPageRoute" />
</article>
</template>

<script setup lang="ts">
import type { PostRenderer } from '@/pages/posts.vue';
import type { RouteLocationRaw } from 'vue-router';

const { posts } = defineProps<{
    posts: ApiPosts['response'],
    renderType: PostRenderer,
    isFetching: boolean,
    hasNextPage: boolean,
    isLastPageInPages: boolean,
    nextPageRoute: RouteLocationRaw
}>();
defineEmits<{ clickNextPage: [] }>();
usePostPageProvision().provide({ ...posts, currentCursor: posts.pages.currentCursor });
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
