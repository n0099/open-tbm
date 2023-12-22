import { ref } from 'vue';
import type { RouteLocationRaw } from 'vue-router';
import { useRouter } from 'vue-router';
import { defineStore } from 'pinia';

export const useTriggerRouteUpdateStore = defineStore('triggerRouteUpdate', () => {
    const router = useRouter();
    const latestRouteUpdateBy = ref<Record<string, boolean>>({});
    const trigger = (triggeredBy: string) => { latestRouteUpdateBy.value[triggeredBy] = true };
    const replaceRoute = (triggeredBy: string) => async (to: RouteLocationRaw) => {
        trigger(triggeredBy);

        return router.replace(to);
    };
    const isTriggeredBy = (triggeredBy: string) => {
        const originValue = latestRouteUpdateBy.value[triggeredBy];
        latestRouteUpdateBy.value[triggeredBy] = false;

        return originValue;
    };

    return { latestRouteUpdateBy, trigger, replaceRoute, isTriggeredBy };
});
