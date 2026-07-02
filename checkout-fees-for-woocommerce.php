<?php
/**
 * Plugin Name: Payment Gateway Based Fees and Discounts for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/payment-gateway-based-fees-and-discounts-for-woocommerce-plugin/
 * Description: Easily apply fees or discounts based on the customer's selected payment gateway in WooCommerce.
 * Version: 3.1.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: checkout-fees-for-woocommerce
 * Domain Path: /languages
 * Copyright: © 2021 Tyche Softwares
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: woocommerce
 * WC tested up to: 10.9.1
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 *
 * @package checkout-fees-for-woocommerce
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PGBF_LITE_BOOTSTRAP_FILE', __FILE__ );

$_pgbf_wc_plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $_pgbf_wc_plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) &&
	! ( is_multisite() && array_key_exists( $_pgbf_wc_plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}
unset( $_pgbf_wc_plugin );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-payment-gateway-fees.php';

if ( ! function_exists( 'pgbf_lite' ) ) {
	/**
	 * Returns the main plugin instance.
	 * Kept for full backward compatibility with all internal and external callers.
	 *
	 * @return PGBF_Payment_Gateway_Fees_Lite
	 */
	function pgbf_lite() { // phpcs:ignore
		return \TycheSoftwares\PaymentGatewayFees\Lite\Plugin::instance();
	}
}

pgbf_lite();
