<?php
/**
 * Checkout Fees Args
 * Replaces class-alg-wc-checkout-fees-args.php
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TycheSoftwares\PaymentGatewayFees\Lite\Settings;
use TycheSoftwares\PaymentGatewayFees\Lite\Product_Fees_Helper;

/**
 * Build args arrays for fee calculation.
 */
class Checkout_Fees_Args {

	/**
	 * Constructor.
	 */
	public function __construct() {
		return true;
	}

	/**
	 * Get global (gateway-level) fee args.
	 *
	 * @param string $current_gateway Gateway ID.
	 * @return array
	 */
	public function get_the_args_global( $current_gateway ) {
		$core = pgbf_lite()->core;
		$gid  = $current_gateway;

		$args = array();
		$args['current_gateway']  = $gid;
		$args['fee_scope']        = 'global';
		$args['is_enabled']       = Settings::fee( $gid, 'fee_1', 'enabled', false ) ? 'yes' : 'no';

		$args['min_cart_amount']  = $core->convert_currency( Settings::fee( $gid, 'general', 'min_cart_amount', 0 ) );
		$args['max_cart_amount']  = $core->convert_currency( Settings::fee( $gid, 'general', 'max_cart_amount', 0 ) );

		$args['min_fee']          = $core->convert_currency( Settings::fee( $gid, 'fee_1', 'min_fee', 0 ) );
		$args['max_fee']          = $core->convert_currency( Settings::fee( $gid, 'fee_1', 'max_fee', 0 ) );

		$args['min_fee_2']        = $core->convert_currency( Settings::fee( $gid, 'fee_2', 'min_fee', 0 ) );
		$args['max_fee_2']        = $core->convert_currency( Settings::fee( $gid, 'fee_2', 'max_fee', 0 ) );

		$args['coupons_rule']     = Settings::fee( $gid, 'fee_1', 'coupons_rule', 'disabled' );
		$args['coupons_rule_2']   = Settings::fee( $gid, 'fee_2', 'coupons_rule', 'disabled' );

		$args['fee_text']         = Settings::fee( $gid, 'fee_1', 'title', '' );
		$args['fee_value']        = Settings::fee( $gid, 'fee_1', 'value', 0 );
		$args['fee_type']         = Settings::fee( $gid, 'fee_1', 'type', 'fixed' );

		$args['fee_text_2']       = Settings::fee( $gid, 'fee_2', 'title', '' );
		$args['fee_value_2']      = Settings::fee( $gid, 'fee_2', 'value', 0 );
		$args['fee_type_2']       = Settings::fee( $gid, 'fee_2', 'type', 'fixed' );

		$args['do_round']         = Settings::fee( $gid, 'general', 'rounding_enabled', false ) ? 'yes' : 'no';
		$args['precision']        = Settings::fee( $gid, 'general', 'rounding_precision', 0 );

		$args['is_taxable']       = Settings::fee( $gid, 'general', 'tax_enabled', false ) ? 'yes' : 'no';
		$args['tax_class_id']     = Settings::fee( $gid, 'general', 'tax_class', 0 );

		$args['exclude_shipping'] = Settings::fee( $gid, 'general', 'exclude_shipping', false ) ? 'yes' : 'no';
		$args['add_taxes']        = Settings::fee( $gid, 'general', 'add_taxes', false ) ? 'yes' : 'no';

		$args['product_id']       = 0;
		$args['product_qty']      = 0;
		$args['fixed_usage']      = 'once';

		return $args;
	}

	/**
	 * Get per-product (local) fee args.
	 *
	 * @param string $current_gateway Gateway ID.
	 * @param int    $product_id      Product ID.
	 * @param int    $variation_id    Variation ID.
	 * @param int    $product_qty     Product quantity.
	 * @return array
	 */
	public function get_the_args_local( $current_gateway, $product_id, $variation_id, $product_qty ) {
		$core = pgbf_lite()->core;
		$gid  = $current_gateway;

		$do_add_product_name = Settings::general( 'per_product_add_name', false );

		if ( $do_add_product_name ) {
			if ( isset( $variation_id ) && 0 !== $variation_id ) {
				$_product               = wc_get_product( $variation_id );
				$product_formatted_name = ' &ndash; ' . $_product->get_title() . ' &ndash; ' .
					( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' )
						? $_product->get_formatted_variation_attributes( true )
						: wc_get_formatted_variation( $_product, true ) );
			} else {
				$_product               = wc_get_product( $product_id );
				$product_formatted_name = ' &ndash; ' . $_product->get_title();
			}
		}

		$args = array();
		$args['current_gateway'] = $gid;
		$args['fee_scope']       = 'local';

		$args['is_enabled'] = Product_Fees_Helper::get_field( $product_id, $gid, 'root', 'enabled', '' );

		$args['min_cart_amount'] = $core->convert_currency( Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'min_cart_amount', '' ) );
		$args['max_cart_amount'] = $core->convert_currency( Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'max_cart_amount', '' ) );

		$args['min_fee']   = $core->convert_currency( Product_Fees_Helper::get_field( $product_id, $gid, 'fee_1', 'min_fee', '' ) );
		$args['max_fee']   = $core->convert_currency( Product_Fees_Helper::get_field( $product_id, $gid, 'fee_1', 'max_fee', '' ) );

		$args['min_fee_2'] = $core->convert_currency( Product_Fees_Helper::get_field( $product_id, $gid, 'fee_2', 'min_fee', '' ) );
		$args['max_fee_2'] = $core->convert_currency( Product_Fees_Helper::get_field( $product_id, $gid, 'fee_2', 'max_fee', '' ) );

		$args['coupons_rule']   = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_1', 'coupons_rule', '' );
		$args['coupons_rule_2'] = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_2', 'coupons_rule', '' );

		$fee_1_title            = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_1', 'title', '' );
		$args['fee_text']       = $do_add_product_name ? $fee_1_title . $product_formatted_name : $fee_1_title;

		$args['fee_value']      = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_1', 'value', '' );
		$args['fee_type']       = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_1', 'type', '' );

		$fee_2_title            = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_2', 'title', '' );
		$args['fee_text_2']     = $do_add_product_name ? $fee_2_title . $product_formatted_name : $fee_2_title;

		$args['fee_value_2']    = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_2', 'value', '' );
		$args['fee_type_2']     = Product_Fees_Helper::get_field( $product_id, $gid, 'fee_2', 'type', '' );

		$args['do_round']       = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'rounding_enabled', '' );
		$args['precision']      = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'rounding_precision', '' );

		$args['is_taxable']     = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'tax_enabled', '' );
		$args['tax_class_id']   = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'tax_class', 0 );

		$args['exclude_shipping'] = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'exclude_shipping', '' );
		$args['add_taxes']        = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'add_taxes', '' );

		$percent_usage           = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'percent_usage', '' );
		$args['product_id']      = ( 'by_product' === $percent_usage )
			? ( isset( $variation_id ) && 0 !== $variation_id ? $variation_id : $product_id )
			: 0;

		$args['product_qty']     = $product_qty;
		$args['fixed_usage']     = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'fixed_usage', '' );

		return $args;
	}
}

// Backward-compat alias.
if ( ! class_exists( 'Alg_WC_Checkout_Fees_Args' ) ) {
	class_alias( __NAMESPACE__ . '\\Checkout_Fees_Args', 'Alg_WC_Checkout_Fees_Args' );
}
