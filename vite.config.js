import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/sketchpad.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
    define: {
        // Excalidraw reads this at runtime; without it the bundle throws
        // `process is not defined` in the browser.
        'process.env.IS_PREACT': JSON.stringify('false'),
    },
});
