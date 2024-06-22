import type { DefineNuxtConfig } from 'nuxt/config';
import { visualizer } from 'rollup-plugin-visualizer';
import { analyzer } from 'vite-bundle-analyzer';

export default {
    plugins: [
        visualizer({ filename: 'dist/rollup-plugin-visualizer.html', gzipSize: true, brotliSize: true }),
        analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
    ],
    build: { target: 'esnext' },
    assetsInclude: ['**/*.avifs']
} as Parameters<DefineNuxtConfig>[0]['vite'];
