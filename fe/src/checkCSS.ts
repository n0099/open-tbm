import { CheckCSS } from 'checkcss';

const checkcss = new CheckCSS();
checkcss.onClassnameDetected = className =>
    ![
        'grecaptcha',
        'g-recaptcha',
        'router-link-', // vue-router
        'tsqd-', // @tanstack/vue-query-devtools
        'nprogress',
        'noty_',
        'tippy-',
        'viewer-',
        'statsjs',

        // own usages
        'loading',
        'echarts',
        'bs-callout',
        'post-render-list',
        'reply-content',
        'sub-reply-content',

        // fontawesome
        'fa-',
        'far',
        'fas',
        'fontawesome',
        'svg-inline--fa',

        // antdv
        'ant-',
        'anticon',
        'data-ant-cssinjs-cache-path',
        'css-dev-only-do-not-override-',

        // vue <Transition>
        'v-enter-',
        'v-leave-'
    ].some(i => className.startsWith(i));
checkcss.scan().watch();
