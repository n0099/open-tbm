<template>
<nav class="row align-items-center gap-2 p-4">
    <div class="col align-middle"><hr /></div>
    <div class="w-auto">
        <NuxtLink
            @click="$emit('click')"
            :to="getNextCursorRoute(route, nextCursor)" class="btn btn-secondary fs-5">
            下一页
        </NuxtLink>
    </div>
    <div class="col align-middle"><hr /></div>
    <PageDecodedCursor v-if="nextCursor !== null" :encodedCursor="nextCursor" class="text-center" />
</nav>
</template>

<script setup lang="ts">
defineProps<{ nextCursor: Cursor | null }>();
defineEmits<{ click: [] }>();
const route = useRoute();
</script>
