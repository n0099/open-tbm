<template>
<Meta charset="utf-8" />
<Meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
<VueQueryDevtools />
<NuxtLayout>
    <NuxtPage :pageKey="route => route.path.split('/')[1]" />
</NuxtLayout>
</template>

<script setup lang="ts">
import 'assets/css/global.css';
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
</script>
