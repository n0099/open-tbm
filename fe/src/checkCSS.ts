import { CheckCSS } from 'checkcss';

const checkcss = new CheckCSS();
checkcss.onClassnameDetected = className =>
    ![
        'grecaptcha',
        'g-recaptcha',
        'router-link-exact', // vue-router
        'nprogress',
        'noty_',
        'tippy-',
        'viewer-',
        'statsjs',

        // own usages
        'loading',
        'echarts',
        'bs-callout',

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
        'css-dev-only-do-not-override-'
    ].some(i => className.startsWith(i));
checkcss.scan().watch();
