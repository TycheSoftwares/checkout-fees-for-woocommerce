<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - Data Tracking Functions
 *
 * @version 2.6.3
 * @since   2.6.3
 * @package Payment Gateway Based Fees and Discounts/Data Tracking
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Cf_Tracking_Functions' ) ) :

	/**
	 * Payment Gateway Based Fees and Discounts for WooCommerce Tracking Functions.
	 */
	class Cf_Tracking_Functions {

		/**
		 * Construct.
		 *
		 * @since 2.6.3
		 */
		public function __construct() {
		}

		/**
		 * Returns plugin data for tracking.
		 *
		 * @param array $data - Generic data related to WP, WC, Theme, Server and so on.
		 * @return array $data - Plugin data included in the original data received.
		 * @since 2.6.3
		 */
		public static function cf_plugin_tracking_data( $data ) {

			$plugin_data = array(
				'ts_meta_data_table_name'  => 'ts_tracking_pgbf_lite_meta_data',
				'ts_plugin_name'           => 'Payment Gateway Based Fees and Discounts for WooCommerce',
				'global_settings'          => self::cf_get_global_settings(),
				'enabled_payment_gateways' => self::cf_get_enabled_payment_gateways(),
			);

			$active_gateways = self::wc_payment_gateways();
			foreach ( $active_gateways as $key => $value ) {
				$args                             = alg_wc_cf()->core->args_manager->get_the_args_global( $key );
				$plugin_data[ $key . 'settings' ] = $args;
			}

			$active_gateways = WC()->payment_gateways->payment_gateways();
			foreach ( $active_gateways as $gateway => $gateway_data ) {
				$count                                      = self::cf_get_gateway_based_product_counts( $gateway );
				$plugin_data[ $gateway . '_product_count' ] = $count;
			}

			$data['plugin_data'] = $plugin_data;
			return $data;
		}

		/**
		 * Send the global settings for tracking.
		 * Settings from the Genetral, Behavior & Advanced tabs are captured here.
		 *
		 * @since 2.6.3
		 */
		public static function cf_get_global_settings() {

			$global_settings = array(
				'alg_woocommerce_checkout_fees_enabled'   => get_option( 'alg_woocommerce_checkout_fees_enabled' ),
				'alg_woocommerce_checkout_fees_per_product_enabled' => get_option( 'alg_woocommerce_checkout_fees_per_product_enabled' ),
				'alg_woocommerce_checkout_fees_per_product_add_product_name' => get_option( 'alg_woocommerce_checkout_fees_per_product_add_product_name' ),
				'alg_woocommerce_checkout_fees_merge_all_fees' => get_option( 'alg_woocommerce_checkout_fees_merge_all_fees' ),
				'alg_woocommerce_checkout_fees_range_max_total_discounts' => get_option( 'alg_woocommerce_checkout_fees_range_max_total_discounts' ),
				'alg_woocommerce_checkout_fees_range_max_total_fees' => get_option( 'alg_woocommerce_checkout_fees_range_max_total_fees' ),
				'alg_woocommerce_checkout_fees_hide_on_cart' => get_option( 'alg_woocommerce_checkout_fees_hide_on_cart' ),
				'alg_woocommerce_checkout_fees_info_enabled' => get_option( 'alg_woocommerce_checkout_fees_info_enabled' ),
				'alg_woocommerce_checkout_fees_info_start_template' => get_option( 'alg_woocommerce_checkout_fees_info_start_template' ),
				'alg_woocommerce_checkout_fees_info_row_template' => get_option( 'alg_woocommerce_checkout_fees_info_row_template' ),
				'alg_woocommerce_checkout_fees_info_end_template' => get_option( 'alg_woocommerce_checkout_fees_info_end_template' ),
				'alg_woocommerce_checkout_fees_info_hook' => get_option( 'alg_woocommerce_checkout_fees_info_hook' ),
				'alg_woocommerce_checkout_fees_info_hook_priority' => get_option( 'alg_woocommerce_checkout_fees_info_hook_priority' ),
				'alg_woocommerce_checkout_fees_lowest_price_info_enabled' => get_option( 'alg_woocommerce_checkout_fees_lowest_price_info_enabled' ),
				'alg_woocommerce_checkout_fees_lowest_price_info_template' => get_option( 'alg_woocommerce_checkout_fees_lowest_price_info_template' ),
				'alg_woocommerce_checkout_fees_lowest_price_info_hook' => get_option( 'alg_woocommerce_checkout_fees_lowest_price_info_hook' ),
				'alg_woocommerce_checkout_fees_lowest_price_info_hook_priority' => get_option( 'alg_woocommerce_checkout_fees_lowest_price_info_hook_priority' ),
				'alg_woocommerce_checkout_fees_variable_info' => get_option( 'alg_woocommerce_checkout_fees_variable_info' ),
				'alg_woocommerce_checkout_fees_global_fee_enabled' => get_option( 'alg_woocommerce_checkout_fees_global_fee_enabled' ),
				'alg_woocommerce_checkout_fees_global_fee_as_extra_enabled' => get_option( 'alg_woocommerce_checkout_fees_global_fee_as_extra_enabled' ),
				'alg_woocommerce_checkout_fees_global_fee_gateways_excl' => get_option( 'alg_woocommerce_checkout_fees_global_fee_gateways_excl' ),
				'alg_woocommerce_checkout_fees_global_fee_title' => get_option( 'alg_woocommerce_checkout_fees_global_fee_title' ),
				'alg_woocommerce_checkout_fees_global_fee_value' => get_option( 'alg_woocommerce_checkout_fees_global_fee_value' ),
			);

			return $global_settings;
		}

		/**
		 * Enabled Payment Gateways Information
		 *
		 * @since 2.6.3
		 */
		public static function cf_get_enabled_payment_gateways() {

			$active_gateways = self::wc_payment_gateways();

			return $active_gateways;
		}

		/**
		 * Fetch Enabled Payment Gateways Information
		 *
		 * @since 2.6.3
		 */
		public static function wc_payment_gateways() {

			$active_gateways = array();
			$gateways        = WC()->payment_gateways->payment_gateways();
			foreach ( $gateways as $id => $gateway ) {
				if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
					$active_gateways[ $id ] = array(
						'title'    => $gateway->title,
						'supports' => $gateway->supports,
					);
				}
			}

			return $active_gateways;
		}

		/**
		 * Returns the number of products for the given gateway.
		 *
		 * @param string $gateway - Gateway Code.
		 * @return int $count - Count of products.
		 * @since 2.6.3
		 */
		public static function cf_get_gateway_based_product_counts( $gateway = '' ) {

			if ( '' !== $gateway ) {

				$gateway = '_alg_checkout_fees_enabled_' . $gateway;

				global $wpdb;
				$count = $wpdb->get_var( $wpdb->prepare( "SELECT count(post_id) FROM `" . $wpdb->prefix . "postmeta` WHERE meta_key = %s AND meta_value = %s", $gateway, 'yes' ) ); // phpcs:ignore

				return $count;
			}

			return 0;
		}
	}

endif;

$cf_tracking_functions = new Cf_Tracking_Functions();
