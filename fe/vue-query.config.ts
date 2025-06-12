import type { PluginHookReturn } from '@n0099/vue-query-nuxt/dist/runtime/types.js';
import { FetchError } from 'ofetch';

export default defineVueQueryPluginHook((): PluginHookReturn => {
    return {
        dehydrateOptions: { // https://github.com/Hebilicious/vue-query-nuxt/issues/108#issuecomment-2908075286
            shouldDehydrateQuery: query =>
                !(query.state.status === 'error' && query.state.error instanceof FetchError && query.state.error.statusCode === 401)
        }
    };
});
