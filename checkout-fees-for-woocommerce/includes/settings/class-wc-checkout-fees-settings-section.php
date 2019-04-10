<?php
/**
 * Checkout Fees for WooCommerce - Settings Section
 *
 * @version 2.5.0
 * @since   2.5.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Checkout_Fees_Settings_Section' ) ) :

abstract class Alg_WC_Checkout_Fees_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function __construct() {
		add_filter( 'woocommerce_get_sections_alg_checkout_fees',              array( $this, 'settings_section' ) );
		add_filter( 'woocommerce_get_settings_alg_checkout_fees_' . $this->id, array( $this, 'get_settings' ), PHP_INT_MAX );
	}

	/**
	 * settings_section.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function settings_section( $sections ) {
		$sections[ $this->id ] = $this->desc;
		return $sections;
	}

	/**
	 * get_settings.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	abstract function get_settings();

}

endif;
