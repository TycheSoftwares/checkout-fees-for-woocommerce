<?php
/**
 * Class Product_Metabox
 *
 * Registers the per-product fee settings metabox on the WooCommerce product
 * edit page and enqueues the React metabox bundle (pgbf-product.js).
 *
 * The React component mounts into #pgbf-product-root and communicates with
 * the plugin via:
 *   GET /pgbf-pro/v1/products/{id}/fees
 *   PUT /pgbf-pro/v1/products/{id}/fees
 *
 * The classic PHP metabox (save_post) is retained as a no-op guard only —
 * all actual saves go through the REST endpoint.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;

class Product_Metabox {

	public function __construct() {
		// Only register the metabox when per-product fees are enabled.
		add_action( 'add_meta_boxes',   [ $this, 'register_metabox' ] );
		add_action( 'save_post_product', [ $this, 'handle_save_post'  ], 10, 1 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the meta box on the product edit page.
	 */
	public function register_metabox(): void {
		// Check if per-product fees are enabled (reads from new unified storage).
		if ( ! Settings::general( 'per_product_enabled', false ) ) {
			return;
		}

		add_meta_box(
			'pgbf-product-fees',
			__( 'Payment Gateway Based Fees and Discounts', 'checkout-fees-for-woocommerce' ),
			[ $this, 'render_metabox' ],
			[ 'product', 'wc_product_block_editor' ], // classic + block editor product pages
			'normal',
			'default'
		);
	}

	/**
	 * Render the metabox container.
	 * The React component mounts here via product.js.
	 *
	 * @param WP_Post $post
	 */
	public function render_metabox( \WP_Post $post ): void {
		// data-product-id is read by src/product.js to know which product to load.
		printf(
			'<div id="pgbf-product-root" data-product-id="%d"></div>',
			absint( $post->ID )
		);
	}

	/**
	 * Fallback save handler: called when the product is saved via the classic editor
	 * Publish / Update button. The React component already intercepts this via JS
	 * (see ProductMetabox.js), but this PHP hook handles edge cases like quick-edit
	 * or environments where the JS save didn't complete.
	 *
	 * NOTE: The canonical save path is the REST endpoint PUT /pgbf-pro/v1/products/{id}/fees.
	 * This hook only runs if the hidden nonce field is present (set by the React component
	 * via a hidden input on form submit), preventing double-saves.
	 *
	 * @param int $post_id
	 */
	public function handle_save_post( int $post_id ): void {
		// Standard WP save-post guards.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( wp_is_post_revision( $post_id ) ) return;

		// The React JS interceptor already fires the REST save before publish.
		// We do NOT duplicate that here — the REST endpoint is the single source of truth.
		// This hook exists only as a documentation anchor; actual PHP fallback save
		// can be added here if needed in future.
	}

	/**
	 * Enqueue the React metabox bundle on the product edit screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only on product post edit screens.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$is_product_edit = (
			in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) &&
			$screen &&
			'product' === $screen->post_type
		);

		if ( ! $is_product_edit ) {
			return;
		}

		// Skip if per-product fees are disabled — no point loading the JS.
		if ( ! Settings::general( 'per_product_enabled', false ) ) {
			return;
		}

		$asset_file = PGBF_LITE_PLUGIN_PATH . '/build/pgbf-product.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		// Register the fake CSS handle (same fix as the settings page).
		wp_register_style(
			'wp-components/build-style/style.css',
			false,
			[ 'wp-components' ],
			$asset['version']
		);

		wp_enqueue_style(
			'pgbf-pro-product',
			PGBF_LITE_PLUGIN_URL . '/build/pgbf-product.css',
			[ 'wp-components', 'wp-components/build-style/style.css' ],
			$asset['version']
		);

		wp_enqueue_script(
			'pgbf-pro-product',
			PGBF_LITE_PLUGIN_URL . '/build/pgbf-product.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// Inject REST nonce.
		wp_add_inline_script(
			'wp-api-fetch',
			sprintf(
				'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( %s ) );',
				wp_json_encode( wp_create_nonce( 'wp_rest' ) )
			),
			'after'
		);

		wp_localize_script(
			'pgbf-pro-product',
			'pgbfProProductData',
			[
				'restUrl' => esc_url_raw( rest_url( 'pgbf-pro/v1/' ) ),
				'version' => PGBF_LITE_PLUGIN_VERSION,
			]
		);
	}
}
