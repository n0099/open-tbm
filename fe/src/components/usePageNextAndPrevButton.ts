import { assertRouteNameIsStr, routeNameSuffix, routeNameWithPage } from '@/router';
import { removeEnd } from '@/shared';
import { computed } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRoute } from 'vue-router';
import _ from 'lodash';

export { default as PageNextButton } from './PageNextButton.vue';
export { default as PagePrevButton } from './PagePrevButton.vue';
export const usePageRoutes = (currentPage: number) => computed<{ [P in 'next' | 'prev']: RouteLocationRaw }>(() => {
    const route = useRoute();
    assertRouteNameIsStr(route.name);
    const name = routeNameWithPage(route.name);
    const { query } = route;
    return {
        prev: currentPage - 1 === 1 // to prevent '/page/1' occurs in route path
            ? { query, name: removeEnd(route.name, routeNameSuffix.page), params: _.omit(route.params, 'page') }
            : { query, name, params: { ...route.params, page: currentPage - 1 } },
        next: { query, name, params: { ...route.params, page: currentPage + 1 } }
    };
});
