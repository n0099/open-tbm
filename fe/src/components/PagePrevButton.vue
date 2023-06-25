<template>
    <div class="row align-items-center p-2">
        <div class="col align-middle"><hr /></div>
        <div class="w-auto">
            <div class="p-2 badge bg-light text-dark">
                <RouterLink v-if="page.currentPage > 1" :to="pageRoutes.prev"
                            class="badge bg-primary link-light">上一页</RouterLink>
                <p class="h4">第 {{ page.currentPage }} 页</p>
                <span class="small">第 {{ page.firstItem }}~{{ page.firstItem + page.itemCount - 1 }} 条</span>
            </div>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
</template>

<script setup lang="ts">
import type { Pagination } from '@/api/index.d';
import type { RouteLocationRaw } from 'vue-router';
import { RouterLink } from 'vue-router';

defineProps<{
    page: Pagination,
    pageRoutes: { prev: RouteLocationRaw }
}>();
</script>

<style scoped>
.post-previous-page-button {
    position: relative;
    top: -1.5rem;
    margin-bottom: -1rem;
    padding-bottom: 4px;
    padding-top: 4px;
}
</style>
