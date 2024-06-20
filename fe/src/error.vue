<template>
    <NuxtLayout>
        <PlaceholderError :error="new ApiResponseError(error.statusCode, error.statusMessage ?? '')" />
    </NuxtLayout>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app';
import { ApiResponseError } from '@/api';

defineProps<{ error: NuxtError }>();
</script>
