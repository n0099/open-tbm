export const useRouteUpdatingStore = defineStore('isRouteUpdating', () => {
    const globalLoadingStore = useGlobalLoadingStore();
    let timeoutId = 0;
    const isUpdating = ref(false);
    const start = () => { isUpdating.value = true };
    const end = () => { isUpdating.value = false };

    watchEffect(() => {
        if (!import.meta.client)
            return;
        if (isUpdating.value) {
            timeoutId = window.setTimeout(() => { globalLoadingStore.start() }, 100);
            window.setTimeout(end, 10000);
        } else {
            clearTimeout(timeoutId);
            globalLoadingStore.stop();
        }
    });

    return { isUpdating, start, end };
});
