import purgecss from '@fullhuman/postcss-purgecss';

const purgePlugin = purgecss({
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
        './config/**/*.php',
    ],
    defaultExtractor: (content) => content.match(/[\w-/:.%[\]]+(?<!:)/g) || [],
    safelist: {
        standard: [/^(show|fade|collapse|collapsing|active|disabled|open|hidden|block|inline|flex|grid)$/],
        deep: [
            /^modal/,
            /^offcanvas/,
            /^dropdown/,
            /^alert/,
            /^badge/,
            /^btn/,
            /^form-/,
            /^input-/,
            /^table/,
            /^nav/,
            /^tab-/,
            /^page-/,
            /^pagination/,
            /^toast/,
            /^tooltip/,
            /^popover/,
            /^bs-/,
            /^is-/,
            /^has-/,
            /^tone-/,
            /^fh-/,
            /^mfd-/,
            /^itd-/,
            /^itp-/,
            /^ic-/,
            /^ic-w-/,
            /^rf-/,
            /^wpx-/,
            /^fad-/,
            /^leaflet-/,
            /^ai-/,
            /^text-bg-/,
            /data-theme/,
            /aria-/,
        ],
    },
});

export default {
    plugins: [
        (await import('tailwindcss')).default,
        (await import('autoprefixer')).default,
        {
            postcssPlugin: 'iclimate-purge-app-css',
            OnceExit(root, helpers) {
                if (process.env.NODE_ENV !== 'production') {
                    return;
                }
                const from = helpers.result.opts.from || '';
                if (!from.endsWith('/resources/css/app.css') && !from.endsWith('\\resources\\css\\app.css')) {
                    return;
                }
                return purgePlugin.OnceExit(root, helpers);
            },
        },
    ],
};
