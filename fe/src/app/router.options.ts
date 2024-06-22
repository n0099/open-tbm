import type { RouterConfig } from 'nuxt/schema';
import _ from 'lodash';

const withCursorRoute = (path: string, name: string): Omit<RouteRecordSingleViewWithChildren, 'component'> =>
    ({
        path,
        name,
        children: [{ // see `App\Http\Controllers\PostsQuery->query()` in be
        // non capture group (?:) and escaping `)` is required for regex in vue route
            path: 'cursor/:cursor((?:(?:[A-Za-z0-9-_]{4}\\)*(?:[A-Za-z0-9-_]{2,3}\\)(?:,|$\\)|,\\){5,6})',
            name: `${name}${routeNameSuffix.cursor}`
        } as RouteRecordRaw]
    });
const redirectRoute = (before: string, after: string): RouteRecordRedirect[] => [{
    path: `${before}/:pathMatch(.*)*`,
    redirect: to =>
        `${after}/${_.isArray(to.params.pathMatch) ? to.params.pathMatch.join('/') : to.params.pathMatch}`
}, { path: before, redirect: after }];

export default {
    routes: _routes => {
        const post = _routes.find(p => p.path === '/posts');
        const user = _routes.find(p => p.path === '/users');
        const postChildren = [
            withCursorRoute('fid/:fid(\\d+)', 'posts/fid'),
            withCursorRoute('tid/:tid(\\d+)', 'posts/tid'),
            withCursorRoute('pid/:pid(\\d+)', 'posts/pid'),
            withCursorRoute('spid/:spid(\\d+)', 'posts/spid'),
            {
                path: ':idType(f|t|p|sp)/:id(\\d+)',
                redirect: (to: RouteLocation) =>
                    _.isString(to.params.idType) && _.isString(to.params.id)
                    && `/posts/${to.params.idType}id/${to.params.id}`
            },
            withCursorRoute(':pathMatch(.*)*', 'posts/param')
        ];
        const userChildren = [
            withCursorRoute('id/:uid(\\d+)', 'users/uid'),
            ...redirectRoute('n', '/users/name'),
            withCursorRoute('name/:name', 'users/name'),
            ...redirectRoute('dn', '/users/displayName'),
            withCursorRoute('displayName/:displayName', 'users/displayName')
        ];

        return [
            ..._routes,
            ...redirectRoute('/p', '/posts'),
            _.merge(post, { children: postChildren }),
            ...redirectRoute('/u', '/users'),
            _.merge(user, { children: userChildren })
        ];
    },
    async scrollBehavior(to, from, savedPosition) {
        if (savedPosition !== null)
            return savedPosition;

        const routeScrollBehavior = useRouteScrollBehaviorStore().get;
        if (routeScrollBehavior !== undefined) {
            const ret: ReturnType<RouterScrollBehavior> | undefined =
                routeScrollBehavior(to, from, savedPosition);
            if (ret !== undefined)
                return ret;
        }

        if (to.hash !== '')
            return { el: to.hash, top: 0 };
        if (from.name !== undefined) { // when user refresh page
            assertRouteNameIsStr(to.name);
            assertRouteNameIsStr(from.name);

            if (isPathsFirstDirectorySame(to.path, from.path))
                return { top: 0 };
        }

        return false;
    }
} as RouterConfig;
