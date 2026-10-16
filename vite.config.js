import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/guest.css', 'resources/css/leaflet.css', 'resources/css/ui-refresh.css', 'resources/css/glass-system.css', 'resources/css/sidebar.css', 'resources/css/theme.css', 'resources/css/app-shell.css', 'resources/css/app-magic.css', 'resources/css/gov-theme.css', 'resources/css/accessibility.css', 'resources/css/pages/weather-predictions.css', 'resources/css/pages/heat-map.css', 'resources/css/pages/community-feed.css', 'resources/css/pages/welcome.css', 'resources/js/app.js', 'resources/js/landing.js'],
            refresh: true,
        }),
    ],
});
