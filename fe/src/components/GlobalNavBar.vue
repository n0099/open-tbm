<template>
    <nav class="navbar navbar-expand-lg shadow-sm bg-light">
        <div class="container-fluid" id="nav">
            <NuxtLink to="/" noPrefetch class="navbar-brand">open-tbm @ {{ config.public.instanceName }}</NuxtLink>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar"
                    aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon" />
            </button>
            <div class="navbar-collapse collapse" id="navbar">
                <ul class="navbar-nav">
                    <template v-for="(nav, _k) in navs" :key="_k">
                        <li v-if="'routes' in nav" class="nav-item dropdown" :class="{ active: nav.isActive }">
                            <a class="nav-link dropdown-toggle" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <FontAwesomeIcon v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                            </a>
                            <div class="dropdown-menu">
                                <NuxtLink v-for="r in nav.routes" :key="r.route"
                                          :to="{ name: r.route }" noPrefetch class="nav-link">
                                    <FontAwesomeIcon v-if="r.icon !== undefined" :icon="r.icon" /> {{ r.title }}
                                </NuxtLink>
                            </div>
                        </li>
                        <li v-else class="nav-item" :class="{ action: nav.isActive }">
                            <NuxtLink :to="{ name: nav.route }" noPrefetch class="nav-link">
                                <FontAwesomeIcon v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                            </NuxtLink>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </nav>
</template>

<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import type { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import { faCommentDots, faPaperPlane, faSearch, faUsers } from '@fortawesome/free-solid-svg-icons';

const route = useRoute();
const config = useRuntimeConfig();

interface Nav { title: string, icon?: IconDefinition, isActive?: boolean }
interface Route extends Nav { route: string }
interface DropDown extends Nav { routes: Route[], icon: IconDefinition }
const navs = reactive<Array<DropDown | Route>>([
    {
        title: '查询',
        icon: faSearch,
        routes: [
            { route: 'posts', title: '帖子', icon: faCommentDots },
            { route: 'users', title: '用户', icon: faUsers }
        ]
    },
    {
        title: '专题',
        icon: faPaperPlane,
        routes: [
            { route: 'bilibiliVote', title: 'bilibili吧公投' }
        ]
    }
]);

watch(() => route.name, () => {
    navs.forEach(nav => {
        nav.isActive = 'routes' in nav
            ? nav.routes.some(i => i.route === route.name)
            : nav.route === route.name;
    });
});
</script>
