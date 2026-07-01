<?php
/**
 * Checkout Fees for WooCommerce - Blocks Integration Entry Point
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

add_action(
	'woocommerce_blocks_loaded',
	function () {
		require_once __DIR__ . '/class-blocks.php';

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new Blocks_Integration() );
			}
		);

		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => 'checkout-fees-for-woocommerce',
					'callback'  => __NAMESPACE__ . '\\update_cart_fees',
				)
			);
		}
	}
);

/**
 * Update checkout fees based on payment/shipping method changes in the block.
 *
 * @param array $data Data from the block (shipping_method, payment_method).
 */
function update_cart_fees( $data ) {
	if ( isset( $data['shipping_method'] ) ) {
		WC()->session->set( 'chosen_shipping_method', $data['shipping_method'] );
	}
	if ( isset( $data['payment_method'] ) ) {
		WC()->session->set( 'chosen_payment_method', $data['payment_method'] );
	}
	WC()->cart->calculate_totals();
}