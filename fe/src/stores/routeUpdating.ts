import nprogress from 'nprogress';

export const useRouteUpdatingStore = defineStore('isRouteUpdating', () => {
    let timeoutId = 0;
    const isUpdating = ref(false);
    const start = () => { isUpdating.value = true };
    const end = () => { isUpdating.value = false };

    watchEffect(() => {
        if (isUpdating.value) {
            if (import.meta.client)
                timeoutId = window.setTimeout(nprogress.start, 100);
            window.setTimeout(end, 10000);
        }
        else {
            clearTimeout(timeoutId);
            if (import.meta.client) 
                nprogress.done();
        }
    })

    return { isUpdating, start, end };
});
