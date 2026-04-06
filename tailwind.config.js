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
            },
        },
    },
    plugins: [],
};
