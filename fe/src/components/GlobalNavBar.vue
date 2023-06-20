<template>
    <nav class="navbar navbar-expand-lg navbar-light shadow-sm bg-light">
        <div id="nav" class="container-fluid">
            <RouterLink to="/" class="navbar-brand">open-tbm @ {{ envInstanceName }}</RouterLink>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar"
                    aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" />
            </button>
            <div id="navbar" class="navbar-collapse collapse">
                <ul class="navbar-nav">
                    <template v-for="(nav, _k) in navs" :key="_k">
                        <li v-if="'routes' in nav" class="nav-item dropdown" :class="{ active: nav.isActive }">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <FontAwesomeIcon v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                            </a>
                            <div class="dropdown-menu">
                                <RouterLink v-for="r in nav.routes" :key="r.route.name" :to="{ name: r.route }" class="nav-link">
                                    <FontAwesomeIcon v-if="r.icon !== undefined" :icon="r.icon" /> {{ r.title }}
                                </RouterLink>
                            </div>
                        </li>
                        <li v-else class="nav-item" :class="{ action: nav.isActive }">
                            <RouterLink :to="{ name: nav.route }" class="nav-link">
                                <FontAwesomeIcon v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                            </RouterLink>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </nav>
</template>

<script lang="ts">
import { defineComponent, reactive, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';

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

        watch(() => route.name, () => {
            navs.forEach(nav => ({ // we don't have to reassign navs since it's reactive
                ...nav,
                isActive: 'routes' in nav
                    ? nav.routes.some(i => i.route === route.name)
                    : nav.route === route.name
            }));
        });

        return { navs, envInstanceName: process.env.VUE_APP_INSTANCE_NAME };
    }
});
</script>
