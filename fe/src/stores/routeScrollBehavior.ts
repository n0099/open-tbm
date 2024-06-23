import type { RouterScrollBehavior } from 'vue-router';

export const useRouteScrollBehaviorStore = defineStore('routeScrollBehavior', () => {
    const scrollBehavior = ref<RouterScrollBehavior>();
    const set = (callback: RouterScrollBehavior) => {
        scrollBehavior.value = callback;
        onUnmounted(() => { scrollBehavior.value = undefined });
    };

    return { get: scrollBehavior, set };
});
