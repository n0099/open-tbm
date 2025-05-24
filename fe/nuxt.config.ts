import type { PluginVisualizerOptions } from 'rollup-plugin-visualizer';
import { keysWithSameValue } from './src/utils';
import { analyzer } from 'vite-bundle-analyzer';

export default defineNuxtConfig({
    compatibilityDate: '2025-04-24',
    devServer: { https: true },
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
        },
        vueQueryPluginOptions: { enableDevtoolsV6Plugin: true }
    },
    site: {
        name: `open-tbm${
            process.env.NUXT_PUBLIC_INSTANCE_NAME === undefined
                ? ''
                : ` @ ${process.env.NUXT_PUBLIC_INSTANCE_NAME}`
        }`,
        defaultLocale: 'zh'
    },
    robots: { robotsTxt: false }, // https://github.com/nuxt-modules/robots/commit/c8958975b09d0e9aa3651505d023be43b0da4ec2
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
        assetsInclude: ['**/*.avifs'],
        $server: { // https://github.com/nuxt/nuxt/issues/32175#issuecomment-2898200099
            build: {
                rollupOptions: {
                    output: {
                        preserveModules: true
                    }
                }
            }
        }
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
            'instanceName',
            'footerText',
            'tiebaImageProxy',
            'tiebaImageReferrerPolicy'
        ], '')
    }
});
