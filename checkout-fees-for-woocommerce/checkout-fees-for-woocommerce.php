<?php
/*
Plugin Name: Payment Gateway Based Fees and Discounts for WooCommerce
Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/payment-gateway-based-fees-and-discounts-for-woocommerce-plugin/
Description: Set payment gateways fees and discounts in WooCommerce.
Version: 2.5.9
Author: Tyche Softwares
Author URI: https://www.tychesoftwares.com/
Text Domain: checkout-fees-for-woocommerce
Domain Path: /langs
Copyright: � 2018 Tyche Softwares
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
WC tested up to: 3.6.1
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'checkout-fees-for-woocommerce.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return
	$plugin = 'checkout-fees-for-woocommerce-pro/checkout-fees-for-woocommerce-pro.php';
	if (
		in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ||
		( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! class_exists( 'Alg_Woocommerce_Checkout_Fees' ) ) :

/**
 * Main Alg_Woocommerce_Checkout_Fees Class
 *
 * @version 2.5.2
 * @class   Alg_Woocommerce_Checkout_Fees
 */
final class Alg_Woocommerce_Checkout_Fees {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 2.1.0
	 */
	public $version = '2.5.9';

	/**
	 * @var Alg_Woocommerce_Checkout_Fees The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Main Alg_Woocommerce_Checkout_Fees Instance
	 *
	 * Ensures only one instance of Alg_Woocommerce_Checkout_Fees is loaded or can be loaded.
	 *
	 * @static
	 * @return Alg_Woocommerce_Checkout_Fees - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Alg_Woocommerce_Checkout_Fees Constructor.
	 *
	 * @version 2.5.2
	 * @access  public
	 * @todo    [dev] maybe replace all standalone options with option arrays, e.g.: replace `alg_gateways_fees_text_ . $key` with `alg_gateways_fees_text[$key]`
	 */
	function __construct() {

		// Set up localisation
		load_plugin_textdomain( 'checkout-fees-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

		// Include required files
		$this->includes();

		// Admin
		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages',                     array( $this, 'add_woocommerce_settings_tab' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
			// Admin core
			require_once( 'includes/class-wc-checkout-fees-admin.php' );
			// Settings
			require_once( 'includes/settings/class-wc-checkout-fees-settings-section.php' );
			$this->settings = array();
			$this->settings['general']            = require_once( 'includes/settings/class-wc-checkout-fees-settings-general.php' );
			$this->settings['info']               = require_once( 'includes/settings/class-wc-checkout-fees-settings-info.php' );
			$this->settings['global-extra-fee']   = require_once( 'includes/settings/class-wc-checkout-fees-settings-global-extra-fee.php' );
			$this->settings['gateways']           = require_once( 'includes/settings/class-wc-checkout-fees-settings-gateways.php' );
			// Settings - Per product meta box
			$this->meta_box_settings = require_once( 'includes/settings/class-wc-checkout-fees-meta-boxes-per-product.php' );
			// Version
			if ( get_option( 'alg_woocommerce_checkout_fees_version', '' ) !== $this->version ) {
				add_action( 'admin_init', array( $this, 'version_updated' ) );
			}
		}

	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 2.5.0
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_checkout_fees' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
		if ( 'checkout-fees-for-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a href="https://www.tychesoftwares.com/store/premium-plugins/payment-gateway-based-fees-and-discounts-for-woocommerce-plugin/">' .
				__( 'Unlock All', 'checkout-fees-for-woocommerce' ) . '</a>';
		}
		return array_merge( $custom_links, $links );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 2.5.2
	 */
	function includes() {
		// Functions
		require_once( 'includes/functions/country-functions.php' );
		// Core
		$this->core = require_once( 'includes/class-wc-checkout-fees.php' );
	}

	/**
	 * version_updated.
	 *
	 * @version 2.5.2
	 * @since   2.5.0
	 */
	function version_updated() {
		foreach ( $this->settings as $section ) {
			foreach ( $section->get_settings() as $value ) {
				if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
					$autoload = isset( $value['autoload'] ) ? ( bool ) $value['autoload'] : true;
					add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}
		update_option( 'alg_woocommerce_checkout_fees_version', $this->version );
	}

	/**
	 * Add Woocommerce settings tab to WooCommerce settings.
	 *
	 * @version 2.5.2
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-wc-settings-checkout-fees.php' );
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @return string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @return string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}
}

endif;

if ( ! function_exists( 'alg_wc_cf' ) ) {
	/**
	 * Returns the main instance of Alg_Woocommerce_Checkout_Fees to prevent the need to use globals.
	 *
	 * @version 2.3.0
	 * @return  Alg_Woocommerce_Checkout_Fees
	 */
	function alg_wc_cf() {
		return Alg_Woocommerce_Checkout_Fees::instance();
	}
}

alg_wc_cf();
