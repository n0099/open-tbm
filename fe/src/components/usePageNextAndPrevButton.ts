import { assertRouteNameIsStr, routeNameWithCursor } from '@/router';
import type { Cursor } from '@/api/index.d';
import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRoute } from 'vue-router';

export { default as PageNextButton } from './PageNextButton.vue';
export { default as PagePrevButton } from './PagePrevButton.vue';
export const useNextPageRoute = (nextPageCursor: Cursor) => computed<RouteLocationRaw>(() => {
    const route = useRoute();
    assertRouteNameIsStr(route.name);
    const name = routeNameWithCursor(route.name);
    const { query } = route;
    return { query, name, params: { ...route.params, cursor: nextPageCursor } };
});
