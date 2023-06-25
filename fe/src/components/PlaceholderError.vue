<template>
    <div class="text-center">
        <template v-if="error === null">
            <span class="errorCode text-muted">error</span>
        </template>
        <template v-else>
            <p class="errorCode text-muted">{{ error.errorCode }}</p>
            <p v-for="(info, _k) in error.errorInfo.split('\n')" :key="_k">{{ info }}</p>
        </template>
    </div>
</template>

<script setup lang="ts">
import type { ApiError } from '@/api/index.d';

defineProps<{ error: ApiError | null }>();
</script>

<style scoped>
.errorCode {
    font-size: 6rem;
}
</style>
