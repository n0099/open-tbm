// https://github.com/nuxt/nuxt/issues/13382#issuecomment-1541610910
export default defineNuxtPlugin(nuxt => {
    nuxt.vueApp.directive('viewer', {});
});
