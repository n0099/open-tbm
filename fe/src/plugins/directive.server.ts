// https://github.com/nuxt/nuxt/issues/13382#issuecomment-2192801116
export default defineNuxtPlugin(nuxt => {
    if (import.meta.dev)
        return;
    nuxt.vueApp.directive('viewer', {});
});
