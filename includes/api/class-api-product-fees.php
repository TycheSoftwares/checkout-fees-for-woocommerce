<?php
/**
 * Class Api_Product_Fees
 *
 * Handles per-product fee settings with caching.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \WP_REST_Request;
use \WP_REST_Response;
use \WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Product_Fees
 */
class Api_Product_Fees extends Api_Base {

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'products';

	/**
	 * New consolidated meta key.
	 *
	 * @var string
	 */
	const NEW_META_KEY = '_pgbf_pro_product_fees';

	/**
	 * Per-product migration flag.
	 *
	 * @var string
	 */
	const MIGRATED_FLAG = '_pgbf_pro_product_fees_migrated';

	/**
	 * Per-request cache for fees.
	 *
	 * @var array<int, array>
	 */
	private static array $fees_cache = array();

	/**
	 * Per-request cache for gateways.
	 *
	 * @var array<string, \WC_Payment_Gateway>|null
	 */
	private static ?array $gateways_cache = null;

	/**
	 * Get fee defaults.
	 *
	 * @return array
	 */
	private static function fee_defaults(): array {
		return array(
			'title'           => '',
			'override_global' => 'no',
			'type'            => 'fixed',
			'value'           => '',
			'min_fee'         => '',
			'max_fee'         => '',
			'coupons_rule'    => 'disabled',
		);
	}

