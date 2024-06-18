import type { RouteLocationRaw } from 'vue-router';
import { defineStore } from 'pinia';
import _ from 'lodash';

export type RouteObjectRaw = Exclude<RouteLocationRaw, string>;
export const useTriggerRouteUpdateStore = defineStore('triggerRouteUpdate', () => {
    const router = useRouter();
    const latestRouteUpdateBy = ref<Record<string, RouteObjectRaw | undefined>>({});

    const trigger = (triggeredBy: string, route: RouteObjectRaw) => {
        latestRouteUpdateBy.value[triggeredBy] = route;
    };
    const push = (triggeredBy: string) => async (to: RouteObjectRaw) => {
        trigger(triggeredBy, to);

        return router.push(to);
    };
    const replace = (triggeredBy: string) => async (to: RouteObjectRaw) => {
        trigger(triggeredBy, to);

        return router.replace(to);
    };
    const isTriggeredBy = (triggeredBy: string, route: RouteObjectRaw) => {
        const originRoute = latestRouteUpdateBy.value[triggeredBy];
        latestRouteUpdateBy.value[triggeredBy] = undefined;

        // https://github.com/lodash/lodash/issues/3887 https://z.n0099.net/#narrow/near/83966
        return originRoute !== undefined && _.isMatch(route, originRoute);
    };

    return { latestRouteUpdateBy, push, replace, isTriggeredBy };
});
