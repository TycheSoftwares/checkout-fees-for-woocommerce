
const path = require( 'path' );

// Load the default wp-scripts webpack config for everything EXCEPT entry.
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const WooCommerceDependencyExtractionPlugin = require(
    '@woocommerce/dependency-extraction-webpack-plugin'
);

delete defaultConfig.entry;

// ── Plugins: swap WP extraction plugin for the WC superset ───────────────────
const pluginsWithoutWPExtraction = defaultConfig.plugins.filter(
    ( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

// ── Final config ──────────────────────────────────────────────────────────────
module.exports = {

    // Inherit everything from wp-scripts (loaders, optimization, devtool, etc.)
    ...defaultConfig,

    // ── Entry: explicit object — never auto-scanned, never empty ─────────────
    entry: {
        // Block editor registration (existing file — keep in src/)
        'index': path.resolve( __dirname, 'src/index.js' ),

        // Checkout block frontend subscriber (existing file — keep in src/)
        'checkout-fees-for-woocommerce': path.resolve( __dirname, 'src/frontend.js' ),

        // Settings React SPA (new file in src/settings/)
        'settings': path.resolve( __dirname, 'src/settings/index.js' ),

        // Per-product fee metabox (new file in src/settings/)
        'pgbf-product': path.resolve( __dirname, 'src/settings/product.js' ),
    },

    // ── Output ────────────────────────────────────────────────────────────────
    output: {
        path    : path.resolve( __dirname, 'build' ),
        filename: '[name].js',
    },

    // ── Plugins ───────────────────────────────────────────────────────────────
    plugins: [
        ...pluginsWithoutWPExtraction,
        new WooCommerceDependencyExtractionPlugin(),
    ],

    // ── Resolve ───────────────────────────────────────────────────────────────
    resolve: {
        ...defaultConfig.resolve,
        alias: {
            ...( defaultConfig.resolve?.alias ?? {} ),
            // @pgbf → src/settings/ for clean imports within the settings SPA
            '@pgbf': path.resolve( __dirname, 'src/settings/' ),
        },
    },
};

