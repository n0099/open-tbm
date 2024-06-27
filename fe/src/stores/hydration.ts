// https://github.com/nuxt/nuxt/issues/25662#issuecomment-2185567066
export const useHydrationStore = defineStore('hydration', () => {
    const isHydratingState = ref(false);
    const isHydrationHooked = ref(false);
    const nuxt = useNuxtApp();
    const isHydrating = () => {
        if (!(import.meta.client && (nuxt.isHydrating ?? false)))
            return isHydratingState.value;

        isHydratingState.value ||= true;

        if (!isHydrationHooked.value) {
            isHydrationHooked.value = true;
            nuxt.hooks.hookOnce('app:suspense:resolve', () => {
                isHydratingState.value = false;
            });
        }

        return isHydratingState.value;
    };
    const isHydratingOrSSR = () => import.meta.server || isHydrating();

    return { isHydrating, isHydratingOrSSR };
});
