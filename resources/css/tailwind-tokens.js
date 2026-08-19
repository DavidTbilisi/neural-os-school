/* ============================================================================
   Neural OS School — Tailwind token bridge
   Merge this into the app's tailwind.config.js. It maps SEMANTIC utility names
   (bg-bg, bg-surface, text-fg, text-muted, border-border, text-primary,
   text-success, …) onto the CSS variables in tokens.css, so every class is
   theme-aware (light/dark) with zero per-page dark: variants for color.

   Existing utilities (indigo-600, gray-500, p-4, rounded-lg, sm:) are LEFT
   INTACT — Tailwind's defaults are untouched, so nothing currently built breaks.
   New code should prefer the semantic names below.

   Usage — in tailwind.config.js:

     const tokens = require('./resources/css/tailwind-tokens.js')
     module.exports = {
       darkMode: 'class',                      // <-- required; Filament also uses .dark
       content: [ ...existing... ],
       theme: {
         extend: {
           ...tokens.extend,
           fontFamily: { ...tokens.extend.fontFamily },  // replaces the Figtree-only entry
         },
       },
       plugins: [ require('@tailwindcss/forms'), require('@tailwindcss/typography') ],
     }
   ========================================================================== */

/** rgb(var(--x) / <alpha-value>) — lets `bg-surface/50`, `text-primary/70` etc. work. */
const c = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;

// ESM export (the app uses "type": "module"); imported by tailwind.config.js.
export default {
  darkMode: 'class',
  extend: {
    colors: {
      // Surfaces
      bg: c('bg'),
      surface: {
        DEFAULT: c('surface'),
        raised: c('surface-raised'),
        sunken: c('surface-sunken'),
        inverse: c('surface-inverse'),
      },

      // Foreground — expose as text-fg / text-muted / text-subtle / text-link
      fg: {
        DEFAULT: c('fg'),
        muted: c('fg-muted'),
        subtle: c('fg-subtle'),
        inverse: c('fg-inverse'),
        link: c('fg-link'),
      },
      muted: c('fg-muted'),   // convenience alias: text-muted
      subtle: c('fg-subtle'), // convenience alias: text-subtle

      // Borders — border-border / border-border-subtle / border-border-strong
      border: {
        DEFAULT: c('border'),
        subtle: c('border-subtle'),
        strong: c('border-strong'),
      },
      ring: c('ring'),

      // Brand
      primary: {
        DEFAULT: c('primary'),
        hover: c('primary-hover'),
        active: c('primary-active'),
        fg: c('primary-fg'),
        subtle: c('primary-subtle'),
        'subtle-fg': c('primary-subtle-fg'),
      },

      // Status
      success: {
        DEFAULT: c('success'),
        subtle: c('success-subtle'),
        fg: c('success-fg'),
      },
      warning: {
        DEFAULT: c('warning'),
        subtle: c('warning-subtle'),
        fg: c('warning-fg'),
      },
      danger: {
        DEFAULT: c('danger'),
        subtle: c('danger-subtle'),
        fg: c('danger-fg'),
      },
      info: {
        DEFAULT: c('info'),
        subtle: c('info-subtle'),
        fg: c('info-fg'),
      },

      // Domain palette — bg-domain-1 … bg-domain-10 + text-domain-N-fg.
      // Assigned from data by App\Support\Palette, never hand-picked per view.
      domain: {
        1: { DEFAULT: c('domain-1'), fg: c('domain-1-fg') },
        2: { DEFAULT: c('domain-2'), fg: c('domain-2-fg') },
        3: { DEFAULT: c('domain-3'), fg: c('domain-3-fg') },
        4: { DEFAULT: c('domain-4'), fg: c('domain-4-fg') },
        5: { DEFAULT: c('domain-5'), fg: c('domain-5-fg') },
        6: { DEFAULT: c('domain-6'), fg: c('domain-6-fg') },
        7: { DEFAULT: c('domain-7'), fg: c('domain-7-fg') },
        8: { DEFAULT: c('domain-8'), fg: c('domain-8-fg') },
        9: { DEFAULT: c('domain-9'), fg: c('domain-9-fg') },
        10: { DEFAULT: c('domain-10'), fg: c('domain-10-fg') },
      },

      // The top bar — dark in BOTH themes (see tokens.css); bg-bar / text-bar-fg
      bar: {
        DEFAULT: c('bar'),
        fg: c('bar-fg'),
      },

      // Full-value (no alpha placeholder)
      overlay: 'var(--color-overlay)',
    },

    // One geometric-humanist family for display, headings, UI and reading.
    // `serif` is a RETIRED ALIAS pointing at the same face, so existing
    // `font-serif` markup follows the new voice instead of needing a rename.
    fontFamily: {
      display: ['"Plus Jakarta Sans"', 'Figtree', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
      serif: ['"Plus Jakarta Sans"', 'Figtree', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
      sans: ['"Plus Jakarta Sans"', 'Figtree', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
      mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
    },

    fontSize: {
      // Friendly app ramp; `lg` (17px) is the reading-column body. Headings
      // pull their tracking in as they grow — the display sizes are set bold.
      xs: ['0.75rem', { lineHeight: '1.4' }],
      sm: ['0.8125rem', { lineHeight: '1.45' }],
      base: ['0.9375rem', { lineHeight: '1.55' }],
      md: ['1rem', { lineHeight: '1.6' }],
      lg: ['1.0625rem', { lineHeight: '1.7' }],
      xl: ['1.375rem', { lineHeight: '1.35', letterSpacing: '-0.015em' }],
      '2xl': ['1.75rem', { lineHeight: '1.2', letterSpacing: '-0.02em' }],
      '3xl': ['2.25rem', { lineHeight: '1.15', letterSpacing: '-0.025em' }],
      '4xl': ['3rem', { lineHeight: '1.05', letterSpacing: '-0.03em' }],
    },

    borderRadius: {
      sm: 'var(--radius-sm)',   /* 10 — inputs, small chips */
      md: 'var(--radius-md)',   /* 14 — buttons, list rows */
      lg: 'var(--radius-lg)',   /* 20 — cards */
      xl: 'var(--radius-xl)',   /* 28 — panels */
      full: 'var(--radius-full)',
    },

    boxShadow: {
      sm: 'var(--shadow-sm)',
      md: 'var(--shadow-md)',
      lg: 'var(--shadow-lg)',
      focus: 'var(--shadow-focus)',
    },

    maxWidth: {
      prose: 'var(--max-w-prose)',
      wide: 'var(--max-w-wide)',
      page: 'var(--max-w-page)',
    },

    transitionDuration: {
      instant: '50ms',
      fast: '120ms',
      normal: '200ms',
      slow: '320ms',
      slower: '480ms',
    },

    transitionTimingFunction: {
      DEFAULT: 'cubic-bezier(0.4, 0, 0.2, 1)',
      in: 'cubic-bezier(0.4, 0, 1, 1)',
      out: 'cubic-bezier(0, 0, 0.2, 1)',
      emphatic: 'cubic-bezier(0.34, 1.56, 0.64, 1)',
    },
  },
};
