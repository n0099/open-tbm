<template>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm bg-light">
        <div class="container-fluid">
            <RouterLink to="/" class="navbar-brand">贴吧云监控</RouterLink>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar"
                    aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="navbar-nav">
                    <template v-for="(nav, _k) in navs" :key="_k">
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
</template>

<script lang="ts">
import { defineComponent, reactive, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import AntdZhCn from 'ant-design-vue/es/locale/zh_CN';

export default defineComponent({
    components: { FontAwesomeIcon, RouterLink },
    setup() {
        const route = useRoute();
        interface Route { route: string, title: string, icon?: string, isActive?: boolean }
        interface DropDown { title: string, icon: string, routes: Route[], isActive?: boolean }
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
                isActive: 'routes' in nav
                    ? nav.routes.some(i => i.route === route.name)
                    : nav.route === route.name
            }));
        });

        return { AntdZhCn, navs, activeNavClass };
    }
});
</script>
