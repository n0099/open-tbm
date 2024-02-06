import type { Cursor } from '@/api/index.d';
import { assertRouteNameIsStr, routeNameWithCursor } from '@/router';
import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';

export { default as PageCurrentButton } from './PageCurrentButton.vue';
export { default as PageNextButton } from './PageNextButton.vue';
export const useNextCursorRoute = (route: RouteLocationNormalized, nextCursor: Cursor): RouteLocationRaw => {
    assertRouteNameIsStr(route.name);
    const name = routeNameWithCursor(route.name);
    const { query, params } = route;

    return { query, name, params: { ...params, cursor: nextCursor } };
};
