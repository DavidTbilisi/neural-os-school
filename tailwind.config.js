import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import tokens from './resources/css/tailwind-tokens.js';

/** @type {import('tailwindcss').Config} */
export default {
    // Both the public site and Filament v3 flip theme via a `.dark` class on <html>.
    darkMode: 'class',

    // `not-prose` is applied at runtime by the wiki renderer (chart/mermaid
    // figures), so it never appears in a scanned template — keep it generated.
    safelist: ['not-prose'],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Filament/**/*.php',
    ],

    theme: {
        // Semantic token utilities (bg-surface, text-primary, font-serif, …) mapped
        // onto tokens.css variables. Tailwind's own defaults are left intact.
        extend: {
            ...tokens.extend,
        },
    },

    plugins: [forms, typography],
};
