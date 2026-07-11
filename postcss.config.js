export default {
    plugins: {
        // Must run first so `@import './tokens.css'` in app.css is inlined
        // BEFORE Tailwind processes it — otherwise tokens.css's `@layer base`
        // won't cascade under Tailwind's layers.
        'postcss-import': {},
        tailwindcss: {},
        autoprefixer: {},
    },
};
