import type { PluginVisualizerOptions } from 'rollup-plugin-visualizer';
import { objectWithSameValues } from './src/utils';
import { analyzer } from 'vite-bundle-analyzer';

export default defineNuxtConfig({
    devServer: { https: true },
    devtools: { enabled: true },
    srcDir: 'src',
    imports: { dirs: ['api/**', 'utils/**'] },
    components: [{ path: '@/components', pathPrefix: false }],
    modules: [
        '@nuxt/eslint',
        '@pinia/nuxt',
        '@vueuse/nuxt',
        '@ant-design-vue/nuxt',
        '@hebilicious/vue-query-nuxt',
        '@vesp/nuxt-fontawesome',
        '@nuxtjs/seo'
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
    features: {
        inlineStyles: false // https://github.com/nuxt/nuxt/issues/21821
    },
    sourcemap: true,
    build: {
        analyze: {
            filename: '.nuxt/analyze/rollup-plugin-visualizer.html',
            gzipSize: true,
            brotliSize: true
        } as PluginVisualizerOptions
    },
    vite: {
        plugins: [
            analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
        ],
        build: { target: 'esnext' },
        assetsInclude: ['**/*.avifs']
    },
    experimental: {
        viewTransition: true,
        respectNoSSRHeader: true,
        componentIslands: true
    },
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
