import Index from '@/views/Index.vue';
import PlaceholderError from '@/components/placeholders/PlaceholderError.vue';
import type { Cursor } from '@/api/index.d';
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
    if (!_.isString(name))
        throw Error('https://github.com/vuejs/vue-router-next/issues/1185');
}; // https://github.com/microsoft/TypeScript/issues/34523#issuecomment-700491122
export const compareRouteIsNewQuery = (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
    !(_.isEqual(to.query, from.query) && _.isEqual(_.omit(to.params, 'page'), _.omit(from.params, 'page')));
export const routeNameSuffix = { page: '+p', cursor: '+c' } as const;
export const routeNameWithCursor = (name: string) =>
    (_.endsWith(name, routeNameSuffix.cursor) ? name : `${name}${routeNameSuffix.cursor}`);

// https://github.com/vuejs/vue-router-next/issues/1184
// eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
export const getRouteCursorParam = (r: RouteLocationNormalized): Cursor => String(r.params.cursor ?? '');

const lazyLoadRouteView = async (lazyComponent: Promise<Component>) => {
    nprogress.start();
    const loadingBlocksDom = document.getElementById('loadingBlocks');
    const containersDom = ['.container', '.container-fluid:not(#nav)']
        .flatMap(i => [...document.querySelectorAll(i)]);
    loadingBlocksDom?.classList.remove('d-none');
    containersDom.forEach(i => { i.classList.add('d-none') });
    lazyComponent.catch((e: Error) => { notyShow('error', `${e.name}<br />${e.message}`) });
    return lazyComponent.finally(() => {
        nprogress.done();
        loadingBlocksDom?.classList.add('d-none');
        containersDom.forEach(i => { i.classList.remove('d-none') });
    });
};

type ParentRoute = Omit<RouteRecordSingleView, 'path'> | Omit<RouteRecordMultipleViews, 'path'>;
const withChildrenRoute = (
    path: string,
    name: string,
    parentRoute: ParentRoute,
    childrenBaseRoute: _RouteRecordBase
): RouteRecordSingleViewWithChildren | RouteRecordMultipleViewsWithChildren =>
    ({
        path,
        name,
        ...parentRoute,
        children: [{
            ...parentRoute,
            ...childrenBaseRoute
        } as RouteRecordSingleView | RouteRecordMultipleViews]
    });
const withPageRoute = (parentRoute: ParentRoute, path: string, name: string): ReturnType<typeof withChildrenRoute> =>
    withChildrenRoute(path, name, parentRoute, {
        path: 'page/:page(\\d+)',
        name: `${name}${routeNameSuffix.page}`
    });
const withCursorRoute = (parentRoute: ParentRoute, path: string, name: string): ReturnType<typeof withChildrenRoute> =>
    withChildrenRoute(path, name, parentRoute, {
        path: 'cursor/:cursor(\\d+)',
        name: `${name}${routeNameSuffix.cursor}`
    });
const withViewRoute = (lazyComponent: Promise<Component>, path: string): RouteRecordSingleView => ({
    path: `/${path}`,
    name: path,
    component: async () => lazyLoadRouteView(lazyComponent)
});

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
        _.merge(withCursorRoute(postRoute, '/p', 'post'),
            {
                children: [
                    withCursorRoute(postRoute, 'f/:fid(\\d+)', 'post/fid'),
                    withCursorRoute(postRoute, 't/:tid(\\d+)', 'post/tid'),
                    withCursorRoute(postRoute, 'p/:pid(\\d+)', 'post/pid'),
                    withCursorRoute(postRoute, 'sp/:spid(\\d+)', 'post/spid'),
                    withCursorRoute(postRoute, ':pathMatch(.*)*', 'post/param')
                ]
            }),
        _.merge(withPageRoute(userRoute, '/u', 'user'),
            {
                children: [
                    withPageRoute(userRoute, 'id/:uid(\\d+)', 'user/uid'),
                    withPageRoute(userRoute, 'n/:name', 'user/name'),
                    withPageRoute(userRoute, 'dn/:displayName', 'user/displayName')
                ]
            }),
        withViewRoute(import('@/views/Status.vue'), 'status'),
        withViewRoute(import('@/views/Stats.vue'), 'stats'),
        withViewRoute(import('@/views/BilibiliVote.vue'), 'bilibiliVote')
    ],
    linkActiveClass: 'active',
    async scrollBehavior(to, from, savedPosition) {
        // 'href' property will not exist in from when user refresh page: https://next.router.vuejs.org/api/#resolve
        if ('href' in from && savedPosition !== null)
            return savedPosition;

        if (componentCustomScrollBehaviour.value !== undefined) {
            const ret: ReturnType<RouterScrollBehavior> | undefined
                = componentCustomScrollBehaviour.value(to, from, savedPosition);
            if (ret !== undefined)
                return ret;
        }

        if (to.hash)
            return { el: to.hash, top: 0 };
        if (from.name !== undefined) { // from.name will be undefined when user refresh page
            assertRouteNameIsStr(to.name);
            assertRouteNameIsStr(from.name);

            // scroll to top when the prefix of route name changed
            if (to.name.split('/')[0] !== from.name.split('/')[0])
                return { top: 0 };
        }
        return false;
    }
});
