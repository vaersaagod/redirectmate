import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

// https://vitejs.dev/config/
export default ({ mode }) => {
    // Load additional env vars
    process.env = {...process.env, ...loadEnv(mode, process.cwd() + '/..' )};
    return defineConfig({
        build: {
            emptyOutDir: true,
            manifest: true,
            outDir: '../src/web/assets/dist',
            rollupOptions: {
                input: {
                    'redirectmate': 'src/redirectmate.js',
                },
                output: {
                    sourcemap: true,
                    entryFileNames: '[name].js',
                    assetFileNames: '[name][extname]'
                },
            }
        },
        plugins: [vue()],
        server: {
            host: process.env.VITE_DEVSERVER_HOST || '0.0.0.0',
            port: process.env.VITE_DEVSERVER_PORT || 3000
        },
        appType: 'custom'
    });
}
