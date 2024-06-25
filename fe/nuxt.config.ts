import type { PluginVisualizerOptions } from 'rollup-plugin-visualizer';
import { objectWithSameValues } from './src/utils';
import vite from './vite.config';

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
                    staleTime: Number.MAX_SAFE_INTEGER, // https://stackoverflow.com/questions/1423081/json-left-out-infinity-and-nan-json-status-in-ecmascript
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
    experimental: {
        viewTransition: true,
        respectNoSSRHeader: true,
        componentIslands: true
    },
    vite,
    runtimeConfig: {
        public: objectWithSameValues([
            'apiEndpointPrefix',
            'gaMeasurementID',
            'recaptchaSiteKey',
            'instanceName',
            'footerText'
        ], '')
    }
});
