import vite from './vite.config';
import _ from 'lodash';

const envs = [
    'apiEndpointPrefix',
    'gaMeasurementID',
    'recaptchaSiteKey',
    'instanceName',
    'footerText'
];
// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    devServer: { https: true },
    devtools: { enabled: true },
    srcDir: 'src/',
    imports: { dirs: ['@/api/**', 'utils/**'] },
    components: [{ path: '@/components', pathPrefix: false }],
    modules: ['@pinia/nuxt', '@nuxt/eslint', '@hebilicious/vue-query-nuxt'],
    pinia: { storesDirs: ['src/stores/**'] },
    eslint: { config: { standalone: false } },
    vueQuery: {
        queryClientOptions: {
            defaultOptions: {
                queries: {
                    refetchOnWindowFocus: false,
                    staleTime: Infinity,
                    retry: false
                }
            }
        }
    },
    vite,
    runtimeConfig: {
        public: _.zipObject(envs, envs.map(() => ''))
    }
});
