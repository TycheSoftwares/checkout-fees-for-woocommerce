<?php
/**
 *  Payment Gateway Based Fees for WooCommerce - Data Tracking Functions
 *
 * @since   1.5.0
 * @package  Payment Gateway Based Fees/Data Tracking
 * @author  Tyche Softwares
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 *  Currency per Product Data Tracking Functions.
 */
class CF_Plugin_Tracking {
	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'admin_footer', array( $this, 'ts_admin_notices_scripts' ) );
		add_action( 'admin_init', array( $this, 'ts_reset_tracking_setting' ) );
		add_action( 'pgbf_pro_init_tracker_completed', array( __CLASS__, 'init_tracker_completed' ), 10, 2 );
		add_filter( 'pgbf_pro_ts_tracker_data', array( __NAMESPACE__ . '\\Tracking_Functions', 'cf_pro_plugin_tracking_data' ), 10, 1 );
		add_filter( 'woocommerce_reset_settings_alg_checkout_fees', array( $this, 'ts_tracking_reset_option' ), 10, 2 );
	}

	/**
	 * Add reset tracking option on general settings.
	 *
	 * @param array  $settings Settings.
	 * @param string $current_section Current section.
	 *
	 * @return array
	 */
	public function ts_tracking_reset_option( $settings, $current_section ) {

		if ( 'general' === $current_section || '' === $current_section ) {
			$reset_usage_tracking = array(
				'name'        => __( 'Reset usage tracking', 'checkout-fees-for-woocommerce' ),
				'type'        => 'link',
				'desc'        => __( 'This will reset your usage tracking settings, causing it to show the opt-in banner again and not sending any data', 'checkout-fees-for-woocommerce' ),
				'button_text' => 'Reset',
				'desc_tip'    => true,
				'class'       => 'button-secondary reset_tracking',
				'id'          => 'ts_reset_tracking',
			);
			array_splice( $settings, 2, 0, array( $reset_usage_tracking ) );
		}

		return $settings;
	}

	/**
	 * This function includes js files required for admin side.
	 *
	 * @hook ts_admin_notices_scripts
	 *
	 * @since 2.9.0
	 */
	public static function ts_admin_notices_scripts() {
		$nonce                     = wp_create_nonce( 'tracking_notice' );
		$pgbf_get_previous_version = get_option( 'alg_woocommerce_checkout_fees_version' );
		wp_enqueue_script(
			'pgbf_ts_dismiss_notice',
			plugin_dir_url( PGBF_LITE_PLUGIN_FILE ) . '/includes/tyche/assets/js/tyche-dismiss-tracking-notice.js',
			'',
			$pgbf_get_previous_version,
			false
		);

		wp_localize_script(
			'pgbf_ts_dismiss_notice',
			'pgbf_ts_dismiss_notice',
			array(
				'ts_prefix_of_plugin' => 'pgbf_lite',
				'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
				'tracking_notice'     => $nonce,
			)
		);
	}

	/**
	 * This function reset the tracking settings.
	 *
	 * @hook ts_reset_tracking_setting
	 *
	 * @since 2.9.0
	 */
	public static function ts_reset_tracking_setting() {
		if ( isset( $_GET ['ts_action'] ) && 'reset_tracking' == $_GET ['ts_action'] ) { // phpcs:ignore
			Plugin_Tracking::reset_tracker_setting( 'pgbf_lite' );
			$ts_url = remove_query_arg( 'ts_action' );
			wp_safe_redirect( $ts_url );
		}
	}

	/**
	 * Function init_tracker_completed.
	 *
	 * @since 2.9.0
	 */
	public static function init_tracker_completed() {
		header( 'Location: ' . admin_url( 'admin.php?page=wc-settings&tab=payment-gateway-fees-for-woocommerce' ) );
		exit;
	}
}

$cf_plugin_tracking = new CF_Plugin_Tracking();
