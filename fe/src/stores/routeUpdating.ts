export const useRouteUpdatingStore = defineStore('isRouteUpdating', () => {
    const globalLoadingStore = useGlobalLoadingStore();
    let debounceId = 0;
    const isUpdating = ref(false);
    const start = () => { isUpdating.value = true };
    const stop = () => { isUpdating.value = false };

    watchEffect(() => {
        if (!import.meta.client)
            return;
        if (isUpdating.value) {
            debounceId = window.setTimeout(() => { globalLoadingStore.start() }, 100);
            window.setTimeout(stop, 10000);
        } else {
            clearTimeout(debounceId);
            globalLoadingStore.stop();
        }
    });

    return { isUpdating, start, stop };
});
