import { FetchError } from 'ofetch';

export default defineVueQueryPluginHook(() => {
    return {
        dehydrateOptions: { // https://github.com/Hebilicious/vue-query-nuxt/issues/108#issuecomment-2908075286
            shouldDehydrateQuery: query =>
                !(query.state.status === 'error' && query.state.error instanceof FetchError && query.state.error.statusCode === 401)
        }
    };
});
