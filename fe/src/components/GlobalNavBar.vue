<template>
<DefineNavItem v-slot="{ nav }">
    <NuxtLink :to="{ name: nav.route }" noPrefetch class="nav-link" v-bind="navLinkAttrs(nav)">
        <FontAwesome v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
    </NuxtLink>
</DefineNavItem>
<nav class="navbar navbar-expand-lg shadow-sm bg-light">
    <div class="container-fluid">
        <NuxtLink to="/" noPrefetch class="navbar-brand">{{ useSiteConfig().name }}</NuxtLink>
        <button
            class="navbar-toggler" type="button" data-bs-target="#navbar" data-bs-toggle="collapse"
            aria-controls="navbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon" />
        </button>
        <div class="navbar-collapse collapse" id="navbar">
            <ul class="navbar-nav">
                <template v-for="(nav, _k) in navs" :key="_k">
                    <li v-if="'routes' in nav" class="nav-item dropdown">
                        <a
                            class="nav-link dropdown-toggle" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false" v-bind="navLinkAttrs(nav)">
                            <FontAwesome v-if="nav.icon !== undefined" :icon="nav.icon" /> {{ nav.title }}
                        </a>
                        <div class="dropdown-menu">
                            <ReuseNavItem v-for="r in nav.routes" :key="r.route" :nav="r" />
                        </div>
                    </li>
                    <li v-else class="nav-item">
                        <ReuseNavItem :nav="nav" />
                    </li>
                </template>
            </ul>
        </div>
    </div>
</nav>
</template>

<script setup lang="ts">
import type { AriaAttributes } from 'vue';
import type { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import { faCommentDots, faPaperPlane, faSearch, faUsers } from '@fortawesome/free-solid-svg-icons';

interface Nav { title: string, icon?: IconDefinition, isActive?: boolean }
interface Route extends Nav { route: string }
interface DropDown extends Nav { routes: Route[], icon: IconDefinition }
type Navs = Array<DropDown | Route>;

const route = useRoute();
const navs = reactive<Navs>([
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
const [DefineNavItem, ReuseNavItem] = createReusableTemplate<{ nav: Route }>();
const navLinkAttrs = (nav: ArrayElement<Navs>) => ({
    class: { active: nav.isActive },
    // eslint-disable-next-line @typescript-eslint/naming-convention
    ...nav.isActive === true ? { 'aria-current': 'page' } as Pick<AriaAttributes, 'aria-current'> : {}
});

watchEffect(() => {
    assertRouteNameIsStr(route.name);
    const routeName = route.name;
    navs.forEach(nav => {
        if ('routes' in nav) {
            nav.isActive = nav.routes.some(r => routeName.startsWith(r.route));
            nav.routes.forEach(r => { r.isActive = routeName.startsWith(r.route) });
        } else {
            nav.isActive = routeName.startsWith(nav.route);
        }
    });
});
</script>
