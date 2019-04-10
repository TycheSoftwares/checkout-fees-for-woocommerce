<?php
/**
 * Checkout Fees for WooCommerce - Settings
 *
 * @version 2.5.0
 * @since   1.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Settings_Checkout_Fees' ) ) :

class Alg_WC_Settings_Checkout_Fees extends WC_Settings_Page {

	/**
	 * Constructor.
	 *
	 * @version 2.5.0
	 */
	function __construct() {

		$this->id    = 'alg_checkout_fees';
		$this->label = __( 'Payment Gateway Based Fees and Discounts', 'checkout-fees-for-woocommerce' );

		parent::__construct();

		add_action( 'woocommerce_update_options_' . $this->id,                                array( $this, 'maybe_reset_settings' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option',                             array( $this, 'maybe_unclean_option' ), PHP_INT_MAX, 3 );
		add_action( 'woocommerce_admin_field_' . 'alg_woocommerce_checkout_fees_custom_link', array( $this, 'output_custom_link' ) );

	}

	/**
	 * get_settings.
	 *
	 * @version 2.5.0
	 */
	function get_settings() {
		global $current_section;
		return array_merge( apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() ), array(
			array(
				'title'     => __( 'Reset Settings', 'checkout-fees-for-woocommerce' ),
				'type'      => 'title',
				'id'        => 'alg_woocommerce_checkout_fees_' . $current_section . '_reset_options',
			),
			array(
				'title'     => __( 'Reset section settings', 'checkout-fees-for-woocommerce' ),
				'desc'      => '<strong>' . __( 'Reset', 'checkout-fees-for-woocommerce' ) . '</strong>',
				'id'        => 'alg_woocommerce_checkout_fees_' . $current_section . '_reset',
				'default'   => 'no',
				'type'      => 'checkbox',
			),
			array(
				'type'      => 'sectionend',
				'id'        => 'alg_woocommerce_checkout_fees_' . $current_section . '_reset_options',
			),
		) );
	}

	/**
	 * maybe_reset_settings.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function maybe_reset_settings() {
		global $current_section;
		if ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_' . $current_section . '_reset', 'no' ) ) {
			foreach ( $this->get_settings() as $value ) {
				if ( isset( $value['id'] ) ) {
					if ( false !== strpos( $value['id'], '[' ) ) {
						$id = explode( '[', $value['id'] );
						$id = $id[0];
						delete_option( $id );
					} else {
						delete_option( $value['id'] );
					}
				}
			}
		}
	}

	/**
	 * maybe_unclean_option.
	 *
	 * @version 2.5.0
	 * @since   2.5.0
	 */
	function maybe_unclean_option( $value, $option, $raw_value ) {
		return ( isset( $option['alg_woocommerce_checkout_fees_raw'] ) && $option['alg_woocommerce_checkout_fees_raw'] ? $raw_value : $value );
	}

	/**
	 * output_custom_link.
	 *
	 * @version 2.2.2
	 * @since   2.2.2
	 */
	function output_custom_link( $value ) {
		$tooltip_html = ( isset( $value['desc_tip'] ) && '' != $value['desc_tip'] ) ?
			'<span class="woocommerce-help-tip" data-tip="' . $value['desc_tip'] . '"></span>' : '';
		?><tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label><?php echo $tooltip_html; ?>
			</th>
			<td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
				<?php echo $value['link']; ?>
			</td>
		</tr><?php
	}

}

endif;

return new Alg_WC_Settings_Checkout_Fees();
