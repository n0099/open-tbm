export default defineVueQueryPluginHook(() => {
    return {
        dehydrateOptions: { // https://github.com/Hebilicious/vue-query-nuxt/issues/108#issuecomment-2908075286
            shouldDehydrateQuery: () => true
        }
    };
});
