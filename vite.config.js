import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // No explicit target previously meant Vite's more conservative
        // default, transpiling further than this site's real browser
        // support needs (Lighthouse's "Legacy JavaScript" finding). es2020
        // covers any browser from ~2020 onward - effectively all real-world
        // traffic to a public support site. See
        // docs/homepage-performance-fixes-plan.md, Stage 4.
        target: 'es2020',
    },
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
