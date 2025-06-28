import type { RouteLocationRaw } from 'vue-router';
import _ from 'lodash';

export type RouteObjectRaw = Exclude<RouteLocationRaw, string>;
export const useTriggerRouteUpdateStore = defineStore('triggerRouteUpdate', () => {
    const route = useRoute();
    const router = useRouter();
    const latestRouteUpdateBy = ref<Record<string, RouteObjectRaw | undefined>>({});

    const trigger = (triggeredBy: string, route: RouteObjectRaw) => {
        latestRouteUpdateBy.value[triggeredBy] = route;
    };
    const pushOrReplace = (triggeredBy: string) => async (to: RouteObjectRaw) => {
        trigger(triggeredBy, to);

        return route.fullPath === router.resolve(to).fullPath
            ? router.replace({ ...to, force: true })
            : router.push(to);
    };
    const isTriggeredBy = (triggeredBy: string, route: RouteObjectRaw) => {
        const originRoute = latestRouteUpdateBy.value[triggeredBy];
        latestRouteUpdateBy.value[triggeredBy] = undefined;

        // https://github.com/lodash/lodash/issues/3887 https://z.n0099.net/#narrow/near/83966
        return originRoute !== undefined && _.isMatch(route, originRoute);
    };

    return { latestRouteUpdateBy, pushOrReplace, isTriggeredBy };
});
