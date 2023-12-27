<template>
    <div class="row align-items-center p-2">
        <div class="col align-middle"><hr /></div>
        <div class="w-auto">
            <div class="p-2 badge bg-light text-dark">
                <p class="h4">{{ cursorTemplate(currentCursor) }}</p>
            </div>
        </div>
        <div class="col align-middle"><hr /></div>
    </div>
</template>

<script setup lang="ts">
import type { Cursor } from '@/api/index.d';
import { cursorTemplate } from '@/shared';

defineProps<{ currentCursor: Cursor }>();
</script>

<style scoped>
.post-previous-page-button {
    position: relative;
    inset-block-start: -1.5rem;
    margin-block-end: -1rem;
    padding-block-end: .25rem;
    padding-block-start: .25rem;
}
</style>
