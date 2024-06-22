<template>
    <NuxtLayout>
        <PlaceholderError :error="new ApiResponseError(error.statusCode, error.statusMessage ?? '')" />
    </NuxtLayout>
</template>

<script setup lang="ts">
defineProps<{ error: NuxtError }>();
</script>
