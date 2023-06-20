import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

// https://vitejs.dev/config/
export default defineConfig(({ command, mode }) => {
    // https://vitejs.dev/config/#using-environment-variables-in-config
    const env = loadEnv(mode, process.cwd(), '');
    return {
        plugins: [vue()],
        base: env.VITE_PUBLIC_PATH,
        resolve: {
            alias: {
                // eslint-disable-next-line
                '@': path.resolve(__dirname, './src')
            }
        }
    };
});
