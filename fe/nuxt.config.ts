import type { PluginVisualizerOptions } from 'rollup-plugin-visualizer';
import { keysWithSameValue } from './src/utils';
import { analyzer } from 'vite-bundle-analyzer';
import { access, constants } from 'node:fs/promises';

const tryAccessDevServerCert = async (): Promise<NonNullable<Parameters<typeof defineNuxtConfig>[0]['devServer']>['https']> => {
    // pin https cert to prevent showing https://chromium.googlesource.com/chromium/src/+/lkgr/components/security_interstitials/ after every nuxt restart
    // https://stackoverflow.com/questions/10175812/how-can-i-generate-a-self-signed-ssl-certificate-using-openssl/41366949#41366949
    // openssl req -x509 -newkey ed448 -days 365 -noenc -keyout nuxt-dev.key -out nuxt-dev.crt -subj '/CN=localhost'
    const key = './nuxt-dev.key';
    const cert = './nuxt-dev.crt';
    try {
        await access(key, constants.R_OK);
        await access(cert, constants.R_OK);

        return { key, cert };
    } catch {
        return true;
    }
};

export default defineNuxtConfig({
    compatibilityDate: '2025-08-01',
    devServer: { https: await tryAccessDevServerCert() },
    srcDir: 'src',
    imports: { dirs: ['api/**', 'utils/**'] },
    modules: [
        '@nuxt/eslint',
        '@pinia/nuxt',
        '@vueuse/nuxt',
        '@ant-design-vue/nuxt',
        '@n0099/vue-query-nuxt',
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
                    retry: false,
                    retryOnMount: false // https://github.com/TanStack/query/discussions/4956#discussioncomment-4950241
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
    typescript: {
        tsConfig: { include: ['../vue-query.config.ts'] }, // https://github.com/Hebilicious/vue-query-nuxt/issues/119
        nodeTsConfig: { include: ['../eslint.config.ts', '../stylelint.config.ts'] }
    },
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
        plugins: [ // .nuxt/dist/(client|server)/vite-bundle-analyzer.html
            analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
        ],
        build: { target: 'esnext' },
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
    postcss: {
        // eslint-disable-next-line @typescript-eslint/naming-convention
        plugins: { 'postcss-preset-env': { minimumVendorImplementations: 2 } }
    },
    experimental: {
        viewTransition: true,
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
