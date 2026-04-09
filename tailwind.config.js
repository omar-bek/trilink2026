import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                page:        'var(--c-page)',
                surface:     'var(--c-surface)',
                'surface-2': 'var(--c-surface2)',
                elevated:    'var(--c-elevated)',
                'th-border': 'var(--c-border)',
                'border-s':  'var(--c-border-s)',
                primary:     'var(--c-text-primary)',
                body:        'var(--c-text-body)',
                muted:       'var(--c-text-muted)',
                faint:       'var(--c-text-faint)',
                icon:        'var(--c-icon)',
                accent:      'var(--c-accent)',
                'accent-h':  'var(--c-accent-h)',
                // Sprint C.13 — semantic categorical tokens used for
                // status badges, notification icons, and KPI cards.
                // Adding these here is the single source of truth so
                // a future palette tweak doesn't require touching 50+
                // Blade files. Each maps to a CSS variable defined in
                // resources/css/app.css.
                'accent-info':    'var(--c-accent-info)',
                'accent-success': 'var(--c-accent-success)',
                'accent-warning': 'var(--c-accent-warning)',
                'accent-danger':  'var(--c-accent-danger)',
                'accent-magenta': 'var(--c-accent-magenta)',
                'accent-violet':  'var(--c-accent-violet)',
            },
        },
    },
    plugins: [],
};
