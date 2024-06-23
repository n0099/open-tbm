<template>
    <Meta charset="utf-8" />
    <Meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
    <VueQueryDevtools />
    <NuxtLayout>
        <NuxtPage />
    </NuxtLayout>
</template>

<script setup lang="ts">
import 'assets/css/global.css';
import { VueQueryDevtools } from '@tanstack/vue-query-devtools';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';
import 'nprogress/nprogress.css';

const config = useRuntimeConfig().public;
useHead({
    titleTemplate: title => {
        const suffix = `open-tbm @ ${config.instanceName}`;

        return title === undefined ? suffix : `${title} - ${suffix}`;
    }
});

if (import.meta.client) {
    await import('bootstrap');
    (await import('nprogress')).configure({ trickleSpeed: 200 });
    if (import.meta.dev) {
        await import('@/stats');
        await import('@/checkCSS');
    }

    const reCAPTCHASiteKey = config.recaptchaSiteKey;
    if (reCAPTCHASiteKey !== '') {
        const tag = document.createElement('script');
        tag.async = true;
        tag.src = `https://www.recaptcha.net/recaptcha/api.js?render=${reCAPTCHASiteKey}`;
        document.body.append(tag);
    }

    const googleAnalyticsMeasurementId = config.gaMeasurementID;
    if (googleAnalyticsMeasurementId !== '') {
        const tag = document.createElement('script');
        tag.async = true;
        tag.src = `https://www.googletagmanager.com/gtag/js?id=${googleAnalyticsMeasurementId}`;
        document.body.append(tag);
        // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
        // @ts-ignore
        await import('@/gtag');
    }
}
</script>
