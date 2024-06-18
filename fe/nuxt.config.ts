// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    devtools: { enabled: true },
    modules: ['@pinia/nuxt'],
    components: [
        {
            path: '~/components',
            pathPrefix: false
        }
    ],
    imports: {
        dirs: ['api/**']
    },
    css: ['~/assets/styles/style.css']
});
