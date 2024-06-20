import { visualizer } from 'rollup-plugin-visualizer';
import { analyzer } from 'vite-bundle-analyzer';
import _ from 'lodash';

const envs = [
    'apiBaseURL',
    'gaMeasurementID',
    'recaptchaSiteKey',
    'instanceName',
    'footerText'
];
// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    devServer: { https: true },
    devtools: { enabled: true },
    modules: ['@pinia/nuxt'],
    components: [{
        path: '@/components',
        pathPrefix: false
    }],
    pinia: { storesDirs: ['src/stores/**'] },
    imports: { dirs: ['@/api/**', 'utils/**'] },
    srcDir: 'src/',
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
