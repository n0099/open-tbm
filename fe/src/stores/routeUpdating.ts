export const useRouteUpdatingStore = defineStore('isRouteUpdating', () => {
    const globalLoadingStore = useGlobalLoadingStore();
    let timeoutId = 0;
    const isUpdating = ref(false);
    const start = () => { isUpdating.value = true };
    const stop = () => { isUpdating.value = false };

    watchEffect(() => {
        if (isUpdating.value && import.meta.client) {
            timeoutId = window.setTimeout(() => { globalLoadingStore.start() }, 100);
            window.setTimeout(stop, 10000);
        } else {
            clearTimeout(timeoutId);
            globalLoadingStore.stop();
        }
    });

    return { isUpdating, start, stop };
});
