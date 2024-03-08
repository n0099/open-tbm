import PlaceholderError from '@/components/placeholders/PlaceholderError.vue';
import Index from '@/views/Index.vue';
import { ApiResponseError } from '@/api';
import type { Cursor } from '@/api/index.d';
import { notyShow } from '@/shared';
import type { Component } from 'vue';
import { onUnmounted, ref } from 'vue';
import type { RouteLocation, RouteLocationNormalized, RouteRecordMultipleViews, RouteRecordMultipleViewsWithChildren, RouteRecordRedirect, RouteRecordSingleView, RouteRecordSingleViewWithChildren, RouterScrollBehavior, _RouteRecordBase } from 'vue-router';
import { createRouter, createWebHistory } from 'vue-router';
import nprogress from 'nprogress';
import * as _ from 'lodash-es';

const componentCustomScrollBehaviour = ref<RouterScrollBehavior>();
export const setComponentCustomScrollBehaviour = (cb: RouterScrollBehavior) => {
    componentCustomScrollBehaviour.value = cb;
    onUnmounted(() => { componentCustomScrollBehaviour.value = undefined });
};

export const assertRouteNameIsStr: (name: RouteLocationNormalized['name']) => asserts name is string = name => {
    if (!_.isString(name))
        throw new Error('https://github.com/vuejs/vue-router-next/issues/1185');
}; // https://github.com/microsoft/TypeScript/issues/34523#issuecomment-700491122
export const compareRouteIsNewQuery = (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
    !(_.isEqual(to.query, from.query) && _.isEqual(_.omit(to.params, 'cursor'), _.omit(from.params, 'cursor')));
export const routeNameSuffix = { cursor: '/cursor' } as const;
export const routeNameWithCursor = (name: string) =>
    (_.endsWith(name, routeNameSuffix.cursor) ? name : `${name}${routeNameSuffix.cursor}`);

// https://github.com/vuejs/vue-router-next/issues/1184
// eslint-disable-next-line @typescript-eslint/no-unnecessary-condition
export const getRouteCursorParam = (r: RouteLocationNormalized): Cursor => r.params.cursor?.toString() ?? '';

const lazyLoadRouteView = async (lazyComponent: Promise<Component>) => {
    nprogress.start();
    const loadingBlockEl = document.querySelector('#loadingBlock');
    const containersEl = ['.container', '.container-fluid:not(#nav)']
        .flatMap(i => _.toArray<Element>(document.querySelectorAll(i)));
    loadingBlockEl?.classList.remove('d-none');
    containersEl.forEach(i => { i.classList.add('d-none') });

    return lazyComponent
        .catch((e: Error) => { notyShow('error', `${e.name}<br />${e.message}`) })
        .finally(() => {
            nprogress.done();
            loadingBlockEl?.classList.add('d-none');
            containersEl.forEach(i => { i.classList.remove('d-none') });
        });
};

type ParentRoute = Omit<RouteRecordSingleView, 'path'> | Omit<RouteRecordMultipleViews, 'path'>;
const withChildrenRoute = (path: string, name: string, parentRoute: ParentRoute, childrenBaseRoute: _RouteRecordBase)
: RouteRecordSingleViewWithChildren | RouteRecordMultipleViewsWithChildren =>
    ({
        path,
        name,
        ...parentRoute,
        children: [{
            ...parentRoute,
            ...childrenBaseRoute
        } as RouteRecordSingleView | RouteRecordMultipleViews]
    });
const withCursorRoute = (parentRoute: ParentRoute, path: string, name: string): ReturnType<typeof withChildrenRoute> =>
    withChildrenRoute(path, name, parentRoute, { // see `App\Http\Controllers\PostsQuery->query()` in be
        // non capture group (?:) and escaping `)` is required for regex in vue route
        path: 'cursor/:cursor((?:(?:[A-Za-z0-9-_]{4}\\)*(?:[A-Za-z0-9-_]{2,3}\\)(?:,|$\\)|,\\){5,6})',
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
const redirectRoute = (before: string, after: string): RouteRecordRedirect[] => [{
    path: `${before}/:pathMatch(.*)*`,
    redirect: to =>
        `${after}/${_.isArray(to.params.pathMatch) ? to.params.pathMatch.join('/') : to.params.pathMatch}`
}, { path: before, redirect: after }];

export default createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/:pathMatch(.*)*',
            name: '404',
            component: PlaceholderError,
            props: (route): InstanceType<typeof PlaceholderError>['$props'] =>
                ({ error: new ApiResponseError(404, route.path) })
        },
        { path: '/', name: 'index', component: Index },
        ...redirectRoute('/p', '/posts'),
        _.merge(withCursorRoute(postRoute, '/posts', 'post'),
            {
                children: [
                    withCursorRoute(postRoute, 'fid/:fid(\\d+)', 'post/fid'),
                    withCursorRoute(postRoute, 'tid/:tid(\\d+)', 'post/tid'),
                    withCursorRoute(postRoute, 'pid/:pid(\\d+)', 'post/pid'),
                    withCursorRoute(postRoute, 'spid/:spid(\\d+)', 'post/spid'),
                    {
                        path: ':idType(f|t|p|sp)/:id(\\d+)',
                        redirect: (to: RouteLocation) =>
                            _.isString(to.params.idType) && _.isString(to.params.id)
                            && `/posts/${to.params.idType}id/${to.params.id}`
                    },
                    withCursorRoute(postRoute, ':pathMatch(.*)*', 'post/param')
                ]
            }),
        ...redirectRoute('/u', '/users'),
        _.merge(withCursorRoute(userRoute, '/users', 'user'),
            {
                children: [
                    withCursorRoute(userRoute, 'id/:uid(\\d+)', 'user/uid'),
                    ...redirectRoute('n', '/users/name'),
                    withCursorRoute(userRoute, 'name/:name', 'user/name'),
                    ...redirectRoute('dn', '/users/displayName'),
                    withCursorRoute(userRoute, 'displayName/:displayName', 'user/displayName')
                ]
            }),
        withViewRoute(import('@/views/Status.vue'), 'status'),
        withViewRoute(import('@/views/Stats.vue'), 'stats'),
        withViewRoute(import('@/views/BilibiliVote.vue'), 'bilibiliVote')
    ],
    linkActiveClass: 'active',
    async scrollBehavior(to, from, savedPosition) {
        if (savedPosition !== null)
            return savedPosition;

        if (componentCustomScrollBehaviour.value !== undefined) {
            const ret: ReturnType<RouterScrollBehavior> | undefined =
                componentCustomScrollBehaviour.value(to, from, savedPosition);
            if (ret !== undefined)
                return ret;
        }

        if (to.hash)
            return { el: to.hash, top: 0 };
        if (from.name !== undefined) { // when user refresh page
            assertRouteNameIsStr(to.name);
            assertRouteNameIsStr(from.name);

            // scroll to top when the prefix of route name changed
            if (to.name.split('/')[0] !== from.name.split('/')[0])
                return { top: 0 };
        }

        return false;
    }
});
