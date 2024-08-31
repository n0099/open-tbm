import _ from 'lodash';

export const useGlobalLoadingStore = defineStore('globalLoading', () => {
    const isLoading = ref(true); // show initially for noscript

    const start = _.debounce(() => { isLoading.value = true }, 100);
    const stop = () => {
        start.cancel();
        isLoading.value = false;
    };

    return { isLoading, start, stop };
});
