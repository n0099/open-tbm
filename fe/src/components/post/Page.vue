<template>
<article>
    <PageCurrentButton :currentCursor="currentCursor" />
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
        @click="$emit('clickNextPage')" :nextCursor="nextCursor" />
</article>
</template>

<script setup lang="ts">
import type { PostRenderer } from '@/pages/posts.vue';

const { posts } = defineProps<{
    posts: ApiPosts['response'],
    renderType: PostRenderer,
    isFetching: boolean,
    hasNextPage: boolean,
    isLastPageInPages: boolean
}>();
defineEmits<{ clickNextPage: [] }>();
const { pages: { currentCursor, nextCursor } } = posts;
usePostPageProvision().provide({ ...posts, currentCursor });
</script>
