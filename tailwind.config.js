import { readFileSync } from 'node:fs';
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// The sage brand palette: one source (resources/palette.json) for this file, resources/css/palette.css and config/palette.php.
const palette = JSON.parse(readFileSync(new URL('./resources/palette.json', import.meta.url), 'utf8'));
const { sage } = palette;

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
            // Brand palette (sage). brand is sage-700 (buttons, links, dark bands), brand-dark its hover; surface, tint and line
            // are the light backgrounds and lines. Ink and muted text stay neutral.
            colors: {
                sage,
                brand: sage['700'],
                'brand-dark': sage['800'],
                ink: palette.ink,
                muted: palette.muted,
                surface: sage['50'],
                tint: sage['100'],
                line: sage['200'],
                'advisory-bg': '#FBF5E3',
                'advisory-text': '#6B4E00',
            },
        },
    },

    plugins: [forms],
};
