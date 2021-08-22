import { createApp } from 'vue';
import App from '@/App.vue';
import router from '@/router';
import store from '@/store';
import '@fortawesome/fontawesome-free/js/all.js';
import antd from 'ant-design-vue';
import 'ant-design-vue/dist/antd.css';
import bootstrap from 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'nprogress/nprogress.css';
// eslint-disable-next-line @typescript-eslint/ban-ts-comment, @typescript-eslint/prefer-ts-expect-error
// @ts-ignore
import cssCheck from '@/checkForUndefinedCSSClasses.js';

cssCheck();
createApp(App)
    .use(store)
    .use(router)
    .use(antd)
    .mount('#app');
