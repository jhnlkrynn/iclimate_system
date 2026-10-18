import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/landing.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // IT dashboard type roles (also exposed there as CSS variables)
                display: ['Sora', ...defaultTheme.fontFamily.sans],
                body: ['DM Sans', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
                // Public landing page (Figma: iClimate landing page)
                inter: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            // Public landing page palette (Figma: iClimate landing page)
            colors: {
                brand: '#17432A',
                ink: '#111A13',
                muted: '#5B665D',
                surface: '#F6F8F5',
                tint: '#EEF3EC',
                line: '#E4E8E3',
                'advisory-bg': '#FBF5E3',
                'advisory-text': '#6B4E00',
            },
        },
    },

    plugins: [forms],
};
