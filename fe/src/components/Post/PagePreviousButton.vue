<template>
    <div :data-page="pageInfo.currentPage" class="post-previous-page mt-3 p-2 row align-items-center">
        <div class="col"><hr /></div>
        <div class="w-auto">
            <div class="p-2 badge badge-light">
                <button v-if="pageInfo.currentPage > 1" @click="$emit('load-page', pageInfo.currentPage - 1)"
                        class="post-previous-page-button btn btn-primary" type="button">上一页</button>
                <p class="h4">第 {{ pageInfo.currentPage }} 页</p>
                <span class="small">第 {{ pageInfo.firstItem }}~{{ pageInfo.firstItem + pageInfo.itemsCount }} 条</span>
            </div>
        </div>
        <div class="col"><hr /></div>
    </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';

export default defineComponent({
    props: {
        pageInfo: { type: Object, required: true }
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
