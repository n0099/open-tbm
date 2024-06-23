import type { DefineNuxtConfig } from 'nuxt/config';
import { analyzer } from 'vite-bundle-analyzer';

export default {
    plugins: [
        analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
    ],
    build: { target: 'esnext' },
    assetsInclude: ['**/*.avifs']
} as Parameters<DefineNuxtConfig>[0]['vite'];
