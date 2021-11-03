import { createApp } from 'vue';
import App from '@/App.vue';
import router from '@/router';
import store from '@/store';
import 'ant-design-vue/dist/antd.css';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';
import NProgress from 'nprogress';
import 'nprogress/nprogress.css';
import tippy from 'tippy.js';
import 'lazysizes';
import '@/shared/style.css';
import * as antdComponents from '@/shared/antd';
import * as fontAwesomeIcons from '@/shared/fontAwesome';
import { library } from '@fortawesome/fontawesome-svg-core';
library.add(...Object.values(fontAwesomeIcons));

NProgress.configure({ trickleSpeed: 200 });
tippy.setDefaultProps({
    animation: 'perspective',
    interactive: true,
    theme: 'light-border'
});

if (process.env.NODE_ENV === 'development') {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
    // @ts-ignore
    (await import('@/checkForUndefinedCSSClasses.js')).default();
}

const app = createApp(App).use(store).use(router);
Object.values(antdComponents).forEach(c => app.use(c));
app.mount('#app');
