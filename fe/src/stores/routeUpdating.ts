import nprogress from 'nprogress';

export const useRouteUpdatingStore = defineStore('isRouteUpdating', () => {
    let timeoutId = 0;
    const isUpdating = ref(false);
    const start = () => {
        timeoutId = window.setTimeout(() => { isUpdating.value = true }, 100);
    };
    const end = () => { isUpdating.value = false };

    watchEffect(() => {
        if (isUpdating.value) {
            if (import.meta.client)
                nprogress.start();
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
