import { ref, watch } from 'vue';
import { defineStore } from 'pinia';
import nprogress from 'nprogress';
import _ from 'lodash';

export const useLazyLoadRouteViewStore = defineStore('lazyLoadRouteView', () => {
    const loadingBlockEl = document.querySelector('#loadingBlock');
    const isLoading = ref(false);
    let timeoutId = 0;

    watch(isLoading, () => {
        const containersEl = ['.container', '.container-fluid:not(#nav)']
            .flatMap(i => _.toArray<Element>(document.querySelectorAll(i)));
        if (isLoading.value) {
            timeoutId = window.setTimeout(() => {
                nprogress.start();
                loadingBlockEl?.classList.remove('d-none');
                containersEl.forEach(i => { i.classList.add('d-none') });
            }, 100);
        } else {
            clearTimeout(timeoutId);
            nprogress.done();
            loadingBlockEl?.classList.add('d-none');
            containersEl.forEach(i => { i.classList.remove('d-none') });
        }
    });

    return { isLoading };
});
