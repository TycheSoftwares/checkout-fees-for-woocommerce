<?php
/**
 * Checkout Fees for WooCommerce - Admin
 *
 * @version 2.5.0
 * @since   2.5.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Checkout_Fees_Admin' ) ) :
	/**
	 * Alg_WC_Checkout_Fees_Admin Class
	 *
	 * @class   Alg_WC_Checkout_Fees_Admin
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	class Alg_WC_Checkout_Fees_Admin {

		/**
		 * Constructor.
		 *
		 * @version 2.5.0
		 * @since   2.5.0
		 */
		public function __construct() {
			add_action( 'admin_init', array( $this, 'maybe_delete_all_plugin_data' ), PHP_INT_MAX );
			add_action( 'admin_enqueue_scripts', array( $this, 'alg_checkout_fees_inline_css' ) );
		}



		/**
		 * Inline CSS on admin end.
		 * Solve the conflict issue of bykea_cash.
		 *
		 * @version 2.5.0
		 */
		public function alg_checkout_fees_inline_css() {
			if ( isset( $_GET['tab'] ) && 'alg_checkout_fees' === $_GET['tab'] && isset( $_GET['section'] ) && 'bykea_cash' === $_GET['section'] ) { // phpcs:ignore
				wp_register_style(
					'alg-wc-checkout-bykea-cash',
					false,
					array(),
					alg_wc_cf()->version
				);
				wp_enqueue_style( 'alg-wc-checkout-bykea-cash' );
				wp_add_inline_style( 'alg-wc-checkout-bykea-cash', '.hide-save-btn{ display:block !important;}' );
			}
			$plugin_url = plugins_url() . '/checkout-fees-for-woocommerce';
			wp_register_script(
				'tyche',
				$plugin_url . '/includes/js/tyche.js',
				array( 'jquery' ),
				alg_wc_cf()->version,
				true
			);
			wp_enqueue_script( 'tyche' );
		}


		/**
		 * Admin_notice_delete_all_plugin_data_success.
		 *
		 * @version 2.5.0
		 * @since   2.5.0
		 */
		public function admin_notice_delete_all_plugin_data_success() {
			echo '<div class="notice notice-info"><p>' . __( 'Plugin data successfully deleted.', 'checkout-fees-for-woocommerce' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Admin_notice_delete_all_plugin_data_error.
		 *
		 * @version 2.5.0
		 * @since   2.5.0
		 */
		public function admin_notice_delete_all_plugin_data_error() {
			echo '<div class="notice notice-error"><p>' . __( 'Wrong user role or nonce not verified.', 'checkout-fees-for-woocommerce' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		/**
		 * Maybe_delete_all_plugin_data.
		 *
		 * @version 2.5.0
		 * @since   2.2.2
		 */
		public function maybe_delete_all_plugin_data() {
			if ( isset( $_GET['alg_woocommerce_checkout_fees_delete_all_data'] ) ) {
				// Checking nonce & user role.
				if ( ! isset( $_GET['alg_woocommerce_checkout_fees_delete_all_data_nonce'] ) || ! wp_verify_nonce( $_GET['alg_woocommerce_checkout_fees_delete_all_data_nonce'], 'alg_woocommerce_checkout_fees_delete_all_data' ) || ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore
					add_action( 'admin_notices', array( $this, 'admin_notice_delete_all_plugin_data_error' ) );
					return;
				}
				global $wpdb;
				$delete_counter_meta = 0;
				$plugin_meta         = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", '_alg_checkout_fees_%' ) ); // phpcs:ignore
				foreach ( $plugin_meta as $meta ) {
					delete_post_meta( $meta->post_id, $meta->meta_key );
					++$delete_counter_meta;
				}
				$delete_counter_options = 0;
				$plugin_options         = $wpdb->get_results( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", 'alg_woocommerce_checkout_fees_%', 'alg_gateways_fees_%' ) ); // phpcs:ignore
				foreach ( $plugin_options as $option ) {
					if ( 'alg_woocommerce_checkout_fees_version' !== $option->option_name ) {
						delete_option( $option->option_name );
						delete_site_option( $option->option_name );
						++$delete_counter_options;
					}
				}
				// The end.
				wp_safe_redirect(
					add_query_arg(
						'alg_woocommerce_checkout_fees_delete_all_data_success',
						$delete_counter_meta . ',' . $delete_counter_options,
						remove_query_arg( 'alg_woocommerce_checkout_fees_delete_all_data' )
					)
				);
				exit;
			} elseif ( isset( $_GET['alg_woocommerce_checkout_fees_delete_all_data_success'] ) ) {
				add_action( 'admin_notices', array( $this, 'admin_notice_delete_all_plugin_data_success' ) );
			}
		}
	}

endif;

return new Alg_WC_Checkout_Fees_Admin();
