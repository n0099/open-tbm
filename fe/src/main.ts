import App from '@/App.vue';
import router from '@/router';
import * as fontAwesomeIcons from '@/shared/fontAwesome';
import '@/styles/style.css';
import { library } from '@fortawesome/fontawesome-svg-core';
import 'bootstrap/dist/css/bootstrap.min.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { createHead } from '@unhead/vue';
import 'bootstrap';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';
import nprogress from 'nprogress';
import 'nprogress/nprogress.css';

library.add(...Object.values(fontAwesomeIcons));

nprogress.configure({ trickleSpeed: 200 });

if (import.meta.env.DEV) {
    // @ts-expect-error no .d.ts
    await import('@/stats.js');
    await import('@/checkCSS');
}

const reCAPTCHASiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY;
if (reCAPTCHASiteKey !== '') {
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = `https://www.recaptcha.net/recaptcha/api.js?render=${reCAPTCHASiteKey}`;
    document.body.append(tag);
}

const googleAnalyticsMeasurementId = import.meta.env.VITE_GA_MEASUREMENT_ID;
if (googleAnalyticsMeasurementId !== '') {
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = `https://www.googletagmanager.com/gtag/js?id=${googleAnalyticsMeasurementId}`;
    document.body.append(tag);
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
    // @ts-ignore
    await import('@/gtag.js');
}

export const app = createApp(App).use(router).use(createHead()).use(createPinia())
    .use(VueQueryPlugin, { queryClientConfig: { defaultOptions: { queries: { refetchOnWindowFocus: false } } } });
app.mount('#app');
