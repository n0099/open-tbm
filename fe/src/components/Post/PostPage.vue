<template>
    <PageCurrentButton :currentCursor="posts.pages.currentCursor" />
    <RendererList v-if="renderType === 'list'" :initialPosts="posts" />
    <RendererTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="isLastPageInPages && !isFetching && hasNextPage"
                    @click="() => $emit('clickNextPage')" />
</template>

<script setup lang="ts">
defineProps<{
    posts: ApiPosts['response'],
    renderType: PostRenderer,
    isFetching: boolean,
    hasNextPage: boolean,
    isLastPageInPages: boolean
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
