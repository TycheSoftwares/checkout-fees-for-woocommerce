<?php
/**
 * Product Fees Helper
 * Reads from _pgbf_pro_product_fees (new) or falls back to old individual meta.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Product_Fees_Helper
 */
class Product_Fees_Helper {

	/**
	 * New consolidated meta key.
	 *
	 * @var string
	 */
	const NEW_META_KEY = '_pgbf_pro_product_fees';

	/**
	 * Runtime cache for product fees.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Get the full consolidated fees array for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null
	 */
	public static function get( int $product_id ): ?array {
		if ( array_key_exists( $product_id, self::$cache ) ) {
			return self::$cache[ $product_id ];
		}

		$raw = get_post_meta( $product_id, self::NEW_META_KEY, true );
		if ( $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				self::$cache[ $product_id ] = $decoded;
				return $decoded;
			}
		}

		self::$cache[ $product_id ] = null;
		return null;
	}

	/**
	 * Read a single field for a gateway.
	 *
	 * @param int    $product_id  Product ID.
	 * @param string $gateway_id  Gateway ID.
	 * @param string $section     Section name.
	 * @param string $field       Field name.
	 * @param mixed  $default     Default value.
	 * @return mixed
	 */
	public static function get_field( int $product_id, string $gateway_id, string $section, string $field, $default = '' ) {
		$new = self::get( $product_id );

		if ( null !== $new ) {
			if ( 'root' === $section ) {
				return isset( $new[ $gateway_id ][ $field ] ) ? $new[ $gateway_id ][ $field ] : $default;
			}
			return isset( $new[ $gateway_id ][ $section ][ $field ] ) ? $new[ $gateway_id ][ $section ][ $field ] : $default;
		}

		// Fallback to old individual postmeta.
		$old_key = self::old_key( $gateway_id, $section, $field );
		if ( $old_key ) {
			$val = get_post_meta( $product_id, $old_key, true );
			return ( '' !== $val && false !== $val ) ? $val : $default;
		}

		return $default;
	}

	/**
	 * Map section+field to old meta key.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param string $section    Section name.
	 * @param string $field      Field name.
	 * @return string|null
	 */
	private static function old_key( string $gateway_id, string $section, string $field ): ?string {
		$g = $gateway_id;

		$map = array(
			'root.enabled'                    => '_alg_checkout_fees_enabled_' . $g,
			'fee_1.title'                     => '_alg_checkout_fees_title_' . $g,
			'fee_1.value'                     => '_alg_checkout_fees_value_' . $g,
			'fee_1.type'                      => '_alg_checkout_fees_type_' . $g,
			'fee_1.min_fee'                   => '_alg_checkout_fees_min_fee_' . $g,
			'fee_1.max_fee'                   => '_alg_checkout_fees_max_fee_' . $g,
			'fee_1.coupons_rule'              => '_alg_checkout_fees_coupons_rule_' . $g,
			'fee_1.override_global'           => '_alg_checkout_fees_global_override_' . $g,
			'fee_2.title'                     => '_alg_checkout_fees_title_2_' . $g,
			'fee_2.value'                     => '_alg_checkout_fees_value_2_' . $g,
			'fee_2.type'                      => '_alg_checkout_fees_type_2_' . $g,
			'fee_2.min_fee'                   => '_alg_checkout_fees_min_fee_2_' . $g,
			'fee_2.max_fee'                   => '_alg_checkout_fees_max_fee_2_' . $g,
			'fee_2.coupons_rule'              => '_alg_checkout_fees_coupons_rule_2_' . $g,
			'fee_2.override_global'           => '_alg_checkout_fees_global_override_fee_2_' . $g,
			'general.min_cart_amount'         => '_alg_checkout_fees_min_cart_amount_' . $g,
			'general.max_cart_amount'         => '_alg_checkout_fees_max_cart_amount_' . $g,
			'general.rounding_enabled'        => '_alg_checkout_fees_rounding_enabled_' . $g,
			'general.rounding_precision'      => '_alg_checkout_fees_rounding_precision_' . $g,
			'general.tax_enabled'             => '_alg_checkout_fees_tax_enabled_' . $g,
			'general.tax_class'               => '_alg_checkout_fees_tax_class_' . $g,
			'general.exclude_shipping'        => '_alg_checkout_fees_exclude_shipping_' . $g,
			'general.add_taxes'               => '_alg_checkout_fees_add_taxes_' . $g,
			'general.percent_usage'           => '_alg_checkout_fees_percent_usage_' . $g,
			'general.fixed_usage'             => '_alg_checkout_fees_fixed_usage_' . $g,
		);

		$lookup = ( 'root' === $section ) ? "root.{$field}" : "{$section}.{$field}";
		return isset( $map[ $lookup ] ) ? $map[ $lookup ] : null;
	}
}
