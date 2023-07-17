import { createApp } from 'vue';
import { createHead } from '@vueuse/head';
import App from '@/App.vue';
import router from '@/router';

import 'ant-design-vue/dist/antd.css';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';
import nprogress from 'nprogress';
import 'nprogress/nprogress.css';
import '@/shared/style.css';

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

export const app = createApp(App).use(router).use(createHead());
app.mount('#app');
