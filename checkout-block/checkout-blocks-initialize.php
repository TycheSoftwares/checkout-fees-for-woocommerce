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

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

add_action(
	'woocommerce_blocks_loaded',
	function () {
		require_once 'class-blocks-integration.php';
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new CheckoutFeesBlocksIntegration() );
			}
		);

		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => 'checkout-fees-for-woocommerce',
					'callback'  => 'update_cart_fees',
				)
			);
		}
	}
);

/**
 * Update checkout fees.
 *
 * @param array $data data for checkout fees.
 * @version 2.10.3
 */
function update_cart_fees( $data ) {
	if ( isset( $data['shipping_method'] ) ) {
		WC()->session->set( 'chosen_shipping_method', $data['shipping_method'] );
	} if ( isset( $data['payment_method'] ) ) {
		WC()->session->set( 'chosen_payment_method', $data['payment_method'] );
	}

	WC()->cart->calculate_totals();
}
