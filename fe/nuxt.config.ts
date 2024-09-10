import type { PluginVisualizerOptions } from 'rollup-plugin-visualizer';
import { keysWithSameValue } from './src/utils';
import { analyzer } from 'vite-bundle-analyzer';

export default defineNuxtConfig({
    compatibilityDate: '2024-07-04',
    devServer: { https: true },
    devtools: { enabled: false },
    srcDir: 'src',
    imports: { dirs: ['api/**', 'utils/**'] },
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
    site: {
        name: `open-tbm @ ${process.env.NUXT_PUBLIC_INSTANCE_NAME}`,
        defaultLocale: 'zh'
    },
    sitemap: {
        sitemaps: true,
        appendSitemaps: [{ sitemap: `${process.env.NUXT_PUBLIC_BE_URL}/sitemaps/forums` }]
    },
    ogImage: { fonts: ['Noto Sans SC'] },
    schemaOrg: { identity: 'Organization' },
    features: { inlineStyles: false }, // https://github.com/nuxt/nuxt/issues/21821
    sourcemap: true,
    build: {
        analyze: {
            filename: '.nuxt/analyze/rollup-plugin-visualizer.html',
            gzipSize: true,
            brotliSize: true
        } as PluginVisualizerOptions
    },
    vue: { propsDestructure: true },
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
        componentIslands: true,
        asyncContext: true
    },
    runtimeConfig: {
        public: keysWithSameValue([
            'beUrl',
            'gaMeasurementId',
            'recaptchaSiteKey',
            'instanceName',
            'footerText'
        ], '')
    }
});
