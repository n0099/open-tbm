import { createApp } from 'vue';
import App from '@/App.vue';
import router from '@/router';
import store from '@/store';
import '@fortawesome/fontawesome-free/js/all.js';
import antd from 'ant-design-vue';
import 'ant-design-vue/dist/antd.css';
// eslint-disable-next-line @typescript-eslint/no-unused-vars
import bootstrap from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'noty/lib/noty.css';
import 'noty/lib/themes/mint.css';
import NProgress from 'nprogress';
import 'nprogress/nprogress.css';
import tippy from 'tippy.js';

// window.noty = new Noty({ timeout: 3000 }); // https://github.com/needim/noty/issues/455
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

createApp(App)
    .use(store)
    .use(router)
    .use(antd)
    .mount('#app');
