<template>
<nav class="row align-items-center p-4">
    <div class="col align-middle"><hr /></div>
    <div class="w-auto">
        <NuxtLink
            @click="$emit('click')"
            :to="getNextCursorRoute(route, nextCursor)" class="btn btn-secondary fs-5">
            下一页
        </NuxtLink>
    </div>
    <div class="col align-middle"><hr /></div>
</nav>
</template>

<script setup lang="ts">
defineProps<{ nextCursor: Cursor | null }>();
defineEmits<{ click: [] }>();
const route = useRoute();
</script>
