import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const __dirname = dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    build: {
        lib: {
            entry: resolve(__dirname, 'index.js'),
            formats: ['es']
        }
    },
    define: { // https://stackoverflow.com/questions/74120349/building-bundle-for-web-in-vite/74121995#74121995
        'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV)
    }
});
