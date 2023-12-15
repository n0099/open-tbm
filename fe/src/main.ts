import { createApp } from 'vue';
import { createHead } from '@vueuse/head';
import App from '@/App.vue';
import router from '@/router';

import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';
import nprogress from 'nprogress';
import 'nprogress/nprogress.css';
import '@/styles/style.css';

import * as fontAwesomeIcons from '@/shared/fontAwesome';
import { library } from '@fortawesome/fontawesome-svg-core';
library.add(...Object.values(fontAwesomeIcons));

nprogress.configure({ trickleSpeed: 200 });

if (import.meta.env.DEV) {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
    // @ts-ignore
    (await import('@/checkForUndefinedCSSClasses.js')).default();
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
    // @ts-ignore
    await import('@/stats.js');
}

const reCAPTCHASiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY;
if (reCAPTCHASiteKey !== '') {
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = `https://www.recaptcha.net/recaptcha/api.js?render=${reCAPTCHASiteKey}`;
    document.body.appendChild(tag);
}

const googleAnalyticsMeasurementId = import.meta.env.VITE_GA_MEASUREMENT_ID;
if (googleAnalyticsMeasurementId !== '') {
    const tag = document.createElement('script');
    tag.async = true;
    tag.src = `https://www.googletagmanager.com/gtag/js?id=${googleAnalyticsMeasurementId}`;
    document.body.appendChild(tag);
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
    // @ts-ignore
    await import('@/gtag.js');
}

export const app = createApp(App).use(router).use(createHead());
app.mount('#app');
