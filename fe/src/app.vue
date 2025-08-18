<template>
<Meta charset="utf-8" />
<Meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
<VueQueryDevtools />
<NuxtLayout>
    <NuxtPage :pageKey="pageKey" />
</NuxtLayout>
</template>

<script setup lang="ts">
import '@/assets/css/global.css';
import type { RouteLocationNormalizedLoaded } from 'vue-router';
import { VueQueryDevtools } from '@tanstack/vue-query-devtools';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';

useHead({
    titleTemplate: '%pageTitle %separator %siteName',
    templateParams: { separator: '-' }
});

if (import.meta.client) {
    await import('bootstrap');
    if (import.meta.dev) {
        await import('@/stats');
        await import('@/checkCSS');
    }
}

/** {@link route.path} should always has leading slash */
const pageKey = (route: RouteLocationNormalizedLoaded) => route.path.split('/')[1] ?? throwError();
</script>
