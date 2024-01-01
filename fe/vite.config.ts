import vue from '@vitejs/plugin-vue';
import { visualizer } from 'rollup-plugin-visualizer';
import { defineConfig } from 'vite';
import { analyzer } from 'vite-bundle-analyzer';
import { fileURLToPath } from 'node:url';

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [
        vue(),
        visualizer({ filename: 'dist/rollup-plugin-visualizer.html', gzipSize: true, brotliSize: true }),
        analyzer({ analyzerMode: 'static', fileName: 'vite-bundle-analyzer' })
    ],
    build: { target: 'esnext' },
    resolve: {
        alias: [
            { find: '@', replacement: fileURLToPath(new URL('src', import.meta.url)) }
        ]
    },
    assetsInclude: ['**/*.avifs']
});
