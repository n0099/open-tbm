import type { Cursor } from '@/api/index.d';
import { assertRouteNameIsStr, routeNameWithCursor } from '@/router';
import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRoute } from 'vue-router';

export { default as PageCurrentButton } from './PageCurrentButton.vue';
export { default as PageNextButton } from './PageNextButton.vue';
export const useNextCursorRoute = (nextCursor: Cursor) => computed<RouteLocationRaw>(() => {
    const route = useRoute();
    assertRouteNameIsStr(route.name);
    const name = routeNameWithCursor(route.name);
    const { query, params } = route;

    return { query, name, params: { ...params, cursor: nextCursor } };
});
