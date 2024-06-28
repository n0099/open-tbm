<template>
<div class="row align-items-center p-4">
    <div class="col align-middle"><hr /></div>
    <div class="w-auto">
        <NuxtLink
            @click="() => $emit('click')"
            :to="nextPageRoute" class="btn btn-secondary fs-5">
            下一页
        </NuxtLink>
    </div>
    <div class="col align-middle"><hr /></div>
</div>
</template>

<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router';

defineProps<{ nextPageRoute: RouteLocationRaw }>();
defineEmits<{ click: [] }>();
</script>
