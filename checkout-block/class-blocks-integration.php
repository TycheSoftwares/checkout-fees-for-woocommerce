<?php
/**
 * Checkout Fees for WooCommerce
 *
 * @version 2.5.4
 * @since   1.0.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce-pro/checkout
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

define( 'PGBF_BLOCK_VERSION', '1.0.0' );

/**
 * Blocks Integration.
 */
class Blocks_Integration implements IntegrationInterface {
	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return '';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$this->register_block_frontend_scripts();
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'checkout-block-frontend' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( '' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array();
	}

	/**
	 * Register scripts for frontend block.
	 *
	 * @return void
	 */
	public function register_block_frontend_scripts() {

		wp_register_script(
			'checkout-block-frontend',
			trailingslashit( plugin_dir_url( __DIR__ ) ) . 'src/frontend.js',
			array(),
			PGBF_BLOCK_VERSION,
			true
		);
	}
}
