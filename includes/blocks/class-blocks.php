<?php
/**
 * Checkout Fees for WooCommerce - Blocks Integration Class
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define( 'PGBF_LITE_BLOCK_VERSION', '1.0.0' );

/**
 * Integration class for WooCommerce Blocks (Checkout Block).
 */
class Blocks_Integration implements IntegrationInterface {

	/**
	 * Unique name for the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'pgbf-checkout-fees';
	}

	/**
	 * Initialise the integration.
	 */
	public function initialize() {
		$this->register_block_frontend_scripts();
	}

	/**
	 * Script handles to enqueue on the frontend.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'checkout-block-frontend' );
	}

	/**
	 * Script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Data passed to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array();
	}

	/**
	 * Register the frontend script.
	 */
	private function register_block_frontend_scripts() {
		wp_register_script(
			'checkout-block-frontend',
			PGBF_LITE_PLUGIN_URL . '/build/checkout-fees-for-woocommerce.js',
			array(),
			PGBF_LITE_BLOCK_VERSION,
			true
		);
	}
}