const mix = require('laravel-mix');
require('laravel-mix-clean');

const TerserPlugin = require('terser-webpack-plugin');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

// Disable notifications.
mix.disableNotifications();

// Version & source maps.
mix.version();
mix.sourceMaps(false);

// Assets.
mix.js('resources/js/app.js', 'public/js')
    .vue();

mix.postCss('resources/css/app.pcss', 'public/css', [
    require('postcss-import'),
    require('tailwindcss'),
]);

mix.extract();
mix.clean();

// Config.
mix.webpackConfig({
    output: {
        chunkFilename: 'js/[chunkhash].js',
    },
    devtool: false,
    optimization: {
        minimize: mix.inProduction(),
        minimizer: [
            new TerserPlugin({
                extractComments: false,
            }),
        ],
    },
});
