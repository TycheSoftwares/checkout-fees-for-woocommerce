<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - Deactivation Class
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
class Deactivation {

	/** Constructor */
	public function __construct() {
		require_once __DIR__ . '/tyche/components/plugin-deactivation/class-plugin-deactivation.php';
		new Plugin_Deactivation(
			array(
				'plugin_name'       => 'Payment Gateway Based Fees and Discounts for WooCommerce',
				'plugin_base'       => 'checkout-fees-for-woocommerce/checkout-fees-for-woocommerce.php',
				'script_file'       => PGBF_LITE_PLUGIN_URL . '/includes/tyche/assets/js/plugin-deactivation.js',
				'plugin_short_name' => 'pgbf_lite',
				'version'           => PGBF_LITE_PLUGIN_VERSION,
				'plugin_locale'     => 'checkout-fees-for-woocommerce',
			)
		);
	}
}

// Initialize the deactivation class.
new Deactivation();
