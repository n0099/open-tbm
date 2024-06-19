import nprogress from 'nprogress';
import _ from 'lodash';

export default defineNuxtPlugin(nuxt => {
    const isRouteChanging = useState('isRouteChanging', () => false);
    let timeoutId = 0;
    nuxt.hook('page:loading:start', () => {
        // to prevent Hydration class mismatch since the initial ssr will fire this hook
        // https://github.com/nuxt/nuxt/blob/77685e43e4b4602151f0d2e7fbfa8bc372308907/packages/nuxt/src/app/composables/loading-indicator.ts#L58
        if (nuxt.isHydrating)
            return;
        timeoutId = window.setTimeout(() => {
            nprogress.start();
            isRouteChanging.value = true;
        }, 100);
    });
    // page:finish triggers latter than page:loading:end which fires just after 
    // the new route component fetched but not it's all dependencies
    nuxt.hook('page:finish', () => {
        clearTimeout(timeoutId);
        nprogress.done();
        isRouteChanging.value = false;
    });
});
