import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';

// https://vitejs.dev/config/
export default defineConfig({
    plugins: [vue({ script: { defineModel: true } })],
    build: { target: 'esnext' },
    resolve: {
        alias: [
            { find: '@', replacement: fileURLToPath(new URL('src', import.meta.url)) }
        ]
    },
    assetsInclude: ['**/*.avifs']
});
