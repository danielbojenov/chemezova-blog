import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // The blog design pairs Source Serif 4 (headings) with Source Sans 3 (UI and
            // body). Only the weights that are used above the fold are preloaded; the
            // rest are fetched on demand.
            fonts: [
                bunny('Source Serif 4', {
                    weights: [400, 600, 700],
                    preload: [{ weight: 600 }, { weight: 700 }],
                }),
                bunny('Source Sans 3', {
                    weights: [400, 500, 600, 700],
                    // Italic carries the footer tagline.
                    styles: ['normal', 'italic'],
                    preload: [{ weight: 400 }, { weight: 500 }, { weight: 600 }],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
