import { visualizer } from 'rollup-plugin-visualizer';
import { analyzer } from 'vite-bundle-analyzer';
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
    modules: ['@pinia/nuxt', '@hebilicious/vue-query-nuxt'],
    pinia: { storesDirs: ['src/stores/**'] },
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
    vite: {
        plugins: [
            visualizer({ filename: 'dist/rollup-plugin-visualizer.html', gzipSize: true, brotliSize: true }),
            analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
        ],
        build: { target: 'esnext' },
        assetsInclude: ['**/*.avifs']
    },
    runtimeConfig: {
        public: _.zipObject(envs, envs.map(() => ''))
    }
});
