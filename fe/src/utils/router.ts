import _ from 'lodash';

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
export const getRouteCursorParam = (route: RouteLocationNormalized): Cursor => route.params.cursor?.toString() ?? '';
export const getNextCursorRoute = (route: RouteLocationNormalized, nextCursor?: Cursor | null): RouteLocationRaw => {
    assertRouteNameIsStr(route.name);
    const name = routeNameWithCursor(route.name);
    const { query, params } = route;

    return { query, name, params: { ...params, cursor: nextCursor } };
};

export const isPathsFirstDirectorySame = (a: string, b: string) =>
    a.split('/')[1] === b.split('/')[1];
