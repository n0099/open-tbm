<template>
    <div class="row align-items-center p-2">
        <div class="col align-middle"><hr /></div>
        <div class="w-auto">
            <div class="p-2 badge bg-light text-dark">
                <RouterLink v-if="pageInfo.currentPage > 1" :to="pageRoutes.prev"
                            class="badge bg-primary link-light">上一页</RouterLink>
                <p class="h4">第 {{ pageInfo.currentPage }} 页</p>
                <span class="small">第 {{ pageInfo.firstItem }}~{{ pageInfo.firstItem + pageInfo.itemsCount - 1 }} 条</span>
            </div>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
</template>

<script lang="ts">
import type { Pagination } from '@/api/index.d';
import type { PropType } from 'vue';
import { defineComponent } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { RouterLink } from 'vue-router';

export default defineComponent({
    components: { RouterLink },
    props: {
        pageInfo: { type: Object as PropType<Pagination>, required: true },
        pageRoutes: { type: Object as PropType<{ prev: RouteLocationRaw }>, required: true }
    }
});
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
