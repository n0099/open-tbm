<template>
<nav class="row align-items-center p-2">
    <div class="col align-middle"><hr /></div>
    <div class="w-auto">
        <div class="p-2 badge bg-light text-dark">
            <h4 v-if="currentCursor === ''">起始页</h4>
            <h4 v-else>页游标 <code>{{ currentCursor }}</code></h4>
        </div>
    </div>
    <div class="col align-middle"><hr /></div>
</nav>
</template>

<script setup lang="ts">
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
