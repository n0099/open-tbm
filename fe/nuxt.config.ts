import type { PluginVisualizerOptions } from 'rollup-plugin-visualizer';
import vite from './vite.config';
import _ from 'lodash';

const envs = [
    'apiEndpointPrefix',
    'gaMeasurementID',
    'recaptchaSiteKey',
    'instanceName',
    'footerText'
];
export default defineNuxtConfig({
    devServer: { https: true },
    devtools: { enabled: true },
    srcDir: 'src',
    imports: { dirs: ['api/**', 'utils/**'] },
    components: [{ path: '@/components', pathPrefix: false }],
    modules: [
        '@pinia/nuxt',
        '@nuxt/eslint',
        '@hebilicious/vue-query-nuxt',
        '@vueuse/nuxt',
        '@nuxtjs/seo',
        '@ant-design-vue/nuxt',
        '@vesp/nuxt-fontawesome'
    ],
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
    build: {
        analyze: {
            filename: 'dist/rollup-plugin-visualizer.html',
            gzipSize: true,
            brotliSize: true
        } as PluginVisualizerOptions
    },
    vite,
    runtimeConfig: {
        public: _.zipObject(envs, envs.map(() => ''))
    }
});
