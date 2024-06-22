<template>
    <NuxtLayout>
        <PlaceholderError :error="new ApiResponseError(error.statusCode, error.statusMessage ?? '')" />
    </NuxtLayout>
</template>

<script setup lang="ts">
import type { NuxtError } from '#app';

defineProps<{ error: NuxtError }>();
</script>
