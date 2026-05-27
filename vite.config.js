import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                }
            },
        }),
    ],

    // optimizeDeps: {
    //     include: ['jquery', 'select2']
    // },

    resolve: {
        alias: {
            '@': '/resources/js',
            '~bootstrap': '/resources/assets/vendor/js/bootstrap.js',
        },
    },
    optimizeDeps: {
        include: ['jquery', 'select2', 'bootstrap'],
    },


    // server: {
    //     host: '0.0.0.0',
    //     port: 5173,
    //     hmr: {
    //         host: 'localhost',
    //     },
    // },
});
