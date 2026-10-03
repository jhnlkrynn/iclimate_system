import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Route axios through the global iClimate loader too (public/js/iclimate-loader.js).
// Pass `loader: false` or `loader: 'Saving...'` in a request config to override.
window.axios.interceptors.request.use((config) => {
    config.iclLoaderToken = window.iClimateLoader?.beginRequest(config.url, config.method, config.loader);
    return config;
});
window.axios.interceptors.response.use(
    (response) => {
        window.iClimateLoader?.hide(response.config?.iclLoaderToken);
        return response;
    },
    (error) => {
        window.iClimateLoader?.hide(error.config?.iclLoaderToken);
        return Promise.reject(error);
    },
);
