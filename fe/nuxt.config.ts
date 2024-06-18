import { visualizer } from 'rollup-plugin-visualizer';
import { analyzer } from 'vite-bundle-analyzer';

// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
    devServer: { https: true },
    devtools: { enabled: true },
    modules: ['@pinia/nuxt'],
    components: [{
        path: '@/components',
        pathPrefix: false
    }],
    imports: { dirs: ['@/api/**'] },
    srcDir: 'src/',
    css: [
        'bootstrap/dist/css/bootstrap.min.css',
        'noty/lib/noty.css',
        'noty/lib/themes/mint.css',
        'nprogress/nprogress.css',
        'assets/css/global.css'
    ],
    vite: {
        plugins: [
            visualizer({ filename: 'dist/rollup-plugin-visualizer.html', gzipSize: true, brotliSize: true }),
            analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
        ],
        build: { target: 'esnext' },
        assetsInclude: ['**/*.avifs']
    }
});
