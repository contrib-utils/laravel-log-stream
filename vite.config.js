import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

// The SPA is served from /vendor/logscope after `vendor:publish`, so assets
// must resolve against that base. Output goes to resources/dist, which the
// service provider publishes to public/vendor/logscope.
export default defineConfig({
    base: '/vendor/logscope/',
    plugins: [vue(), tailwindcss()],
    build: {
        outDir: 'resources/dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: 'resources/js/main.js',
        },
    },
});
