import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        vue(),
    ],
    server: {
        // Hace que public/hot use localhost en lugar de 0.0.0.0 (el navegador no acepta 0.0.0.0)
        hmr: {
            host: 'localhost',
        },
        watch: {
            usePolling: true,
            interval: 300,
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});


