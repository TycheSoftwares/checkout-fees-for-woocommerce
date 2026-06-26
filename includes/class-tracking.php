<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - Tracking Class
 *
 * @version 1.1.7
 * @since   1.1.3
 * @author  Tyche Softwares
 * @package Payment Gateway Based Fees and Discounts for WooCommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/** Declaration of Class */
class Tracking {

	/** Constructor */
	public function __construct() {
		require_once __DIR__ . '/tyche/components/plugin-tracking/class-tracking-functions.php';
		require_once __DIR__ . '/tyche/components/plugin-tracking/class-plugin-tracking.php';
		new Plugin_Tracking(
			array(
				'plugin_name'       => 'Payment Gateway Based Fees and Discounts for WooCommerce',
				'plugin_locale'     => 'checkout-fees-for-woocommerce',
				'plugin_short_name' => 'pgbf_lite',
				'version'           => PGBF_LITE_PLUGIN_VERSION,
				'blog_link'         => 'https://www.tychesoftwares.com/docs/docs/payment-gateway-based-fees-and-discounts-for-woocommerce/payment-gateway-based-fees-and-discounts-usage-tracking/',
			)
		);
		if ( is_admin() ) {
			require_once __DIR__ . '/tyche/components/plugin-tracking/class-cf-plugin-tracking.php';
		}
	}
}

// Initialize the tracking class.
new Tracking();