	/**
	 * Get general defaults.
	 *
	 * @return array
	 */
	private static function general_defaults(): array {
		return array(
			'min_cart_amount'    => '',
			'max_cart_amount'    => '',
			'rounding_enabled'   => false,
			'rounding_precision' => '',
			'tax_enabled'        => false,
			'tax_class'          => '',
			'exclude_shipping'   => false,
			'add_taxes'          => false,
			'percent_usage'      => 'for_all_cart',
			'fixed_usage'        => 'once',
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/fees',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product_fees' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_product_fees' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Get product fees.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_product_fees( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );

		if ( ! $product_id ) {
			return $this->error(
				'pgbf_invalid_product',
				__( 'Invalid product ID.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return $this->success( array() );
		}

		$gateway_ids = array_keys( $this->get_gateways() );
		$data        = $this->read_fees( $product_id, $gateway_ids );

		return $this->success( $data );
	}

	/**
	 * Update product fees.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_product_fees( WP_REST_Request $request ): WP_REST_Response {
		$product_id = absint( $request->get_param( 'id' ) );

		if ( ! $product_id ) {
			return $this->error(
				'pgbf_invalid_product',
				__( 'Invalid product ID.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		$data = $request->get_json_params();

		if ( ! is_array( $data ) ) {
			return $this->error(
				'pgbf_invalid_data',
				__( 'Invalid fees data.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		$sanitised = $this->sanitise_fees( $data );

		update_post_meta( $product_id, self::NEW_META_KEY, wp_json_encode( $sanitised ) );

		if ( ! get_post_meta( $product_id, self::MIGRATED_FLAG, true ) ) {
			update_post_meta( $product_id, self::MIGRATED_FLAG, '1.0.0' );
		}

		unset( self::$fees_cache[ $product_id ] );

		do_action( 'pgbf_pro_product_fees_saved', $product_id, $sanitised );

		return $this->success(
			array(
				'saved'      => true,
				'product_id' => $product_id,
			)
		);
	}

	/**
	 * Read fees for a product with caching.
	 *
	 * @param int      $product_id Product ID.
	 * @param string[] $gateway_ids Gateway IDs.
	 * @return array
	 */
	private function read_fees( int $product_id, array $gateway_ids ): array {
		if ( isset( self::$fees_cache[ $product_id ] ) ) {
			return $this->fill_missing_gateways( self::$fees_cache[ $product_id ], $gateway_ids );
		}

		$new_raw = get_post_meta( $product_id, self::NEW_META_KEY, true );

		if ( $new_raw ) {
			$new_data = json_decode( $new_raw, true );
			if ( is_array( $new_data ) ) {
				$result                          = $this->fill_missing_gateways( $new_data, $gateway_ids );
				self::$fees_cache[ $product_id ] = $result;
				return $result;
			}
		}

		$result                          = $this->read_old_meta( $product_id, $gateway_ids );
		self::$fees_cache[ $product_id ] = $result;
		return $result;
	}

	/**
	 * Read old format postmeta in a single query.
	 *
	 * @param int      $product_id Product ID.
	 * @param string[] $gateway_ids Gateway IDs.
	 * @return array
	 */
	private function read_old_meta( int $product_id, array $gateway_ids ): array {
		$all_meta = get_post_meta( $product_id );

		$get = function ( string $key, $default = '' ) use ( $all_meta ) {
			return isset( $all_meta[ $key ] ) ? $all_meta[ $key ][0] : $default;
		};

		$data = array();

		foreach ( $gateway_ids as $gid ) {
			$prefix = "_alg_checkout_fees_{$gid}";

			$data[ $gid ] = array(
				'enabled' => (bool) $get( "{$prefix}_enabled", false ),
				'fee_1'   => array(
					'title'           => (string) $get( $prefix, '' ),
					'override_global' => (string) ( $get( "{$prefix}_override_global_fee", '' ) ?: 'no' ),
					'type'            => (string) ( $get( "{$prefix}_type", '' ) ?: 'fixed' ),
					'value'           => (string) $get( "{$prefix}_value", '' ),
					'min_fee'         => (string) $get( "{$prefix}_min_fee", '' ),
					'max_fee'         => (string) $get( "{$prefix}_max_fee", '' ),
					'coupons_rule'    => (string) ( $get( "{$prefix}_coupons_rule", '' ) ?: 'disabled' ),
				),
				'fee_2'   => array(
					'title'           => (string) $get( "{$prefix}_text_2", '' ),
					'override_global' => (string) ( $get( "{$prefix}_override_global_fee_2", '' ) ?: 'no' ),
					'type'            => (string) ( $get( "{$prefix}_type_2", '' ) ?: 'fixed' ),
					'value'           => (string) $get( "{$prefix}_value_2", '' ),
					'min_fee'         => (string) $get( "{$prefix}_min_fee_2", '' ),
					'max_fee'         => (string) $get( "{$prefix}_max_fee_2", '' ),
					'coupons_rule'    => (string) ( $get( "{$prefix}_coupons_rule_2", '' ) ?: 'disabled' ),
				),
				'general' => array(
					'min_cart_amount'    => (string) $get( "{$prefix}_min_cart_amount", '' ),
					'max_cart_amount'    => (string) $get( "{$prefix}_max_cart_amount", '' ),
					'rounding_enabled'   => (bool) $get( "{$prefix}_rounding", false ),
					'rounding_precision' => (string) $get( "{$prefix}_rounding_precision", '' ),
					'tax_enabled'        => (bool) $get( "{$prefix}_is_taxable", false ),
					'tax_class'          => (string) $get( "{$prefix}_tax_class_id", '' ),
					'exclude_shipping'   => (bool) $get( "{$prefix}_exclude_shipping", false ),
					'add_taxes'          => (bool) $get( "{$prefix}_add_taxes", false ),
					'percent_usage'      => (string) ( $get( "{$prefix}_percent_usage", '' ) ?: 'for_all_cart' ),
					'fixed_usage'        => (string) ( $get( "{$prefix}_fixed_usage", '' ) ?: 'once' ),
				),
			);
		}

		return $data;
	}

	/**
	 * Fill missing gateways with defaults.
	 *
	 * @param array    $data Existing data.
	 * @param string[] $gateway_ids Gateway IDs.
	 * @return array
	 */
	private function fill_missing_gateways( array $data, array $gateway_ids ): array {
		foreach ( $gateway_ids as $gid ) {
			if ( ! isset( $data[ $gid ] ) ) {
				$data[ $gid ] = array(
					'enabled' => false,
					'fee_1'   => self::fee_defaults(),
					'fee_2'   => self::fee_defaults(),
					'general' => self::general_defaults(),
				);
			}
		}
		return $data;
	}

	/**
	 * Get gateways with caching.
	 *
	 * @return array<string, \WC_Payment_Gateway>
	 */
	private function get_gateways(): array {
		if ( null === self::$gateways_cache ) {
			self::$gateways_cache = WC()->payment_gateways->payment_gateways();
		}
		return self::$gateways_cache;
	}

	/**
	 * Sanitise fees data.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private function sanitise_fees( array $data ): array {
		$clean = array();

		foreach ( $data as $gid => $gateway_data ) {
			$gid = sanitize_key( $gid );
			if ( ! $gid || ! is_array( $gateway_data ) ) {
				continue;
			}

			$clean[ $gid ] = array(
				'enabled' => (bool) ( $gateway_data['enabled'] ?? false ),
				'fee_1'   => $this->sanitise_fee( $gateway_data['fee_1'] ?? array() ),
				'fee_2'   => $this->sanitise_fee( $gateway_data['fee_2'] ?? array() ),
				'general' => $this->sanitise_general( $gateway_data['general'] ?? array() ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitise fee data.
	 *
	 * @param array $fee Fee data.
	 * @return array
	 */
	private function sanitise_fee( array $fee ): array {
		return array(
			'title'           => sanitize_text_field( $fee['title'] ?? '' ),
			'override_global' => sanitize_key( $fee['override_global'] ?? 'no' ),
			'type'            => sanitize_key( $fee['type'] ?? 'fixed' ),
			'value'           => sanitize_text_field( $fee['value'] ?? '' ),
			'min_fee'         => sanitize_text_field( $fee['min_fee'] ?? '' ),
			'max_fee'         => sanitize_text_field( $fee['max_fee'] ?? '' ),
			'coupons_rule'    => sanitize_key( $fee['coupons_rule'] ?? 'disabled' ),
		);
	}

	/**
	 * Sanitise general data.
	 *
	 * @param array $general General data.
	 * @return array
	 */
	private function sanitise_general( array $general ): array {
		return array(
			'min_cart_amount'    => sanitize_text_field( $general['min_cart_amount'] ?? '' ),
			'max_cart_amount'    => sanitize_text_field( $general['max_cart_amount'] ?? '' ),
			'rounding_enabled'   => (bool) ( $general['rounding_enabled'] ?? false ),
			'rounding_precision' => sanitize_text_field( $general['rounding_precision'] ?? '' ),
			'tax_enabled'        => (bool) ( $general['tax_enabled'] ?? false ),
			'tax_class'          => sanitize_text_field( $general['tax_class'] ?? '' ),
			'exclude_shipping'   => (bool) ( $general['exclude_shipping'] ?? false ),
			'add_taxes'          => (bool) ( $general['add_taxes'] ?? false ),
			'percent_usage'      => sanitize_key( $general['percent_usage'] ?? 'for_all_cart' ),
			'fixed_usage'        => sanitize_key( $general['fixed_usage'] ?? 'once' ),
		);
	}
}