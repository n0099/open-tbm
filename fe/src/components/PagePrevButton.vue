<template>
    <div class="row align-items-center p-2">
        <div class="col align-middle"><hr /></div>
        <div class="w-auto">
            <div class="p-2 badge bg-light text-dark">
                <p class="h4">第 {{ currentPageCursor }} 页</p>
            </div>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
</template>

<script setup lang="ts">
import type { Cursor } from '@/api/index.d';

defineProps<{ currentPageCursor: Cursor }>();
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
