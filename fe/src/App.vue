<template>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm bg-light">
        <div class="container-fluid">
            <RouterLink to="/" class="navbar-brand">贴吧云监控</RouterLink>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="navbar-nav">
                    <li :class="`nav-item dropdown ${isActiveNav(['post', 'user'])}`">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarQueryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-search"></i> 查询
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarQueryDropdown">
                            <RouterLink to="/post" :class="`dropdown-item ${isActiveNav('post')}`" activeClass="active"><i class="far fa-comment-dots"></i> 贴子</RouterLink>
                            <RouterLink to="/user" :class="`dropdown-item ${isActiveNav('user')}`" activeClass="active"><i class="fas fa-users"></i> 用户</RouterLink>
                        </div>
                    </li>
                    <li :class="`nav-item ${isActiveNav('stats')}`">
                        <RouterLink to="/stats" class="nav-link" activeClass="active"><i class="fas fa-chart-pie"></i> 统计</RouterLink>
                    </li>
                    <li :class="`nav-item ${isActiveNav('status')}`">
                        <RouterLink to="/status" class="nav-link" activeClass="active"><i class="fas fa-satellite-dish"></i> 状态</RouterLink>
                    </li>
                    <li :class="`nav-item dropdown ${isActiveNav(['bilibiliVote'])}`">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarTopicDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-paper-plane"></i> 专题
                        </a>
                        <div class="dropdown-menu" aria-labelledby="navbarTopicDropdown">
                            <RouterLink to="/bilibiliVote" :class="`dropdown-item ${isActiveNav('bilibiliVote')}`" activeClass="active">bilibili吧公投</RouterLink>
                        </div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="https://n0099.net/donor-list"><i class="fas fa-donate"></i> 捐助</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <HorizontalMobileMessage />
    <div class="container">
        <router-view/>
    </div>
    <footer class="footer-outer text-light pt-4 mt-4">
        <div class="text-center container">
            <p>四叶重工QQ群：292311751</p>
            <p>
                Google <a class="text-white" href="https://www.google.com/analytics/terms/cn.html" target="_blank">Analytics 服务条款</a> |
                <a class="text-white" href="https://policies.google.com/terms" target="_blank">reCAPTCHA 服务条款</a> |
                <a class="text-white" href="https://policies.google.com/privacy" target="_blank">隐私条款</a>
            </p>
        </div>
        <footer class="footer-inner text-center p-3">
            <div class="container">© 2018 ~ 2021 n0099</div>
        </footer>
    </footer>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { RouterLink } from 'vue-router';
import NProgress from 'nprogress';
import tippy from 'tippy.js';
import _ from 'lodash';
import HorizontalMobileMessage from '@/components/HorizontalMobileMessage.vue';

export default defineComponent({
    name: 'App',
    components: { RouterLink, HorizontalMobileMessage },
    methods: {
        isActiveNav: () => ''
    }
});

// window.noty = new Noty({ timeout: 3000 }); // https://github.com/needim/noty/issues/455
NProgress.configure({ trickleSpeed: 200 });
const $$changePageLoading = isLoading => {
    if (isLoading) {
        NProgress.start();
        $('body').css('cursor', 'progress');
    } else {
        NProgress.done();
        $('body').css('cursor', '');
    }
};
/*
 *$(document)
 *    .ajaxStart(() => $$changePageLoading(true))
 *    .ajaxStop(() => $$changePageLoading(false))
 *    .ajaxError((event, jqXHR) => {
 *        let errorInfo = '';
 *        if (jqXHR.responseJSON !== undefined) {
 *            let error = jqXHR.responseJSON;
 *            if (_.isObject(error.errorInfo)) { // response when laravel failed validate
 *                errorInfo = `错误码：${error.errorCode}<br />${_.map(error.errorInfo, (info, paramName) => `参数 ${paramName}：${info.join('<br />')}`).join('<br />')}`;
 *            } else {
 *                errorInfo = `错误码：${error.errorCode}<br />${error.errorInfo}`;
 *            }
 *        }
 *        new Noty({ timeout: 3000, type: 'error', text: `HTTP ${jqXHR.status} ${errorInfo}`}).show();
 *    });
 */

const $$registerTippy = (scopedRootDom = 'body', unregister = false) => {
    if (unregister) _.each($(scopedRootDom).find('[data-tippy-content]'), dom => dom._tippy.destroy());
    else tippy($(scopedRootDom).find('[data-tippy-content]').get());
};
tippy.setDefaultProps({
    animation: 'perspective',
    interactive: true,
    theme: 'light-border'
});

