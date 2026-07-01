<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - Admin
 *
 * @version 3.0.0
 * @since   2.5.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;


/**
 * Admin class — handles legacy delete-all-plugin-data action.
 */
class Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_delete_all_plugin_data' ), PHP_INT_MAX );
	}

	/**
	 * Show success notice after data deletion.
	 */
	public function admin_notice_delete_all_plugin_data_success() {
		echo wp_kses_post( '<div class="notice notice-info"><p>' . __( 'Plugin data successfully deleted.', 'checkout-fees-for-woocommerce' ) . '</p></div>' );
	}

	/**
	 * Show error notice when nonce/capability check fails.
	 */
	public function admin_notice_delete_all_plugin_data_error() {
		echo wp_kses_post( '<div class="notice notice-error"><p>' . __( 'Wrong user role or nonce not verified.', 'checkout-fees-for-woocommerce' ) . '</p></div>' );
	}

	/**
	 * Handle the delete-all-plugin-data GET action.
	 */
	public function maybe_delete_all_plugin_data() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['alg_woocommerce_checkout_fees_delete_all_data'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['alg_woocommerce_checkout_fees_delete_all_data_success'] ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice_delete_all_plugin_data_success' ) );
			}
			return;
		}

		// Nonce + capability check.
		if (
			! isset( $_GET['alg_woocommerce_checkout_fees_delete_all_data_nonce'] ) ||
			! wp_verify_nonce(
				sanitize_key( $_GET['alg_woocommerce_checkout_fees_delete_all_data_nonce'] ),
				'alg_woocommerce_checkout_fees_delete_all_data'
			) ||
			! current_user_can( 'manage_woocommerce' )
		) {
			add_action( 'admin_notices', array( $this, 'admin_notice_delete_all_plugin_data_error' ) );
			return;
		}

		global $wpdb;

		// Delete legacy per-product postmeta rows.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$plugin_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
				'\_alg\_checkout\_fees\_%'
			)
		);

		$delete_counter_meta = 0;
		foreach ( $plugin_meta as $meta ) {
			delete_post_meta( $meta->post_id, $meta->meta_key );
			$delete_counter_meta++;
		}

		// Delete legacy plugin options.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$plugin_options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
					WHERE option_name LIKE %s
					OR option_name LIKE %s",
				'alg\_woocommerce\_checkout\_fees\_%',
				'alg\_gateways\_fees\_%'
			)
		);

		$delete_counter_options = 0;
		foreach ( $plugin_options as $option ) {
			if ( 'alg_woocommerce_checkout_fees_version' !== $option->option_name ) {
				delete_option( $option->option_name );
				delete_site_option( $option->option_name );
				$delete_counter_options++;
			}
		}

		// Delete new unified option keys.
		delete_option( 'pgbf_pro_settings' );
		delete_option( 'pgbf_pro_gateway_settings' );

		do_action( 'pgbf_pro_all_data_deleted' );

		wp_safe_redirect(
			add_query_arg(
				'alg_woocommerce_checkout_fees_delete_all_data_success',
				$delete_counter_meta . ',' . $delete_counter_options,
				remove_query_arg( 'alg_woocommerce_checkout_fees_delete_all_data' )
			)
		);
		exit;
	}
}

// Backward-compat alias.
if ( ! class_exists( 'Alg_WC_Checkout_Fees_Admin' ) ) {
	class_alias( __NAMESPACE__ . '\\Admin', 'Alg_WC_Checkout_Fees_Admin' );
}

return new Admin();