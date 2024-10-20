import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import _ from 'lodash';

export const assertRouteNameIsStr: (name: RouteLocationNormalized['name']) => asserts name is string = name => {
    if (!_.isString(name))
        throw new Error('https://github.com/vuejs/vue-router-next/issues/1185');
}; // https://github.com/microsoft/TypeScript/issues/34523#issuecomment-700491122
export const routeNameSuffix = { cursor: '/cursor' } as const;
export const routeNameWithCursor = (name: RouteLocationNormalized['name']) => {
    assertRouteNameIsStr(name);

    return _.endsWith(name, routeNameSuffix.cursor) ? name : `${name}${routeNameSuffix.cursor}`;
};
export const routeNameWithoutCursor = (name: RouteLocationNormalized['name']) => {
    assertRouteNameIsStr(name);

    return removeEnd(name, routeNameSuffix.cursor);
};

// https://github.com/vuejs/vue-router-next/issues/1184
export const getRouteCursorParam = (route: RouteLocationNormalized): Cursor =>
    (_.isString(route.params.cursor) ? route.params.cursor : '');
export const getNextCursorRoute = (route: RouteLocationNormalized, nextCursor?: Cursor | null): RouteLocationRaw => {
    assertRouteNameIsStr(route.name);
    const name = routeNameWithCursor(route.name);

    return { name, params: { cursor: nextCursor } };
};

export const compareRouteIsNewQuery = (to: RouteLocationNormalized, from: RouteLocationNormalized) =>
    !(_.isEqual(to.query, from.query) && _.isEqual(_.omit(to.params, 'cursor'), _.omit(from.params, 'cursor')));
export const isPathsFirstDirectorySame = (a: string, b: string) =>
    a.split('/')[1] === b.split('/')[1];