const $$baseUrl = '{{ $baseUrl }}';
const $$baseUrlDir = $$baseUrl.substr($$baseUrl.indexOf('/', $$baseUrl.indexOf('://') + 3));
const $$reCAPTCHASiteKey = '{{ $reCAPTCHASiteKey }}';
const $$reCAPTCHACheck = () => new Promise((resolve, reject) => {
    NProgress.start();
    $('body').css('cursor', 'progress');
    grecaptcha.ready(() => {
        grecaptcha.execute($$reCAPTCHASiteKey)
            .then(token => {
                resolve(token);
                $$changePageLoading(false);
            }, () => {
                reject();
                $$changePageLoading(false);
                new Noty({ timeout: 3000, type: 'error', text: 'Google reCAPTCHA 验证未通过 请刷新页面/更换设备/网络环境后重试' }).show();
            });
    });
    // todo: should skip requesting recaptcha under dev mode: resolve(null);
});
const $$initialNavBar = activeNav => {
    window.navBarVue = new Vue({
        el: '#navbar',
        data() {
            return { $$baseUrl, activeNav };
        },
        methods: {
            isActiveNav(pageName) {
                let isActive;
                if (_.isArray(pageName)) isActive = pageName.includes(this.$data.activeNav);
                else isActive = this.$data.activeNav === pageName;

                return isActive ? 'active' : null;
            }
        }
    });
};

const $$loadForumList = () => new Promise(resolve => $.getJSON(`${$$baseUrl}/api/forumsList`).done(ajaxData => resolve(ajaxData)));

const $$getTiebaPostLink = (tid, pid = null, spid = null) => {
    if (spid !== null) return `https://tieba.baidu.com/p/${tid}?pid=${spid}#${spid}`;
    else if (pid !== null) return `https://tieba.baidu.com/p/${tid}?pid=${pid}#${pid}`;

    return `https://tieba.baidu.com/p/${tid}`;
};
const $$getTBMPostLink = (tid, pid = null, spid = null) => {
    if (spid !== null) return `${$$baseUrl}/post/tid/${tid}`;
    else if (pid !== null) return `${$$baseUrl}/post/pid/${pid}`;

    return `${$$baseUrl}/post/spid/${spid}`;
};
const $$getTiebaUserLink = username => `http://tieba.baidu.com/home/main?un=${username}`;
const $$getTBMUserLink = username => `${$$baseUrl}/user/n/${username}`;
const $$getTiebaUserAvatarUrl = avatarUrl => `https://himg.bdimg.com/sys/portrait/item/${avatarUrl}.jpg`;
</script>

<style scoped>
.footer-outer {
    background-color: #2196f3;
}

.footer-inner {
    background-color: rgba(0,0,0,.2);
}
</style>

<style>
.lazyload, .lazyloading {
    opacity: 0;
    background: #f7f7f7 url('../public/assets/icon-huaji-loading-spinner.gif') no-repeat center;
}
.lazyloaded {
    opacity: 1;
    transition: opacity .3s;
}

.loading-icon {
    width: 100px;
    height: 100px;
    background-image: url('../public/assets/icon-huaji-loading-spinner.gif');
    background-size: 100%;
}

.grecaptcha-badge {
    visibility: hidden;
}

a {
    color: #1890ff; /* use antd color for clearly bootstrap hover animation */
    text-decoration: none !important; /* override browser underline */
}

* {
    font-weight: 300;
    font-family: "Lucida Grande", "Microsoft Yahei", 'Noto Sans SC', sans-serif;
}

@media (max-width: 991.98px) {
    .container {
        max-width: 100%;
    }
}

::-webkit-scrollbar {
    width: 10px;
    height: 10px;
    background-color: #f5f5f5;
}
::-webkit-scrollbar-track {
    box-shadow: inset 0 0 6px rgba(0,0,0,0.3);
    background-color: #f5f5f5;
}
::-webkit-scrollbar-thumb {
    background-color: #f90;
    background-image: -webkit-linear-gradient(
        45deg,
        rgba(255, 255, 255, .2) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255, 255, 255, .2) 50%,
        rgba(255, 255, 255, .2) 75%,
        transparent 75%,
        transparent
    )
}
</style>
