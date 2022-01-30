import Index from '@/views/Index.vue';
import PlaceholderError from '@/components/PlaceholderError.vue';
import { notyShow } from '@/shared';
import type { Component } from 'vue';
import { onUnmounted, ref } from 'vue';
import type { RouteLocationNormalized, RouteLocationNormalizedLoaded, RouteRecordRaw, RouterScrollBehavior } from 'vue-router';
import { createRouter, createWebHistory } from 'vue-router';
import NProgress from 'nprogress';
import _ from 'lodash';

const componentCustomScrollBehaviour = ref<RouterScrollBehavior>();
export const setComponentCustomScrollBehaviour = (cb: RouterScrollBehavior) => {
    componentCustomScrollBehaviour.value = cb;
    onUnmounted(() => { componentCustomScrollBehaviour.value = undefined });
};

export const assertRouteNameIsStr: (name: RouteLocationNormalizedLoaded['name']) => asserts name is string = name => {
    if (!_.isString(name)) throw Error('https://github.com/vuejs/vue-router-next/issues/1185');
}; // https://github.com/microsoft/TypeScript/issues/34523#issuecomment-700491122
export const compareRouteIsNewQuery = (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
    !(_.isEqual(to.query, from.query) && _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page')));
export const routeNameWithPage = (name: string) => (_.endsWith(name, '+p') ? name : `${name}+p`);
// https://github.com/vuejs/vue-router-next/issues/1184
// eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
export const routePageParamNullSafe = (r: RouteLocationNormalized) => Number(r.params.page ?? 1);

const lazyLoadRouteView = async (component: Promise<Component>) => {
    NProgress.start();
    const loadingBlocksDom = document.getElementById('loadingBlocksRouteUpdate');
    const containersDom = ['.container', '.container-fluid:not(#nav)'].flatMap(i => [...document.querySelectorAll(i)]);
    loadingBlocksDom?.classList.remove('d-none');
    containersDom.forEach(i => { i.classList.add('d-none') });
    component.catch((e: Error) => { notyShow('error', `${e.name}<br />${e.message}`) });
    return component.finally(() => {
        NProgress.done();
        loadingBlocksDom?.classList.add('d-none');
        containersDom.forEach(i => { i.classList.remove('d-none') });
    });
};
const withPageRoute = <T extends { components: Record<string, () => Promise<Component>> }
| { component: () => Promise<Component> } & { props: boolean }>
(routeName: string, restRoute: T): T & { children: RouteRecordRaw[] } =>
    ({ ...restRoute, children: [{ ...restRoute, path: 'page/:page(\\d+)', name: `${routeName}+p` }] });

const userRoute = { component: async () => lazyLoadRouteView(import('@/views/User.vue')), props: true };
const postRoute = { components: { escapeContainer: async () => lazyLoadRouteView(import('@/views/Post.vue')) } };
export default createRouter({
    history: createWebHistory(process.env.VUE_APP_PUBLIC_PATH),
    routes: [
        {
            path: '/:pathMatch(.*)*',
            name: '404',
            component: PlaceholderError,
            props: r => ({ error: { errorCode: 404, errorInfo: `${r.path}` } })
        },
        { path: '/', name: 'index', component: Index },
        {
            path: '/p',
            name: 'post',
            ...postRoute,
            children: [
                { path: 'page/:page(\\d+)', name: 'post+p', ...postRoute },
                { path: 'f/:fid(\\d+)', name: 'post/fid', ...withPageRoute('post/fid', postRoute) },
                { path: 't/:tid(\\d+)', name: 'post/tid', ...withPageRoute('post/tid', postRoute) },
                { path: 'p/:pid(\\d+)', name: 'post/pid', ...withPageRoute('post/pid', postRoute) },
                { path: 'sp/:spid(\\d+)', name: 'post/spid', ...withPageRoute('post/spid', postRoute) },
                { path: ':pathMatch(.*)*', name: 'post/param', ...withPageRoute('post/param', postRoute) }
            ]
        },
        {
            path: '/u',
            name: 'user',
            ...userRoute,
            children: [
                { path: 'page/:page(\\d+)', name: 'user+p', ...userRoute },
                { path: 'id/:uid(\\d+)', name: 'user/uid', ...withPageRoute('user/uid', userRoute) },
                { path: 'n/:name', name: 'user/name', ...withPageRoute('user/name', userRoute) },
                { path: 'dn/:displayName', name: 'user/displayName', ...withPageRoute('user/displayName', userRoute) }
            ]
        },
        { path: '/status', name: 'status', component: async () => lazyLoadRouteView(import('@/views/Status.vue')) },
        { path: '/stats', name: 'stats', component: async () => lazyLoadRouteView(import('@/views/Stats.vue')) },
        { path: '/bilibiliVote', name: 'bilibiliVote', component: async () => lazyLoadRouteView(import('@/views/BilibiliVote.vue')) }
    ],
    linkActiveClass: 'active',
    scrollBehavior(to, from, savedPosition) {
        // 'href' property will not exist in from when user refresh page: https://next.router.vuejs.org/api/#resolve
        if ('href' in from && savedPosition !== null) return savedPosition;

        if (componentCustomScrollBehaviour.value !== undefined) {
            const ret = componentCustomScrollBehaviour.value(to, from, savedPosition) as ReturnType<RouterScrollBehavior> | undefined;
            if (ret !== undefined) return ret;
        }

        if (to.hash) return { el: to.hash, top: 0 };
        if (from.name !== undefined) { // from.name will be undefined when user refresh page
            assertRouteNameIsStr(to.name);
            assertRouteNameIsStr(from.name);
            // scroll to top when the prefix of route name changed
            if (to.name.split('/')[0] !== from.name.split('/')[0]) return { top: 0 };
        }
        return false;
    }
});
