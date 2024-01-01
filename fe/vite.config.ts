import vue from '@vitejs/plugin-vue';
import { visualizer } from 'rollup-plugin-visualizer';
import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [vue(), visualizer()],
    build: { target: 'esnext' },
    resolve: {
        alias: [
            { find: '@', replacement: fileURLToPath(new URL('src', import.meta.url)) }
        ]
    },
    assetsInclude: ['**/*.avifs']
});
