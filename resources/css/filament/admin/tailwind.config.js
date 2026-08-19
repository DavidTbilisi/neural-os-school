import preset from '../../../../vendor/filament/support/tailwind.config.preset.js';

/**
 * Theme-local Tailwind config for the Filament admin panel (referenced by
 * `@config 'tailwind.config.js'` in theme.css). It extends Filament's preset —
 * NOT the app's root config — so the panel keeps Filament's own type/spacing
 * scale intact. The grape palette comes from AdminPanelProvider->colors()
 * plus the CSS-variable remaps in theme.css.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    presets: [preset],
    darkMode: 'class',
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
        './storage/framework/views/*.php',
    ],
};
