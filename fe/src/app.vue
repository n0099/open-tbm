<template>
    <Meta charset="utf-8" />
    <Meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
    <Style>
        .grecaptcha-badge {
            visibility: hidden;
        }
        body {
            margin: 0;
        }
    </Style>
    <VueQueryDevtools />
    <NuxtLayout>
        <NuxtPage />
    </NuxtLayout>
</template>

<script setup lang="ts">
import { VueQueryDevtools } from '@tanstack/vue-query-devtools';

const config = useRuntimeConfig().public;
useHead({
    titleTemplate: title => {
        const suffix = `open-tbm @ ${config.instanceName}`;
        return title ? `${title} - ${suffix}` : suffix;
    }
});
</script>
