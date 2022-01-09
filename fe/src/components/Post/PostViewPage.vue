<template>
    <PagePrevButton :pageInfo="posts.pages" :pageRoutes="pageRoutes" />
    <ViewList v-if="renderType === 'list'" :initialPosts="posts" />
    <ViewTable v-else-if="renderType === 'table'" :posts="posts" />
    <PageNextButton v-if="!isLoadingNewPage && isLastPageInPages" :pageRoutes="pageRoutes" />
</template>

<script lang="ts">
import { ViewList, ViewTable } from './';
import { PageNextButton, PagePrevButton, usePageRoutes } from '../usePageNextAndPrevButton';
import type { PostViewRenderer } from '@/views/Post.vue';
import type { ApiPostsQuery } from '@/api/index.d';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';

export default defineComponent({
    components: { PageNextButton, PagePrevButton, ViewList, ViewTable },
    props: {
        posts: { type: Object as PropType<ApiPostsQuery>, required: true },
        renderType: { type: String as PropType<PostViewRenderer>, required: true },
        isLoadingNewPage: { type: Boolean, required: true },
        isLastPageInPages: { type: Boolean, required: true }
    },
    setup(props) {
        return { pageRoutes: usePageRoutes(props.posts.pages.currentPage) };
    }
});
</script>
