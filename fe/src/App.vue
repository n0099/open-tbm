<template>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm bg-light">
        <div class="container-fluid">
            <RouterLink to="/" class="navbar-brand">贴吧云监控</RouterLink>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="navbar-nav">
                    <template v-for="nav in navs" :key="navs.indexOf(nav)">
                        <li v-if="'routes' in nav" :class="'nav-item dropdown' + activeNavClass(nav.isActive)">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <FontAwesomeIcon v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                            </a>
                            <div class="dropdown-menu">
                                <RouterLink v-for="r in nav.routes" :key="r.route.name" :to="{ name: r.route }" class="nav-link">
                                    <FontAwesomeIcon v-if="r.icon !== undefined" :icon="r.icon" /> {{ r.title }}
                                </RouterLink>
                            </div>
                        </li>
                        <li v-else :class="'nav-item' + activeNavClass(nav.isActive)">
                            <RouterLink :to="{ name: nav.route }" class="nav-link">
                                <FontAwesomeIcon v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                            </RouterLink>
                        </li>
                    </template>
                    <li class="nav-item">
                        <a class="nav-link" href="https://n0099.net/donor-list"><FontAwesomeIcon icon="donate" /> 捐助</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <HorizontalMobileMessage />
    <img id="loadingBlocksRouteChange" :src="baseUrl + 'assets/icon-loading-blocks.svg'" class="d-none" />
    <div class="container">
        <ConfigProvider :locale="AntdZhCn">
            <RouterView />
        </ConfigProvider>
    </div>
    <footer class="footer-outer text-light pt-4 mt-4">
        <div class="text-center">
            <p>四叶重工QQ群：292311751</p>
            <p>
                Google <a class="text-white" href="https://www.google.com/analytics/terms/cn.html" target="_blank">Analytics 服务条款</a> |
                <a class="text-white" href="https://policies.google.com/terms" target="_blank">reCAPTCHA 服务条款</a> |
                <a class="text-white" href="https://policies.google.com/privacy" target="_blank">隐私条款</a>
            </p>
        </div>
        <footer class="footer-inner text-center p-3">
            <span>© 2018 ~ 2021 n0099</span>
        </footer>
    </footer>
</template>

<script lang="ts">
import { defineComponent, onMounted, reactive, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { ConfigProvider } from 'ant-design-vue';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import HorizontalMobileMessage from '@/components/HorizontalMobileMessage.vue';
import _ from 'lodash';

export default defineComponent({
    components: { FontAwesomeIcon, RouterLink, ConfigProvider, HorizontalMobileMessage },
    setup() {
        const route = useRoute();
        const baseUrl = process.env.BASE_URL;
        interface Route { route: string, title: string, icon?: string, isActive?: true }
        interface DropDown { title: string, icon: string, routes: Route[], isActive?: true }
        const navs = reactive<Array<DropDown | Route>>([
            {
                title: '查询',
                icon: 'search',
                routes: [
                    { route: 'post', title: '帖子', icon: 'comment-dots' },
                    { route: 'user', title: '用户', icon: 'users' }
                ]
            },
            { route: 'stats', title: '统计', icon: 'chart-pie' },
            { route: 'status', title: '状态', icon: 'satellite-dish' },
            {
                title: '专题',
                icon: 'paper-plane',
                routes: [
                    { route: 'bilibiliVote', title: 'bilibili吧公投' }
                ]
            }
        ]);
        const activeNavClass = (isActive?: boolean) => (isActive === true ? 'active' : '');

        watch(() => route.name, () => {
            navs.map(nav => ({
                ...nav,
                isActive: ('routes' in nav
                    ? nav.routes.some(i => i.route === route.name)
                    : nav.route === route.name) || undefined // false to undefined
            }));
        });
        onMounted(() => document.getElementById('loadingBlocksInitial')?.remove());

        return { AntdZhCn, baseUrl, navs, activeNavClass };
    }
});

const $$registerTippy = (scopedRootDom = 'body', unregister = false) => {
    if (unregister) _.each($(scopedRootDom).find('[data-tippy-content]'), dom => dom._tippy.destroy());
    else tippy($(scopedRootDom).find('[data-tippy-content]').get());
};

const $$baseUrl = '{{ $baseUrl }}';
const $$baseUrlDir = $$baseUrl.substr($$baseUrl.indexOf('/', $$baseUrl.indexOf('://') + 3));

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
const $$getTBMUserLink = username => `${$$baseUrl}/user/n/${username}`;
</script>

<style scoped>
.footer-outer {
    background-color: #2196f3;
}

.footer-inner {
    background-color: rgba(0,0,0,.2);
}
</style>
