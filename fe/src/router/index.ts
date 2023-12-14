import Index from '@/views/Index.vue';
import PlaceholderError from '@/components/PlaceholderError.vue';
import { notyShow } from '@/shared';
import type { Component } from 'vue';
import { onUnmounted, ref } from 'vue';
import type { RouteLocationNormalized, RouteLocationNormalizedLoaded, RouteRecordMultipleViews, RouteRecordMultipleViewsWithChildren, RouteRecordSingleView, RouteRecordSingleViewWithChildren, RouterScrollBehavior, _RouteRecordBase } from 'vue-router';
import { createRouter, createWebHistory } from 'vue-router';
import nprogress from 'nprogress';
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
export const routeNameSuffix = { page: '+p', cursor: '+c' } as const;
export const routeNameWithPage = (name: string) =>
    (_.endsWith(name, routeNameSuffix.page) ? name : `${name}${routeNameSuffix.page}`);
// https://github.com/vuejs/vue-router-next/issues/1184
// eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
export const routePageParamNullSafe = (r: RouteLocationNormalized) => Number(r.params.page ?? 1);

const lazyLoadRouteView = async (component: Promise<Component>) => {
    nprogress.start();
    const loadingBlocksDom = document.getElementById('loadingBlocks');
    const containersDom = ['.container', '.container-fluid:not(#nav)']
        .flatMap(i => [...document.querySelectorAll(i)]);
    loadingBlocksDom?.classList.remove('d-none');
    containersDom.forEach(i => { i.classList.add('d-none') });
    component.catch((e: Error) => { notyShow('error', `${e.name}<br />${e.message}`) });
    return component.finally(() => {
        nprogress.done();
        loadingBlocksDom?.classList.add('d-none');
        containersDom.forEach(i => { i.classList.remove('d-none') });
    });
};

type ParentRoute = Omit<RouteRecordSingleView, 'path'> | Omit<RouteRecordMultipleViews, 'path'>;
const withChildrenRoute = <T extends ParentRoute>(
    routeName: string,
    parentRoute: T,
    childrenRoute: _RouteRecordBase
): Omit<RouteRecordSingleViewWithChildren, 'path'> | Omit<RouteRecordMultipleViewsWithChildren, 'path'> =>
    ({
        name: routeName,
        ...parentRoute,
        children: [{ ...parentRoute, ...childrenRoute } as RouteRecordSingleView | RouteRecordMultipleViews]
    });
const withPageRoute = <T extends ParentRoute>(routeName: string, parentRoute: T): ReturnType<typeof withChildrenRoute> =>
    withChildrenRoute(routeName, parentRoute, { path: 'page/:page(\\d+)', name: `${routeName}${routeNameSuffix.page}` });
const withCursorRoute = <T extends ParentRoute>(routeName: string, parentRoute: T): ReturnType<typeof withChildrenRoute> =>
    withChildrenRoute(routeName, parentRoute, { path: 'cursor/:cursor(\\d+)', name: `${routeName}${routeNameSuffix.cursor}` });

const userRoute: ParentRoute = {
    component: async () => lazyLoadRouteView(import('@/views/User.vue')),
    props: true
};
const postRoute: ParentRoute = {
    components: { escapeContainer: async () => lazyLoadRouteView(import('@/views/Post.vue')) }
};

export default createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/:pathMatch(.*)*',
            name: '404',
            component: PlaceholderError,
            props: r => ({ error: { errorCode: 404, errorInfo: `${r.path}` } })
        },
        { path: '/', name: 'index', component: Index },
        _.merge({ path: '/p', ...withCursorRoute('post', postRoute) },
            {
                children: [
                    { path: 'f/:fid(\\d+)', ...withCursorRoute('post/fid', postRoute) },
                    { path: 't/:tid(\\d+)', ...withCursorRoute('post/tid', postRoute) },
                    { path: 'p/:pid(\\d+)', ...withCursorRoute('post/pid', postRoute) },
                    { path: 'sp/:spid(\\d+)', ...withCursorRoute('post/spid', postRoute) },
                    { path: ':pathMatch(.*)*', ...withCursorRoute('post/param', postRoute) }
                ]
            }),
        _.merge({ path: '/u', ...withPageRoute('user', userRoute) },
            {
                children: [
                    { path: 'id/:uid(\\d+)', ...withPageRoute('user/uid', userRoute) },
                    { path: 'n/:name', ...withPageRoute('user/name', userRoute) },
                    { path: 'dn/:displayName', ...withPageRoute('user/displayName', userRoute) }
                ]
            }),
        { path: '/status', name: 'status', component: async () => lazyLoadRouteView(import('@/views/Status.vue')) },
        { path: '/stats', name: 'stats', component: async () => lazyLoadRouteView(import('@/views/Stats.vue')) },
        { path: '/bilibiliVote', name: 'bilibiliVote', component: async () => lazyLoadRouteView(import('@/views/BilibiliVote.vue')) }
    ],
    linkActiveClass: 'active',
    async scrollBehavior(to, from, savedPosition) {
        // 'href' property will not exist in from when user refresh page: https://next.router.vuejs.org/api/#resolve
        if ('href' in from && savedPosition !== null) return savedPosition;

        if (componentCustomScrollBehaviour.value !== undefined) {
            const ret: ReturnType<RouterScrollBehavior> | undefined
                = componentCustomScrollBehaviour.value(to, from, savedPosition);
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
