import type { RouterScrollBehavior } from 'vue-router';

export const useRouteScrollBehaviorStore = defineStore('routeScrollBehavior', () => {
    const scrollBehavior = ref<RouterScrollBehavior>();
    const set = (callback: RouterScrollBehavior) => {
        scrollBehavior.value = callback;
        onUnmounted(() => { scrollBehavior.value = undefined });
    };

    // https://github.com/lisilinhart/eslint-plugin-pinia/issues/52
    // eslint-disable-next-line pinia/require-setup-store-properties-export
    return { get: scrollBehavior, set };
});
