<?php
/**
 * Class Api_Gateways
 *
 * Handles per-gateway settings stored under pgbf_pro_gateway_settings.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \WP_REST_Request;
use \WP_REST_Response;
use \WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Gateways
 */
class Api_Gateways extends Api_Base {

	/**
	 * WordPress option key for all gateway settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'pgbf_pro_gateway_settings';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'gateways';

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'pgbf_gateways_';

	/**
	 * Cache expiration (1 hour).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_gateways' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_gateway' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_gateway' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_gateway_section' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'section' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'description'       => __( 'Gateway section to reset: fee_1, fee_2, general, or card_rules.', 'checkout-fees-for-woocommerce' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-zA-Z0-9_-]+)/reset-all',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_gateway_all' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Get list of active gateways with caching.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_gateways( WP_REST_Request $request ): WP_REST_Response {
		$cache_key = self::CACHE_PREFIX . 'list';
		$cached    = wp_cache_get( $cache_key, 'pgbf_gateways' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $this->success( $cached );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return $this->success( array() );
		}

		$gateways = array();
		$non_card_gateways = apply_filters(
			'alg_wc_checkout_fees_non_card_payment_gateways',
			array( 'cod', 'bacs', 'cheque' )
		);

		foreach ( WC()->payment_gateways->payment_gateways() as $id => $gateway ) {

			// Skip gateways that are not enabled in WooCommerce → Settings → Payments.
			if ( 'yes' !== $gateway->enabled ) {
				continue;
			}

			$gateways[] = array(
				'id'            => sanitize_key( $id ),
				'title'         => wp_strip_all_tags( $gateway->get_title() ),
				'supports_card' => ! in_array( $id, $non_card_gateways, true ),
			);
		}

		wp_cache_set( $cache_key, $gateways, 'pgbf_gateways', self::CACHE_EXPIRATION );

		return $this->success( $gateways );
	}

	/**
	 * Get single gateway settings with caching.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_gateway( WP_REST_Request $request ): WP_REST_Response {
		$id = sanitize_key( $request->get_param( 'id' ) );

		if ( empty( $id ) ) {
			return $this->error(
				'pgbf_missing_gateway_id',
				__( 'Gateway ID is required.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		$cache_key = self::CACHE_PREFIX . 'gateway_' . $id;
		$cached    = wp_cache_get( $cache_key, 'pgbf_gateways' );

		if ( false !== $cached && is_array( $cached ) ) {
			return $this->success( $cached );
		}

		$all_settings     = get_option( self::OPTION_KEY, array() );
		$gateway_saved    = is_array( $all_settings ) && isset( $all_settings[ $id ] ) ? $all_settings[ $id ] : array();
		$gateway_defaults = $this->get_gateway_defaults();

		$merged = $this->deep_merge( $gateway_defaults, $gateway_saved );

		wp_cache_set( $cache_key, $merged, 'pgbf_gateways', self::CACHE_EXPIRATION );

		return $this->success( $merged );
	}

	/**
	 * Update single gateway settings and clear cache.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_gateway( WP_REST_Request $request ): WP_REST_Response {
		$id   = sanitize_key( $request->get_param( 'id' ) );
		$body = $request->get_json_params();
		$incoming = is_array( $body ) ? $body : array();

		if ( empty( $id ) ) {
			return $this->error(
				'pgbf_missing_gateway_id',
				__( 'Gateway ID is required.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		$sanitised    = $this->sanitize_gateway_settings( $incoming );
		$all_settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $all_settings ) ) {
			$all_settings = array();
		}

		$existing              = isset( $all_settings[ $id ] ) ? $all_settings[ $id ] : array();
		$all_settings[ $id ]   = $this->smart_merge( $existing, $sanitised );

		update_option( self::OPTION_KEY, $all_settings, false );

		$this->clear_gateway_cache( $id );

		do_action( 'pgbf_pro_gateway_saved', $id, $all_settings[ $id ] );

		return $this->success( $all_settings[ $id ] );
	}

	/**
	 * Reset one section of a gateway and clear cache.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reset_gateway_section( WP_REST_Request $request ): WP_REST_Response {
		$id      = sanitize_key( $request->get_param( 'id' ) );
		$section = $request->get_param( 'section' );
		$defaults = $this->get_gateway_defaults();

		if ( empty( $id ) ) {
			return $this->error(
				'pgbf_missing_gateway_id',
				__( 'Gateway ID is required.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		if ( ! array_key_exists( $section, $defaults ) ) {
			return $this->error(
				'pgbf_unknown_gateway_section',
				sprintf(
					/* translators: %s: section key */
					__( 'Unknown gateway section: %s', 'checkout-fees-for-woocommerce' ),
					$section
				),
				404
			);
		}

		$all_settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $all_settings ) ) {
			$all_settings = array();
		}

		if ( ! isset( $all_settings[ $id ] ) ) {
			$all_settings[ $id ] = $defaults;
		}

		$all_settings[ $id ][ $section ] = $defaults[ $section ];

		update_option( self::OPTION_KEY, $all_settings, false );

		$this->clear_gateway_cache( $id );

		do_action( 'pgbf_pro_gateway_section_reset', $id, $section, $defaults[ $section ] );

		return $this->success( $defaults[ $section ] );
	}

	/**
	 * Reset all sections of a gateway to defaults and clear cache.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reset_gateway_all( WP_REST_Request $request ): WP_REST_Response {
		$id       = sanitize_key( $request->get_param( 'id' ) );
		$defaults = $this->get_gateway_defaults();

		if ( empty( $id ) ) {
			return $this->error(
				'pgbf_missing_gateway_id',
				__( 'Gateway ID is required.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		$all_settings = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $all_settings ) ) {
			$all_settings = array();
		}

		$all_settings[ $id ] = $defaults;

		update_option( self::OPTION_KEY, $all_settings, false );

		$this->clear_gateway_cache( $id );

		do_action( 'pgbf_pro_gateway_reset_all', $id, $defaults );

		return $this->success( $defaults );
	}

	/**
	 * Clear cache for a specific gateway.
	 *
	 * @param string $gateway_id Gateway ID.
	 */
	private function clear_gateway_cache( string $gateway_id ): void {
		wp_cache_delete( self::CACHE_PREFIX . 'gateway_' . $gateway_id, 'pgbf_gateways' );
		wp_cache_delete( self::CACHE_PREFIX . 'list', 'pgbf_gateways' );
	}

	/**
	 * Sanitise gateway settings.
	 *
	 * @param array $data Raw incoming gateway settings.
	 * @return array Sanitised gateway settings.
	 */
	private function sanitize_gateway_settings( array $data ): array {
		$clean = array();

		$fee_keys = array( 'fee_1', 'fee_2' );

		foreach ( $fee_keys as $fee_key ) {
			if ( isset( $data[ $fee_key ] ) ) {
				$fee = $data[ $fee_key ];
				$clean[ $fee_key ] = array(
					'enabled'           => isset( $fee['enabled'] ) ? (bool) $fee['enabled'] : false,
					'title'             => isset( $fee['title'] ) ? sanitize_text_field( $fee['title'] ) : '',
					'type'              => isset( $fee['type'] ) ? sanitize_key( $fee['type'] ) : 'fixed',
					'value'             => isset( $fee['value'] ) ? (float) $fee['value'] : 0,
					'min_fee'           => isset( $fee['min_fee'] ) ? (float) $fee['min_fee'] : 0,
					'max_fee'           => isset( $fee['max_fee'] ) ? (float) $fee['max_fee'] : 0,
					'coupons_rule'      => isset( $fee['coupons_rule'] ) ? sanitize_key( $fee['coupons_rule'] ) : 'disabled',
					'countries_include' => isset( $fee['countries_include'] ) ? array_map( 'sanitize_text_field', (array) $fee['countries_include'] ) : array(),
					'countries_exclude' => isset( $fee['countries_exclude'] ) ? array_map( 'sanitize_text_field', (array) $fee['countries_exclude'] ) : array(),
					'states_include'    => isset( $fee['states_include'] ) ? array_map( 'sanitize_text_field', (array) $fee['states_include'] ) : array(),
					'states_exclude'    => isset( $fee['states_exclude'] ) ? array_map( 'sanitize_text_field', (array) $fee['states_exclude'] ) : array(),
					'cats_include'      => isset( $fee['cats_include'] ) ? array_map( 'absint', (array) $fee['cats_include'] ) : array(),
					'cats_exclude'      => isset( $fee['cats_exclude'] ) ? array_map( 'absint', (array) $fee['cats_exclude'] ) : array(),
					'shipping_include'  => isset( $fee['shipping_include'] ) ? array_map( 'sanitize_text_field', (array) $fee['shipping_include'] ) : array(),
					'shipping_exclude'  => isset( $fee['shipping_exclude'] ) ? array_map( 'sanitize_text_field', (array) $fee['shipping_exclude'] ) : array(),
				);
			}
		}

		if ( isset( $data['general'] ) ) {
			$general = $data['general'];
			$clean['general'] = array(
				'min_cart_amount'        => isset( $general['min_cart_amount'] ) ? (float) $general['min_cart_amount'] : 0,
				'max_cart_amount'        => isset( $general['max_cart_amount'] ) ? (float) $general['max_cart_amount'] : 0,
				'rounding_enabled'       => isset( $general['rounding_enabled'] ) ? (bool) $general['rounding_enabled'] : false,
				'rounding_precision'     => isset( $general['rounding_precision'] ) ? absint( $general['rounding_precision'] ) : 0,
				'tax_enabled'            => isset( $general['tax_enabled'] ) ? (bool) $general['tax_enabled'] : false,
				'tax_class'              => isset( $general['tax_class'] ) ? sanitize_text_field( $general['tax_class'] ) : '',
				'exclude_shipping'       => isset( $general['exclude_shipping'] ) ? (bool) $general['exclude_shipping'] : false,
				'add_taxes'              => isset( $general['add_taxes'] ) ? (bool) $general['add_taxes'] : false,
				'countries_include'      => isset( $general['countries_include'] ) ? array_map( 'sanitize_text_field', (array) $general['countries_include'] ) : array(),
				'countries_exclude'      => isset( $general['countries_exclude'] ) ? array_map( 'sanitize_text_field', (array) $general['countries_exclude'] ) : array(),
				'cats_include_calc_type' => isset( $general['cats_include_calc_type'] ) ? sanitize_key( $general['cats_include_calc_type'] ) : '',
				'cats_exclude_calc_type' => isset( $general['cats_exclude_calc_type'] ) ? sanitize_key( $general['cats_exclude_calc_type'] ) : '',
			);
		}

		if ( isset( $data['card_rules'] ) ) {
			$card_rules = $data['card_rules'];
			$clean['card_rules'] = array(
				'enabled'                   => isset( $card_rules['enabled'] ) ? (bool) $card_rules['enabled'] : false,
				'show_card_payment_display' => isset( $card_rules['show_card_payment_display'] ) ? (bool) $card_rules['show_card_payment_display'] : false,
				'rules'                     => $this->sanitize_card_rules( isset( $card_rules['rules'] ) ? (array) $card_rules['rules'] : array() ),
			);
		}

		return $clean;
	}

	/**
	 * Sanitise card rules array.
	 *
	 * @param array $rules Raw card rules.
	 * @return array Sanitised card rules.
	 */
	private function sanitize_card_rules( array $rules ): array {
		$clean = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$clean[] = array(
				'type'     => isset( $rule['type'] ) ? sanitize_key( $rule['type'] ) : 'any',
				'scheme'   => isset( $rule['scheme'] ) ? array_map( 'sanitize_key', (array) $rule['scheme'] ) : array( 'any' ),
				'country'  => isset( $rule['country'] ) ? array_map( 'sanitize_text_field', (array) $rule['country'] ) : array( 'any' ),
				'location' => isset( $rule['location'] ) ? sanitize_key( $rule['location'] ) : 'any',
				'bank'     => isset( $rule['bank'] ) ? array_map( 'sanitize_text_field', (array) $rule['bank'] ) : array( 'Any' ),
				'fee'      => isset( $rule['fee'] ) ? (float) $rule['fee'] : 0,
				'fee_type' => isset( $rule['fee_type'] ) ? sanitize_key( $rule['fee_type'] ) : 'fixed',
			);
		}

		return $clean;
	}

	/**
	 * Get gateway defaults.
	 *
	 * @return array
	 */
	public function get_gateway_defaults(): array {
		$fee_defaults = array(
			'enabled'           => false,
			'title'             => '',
			'type'              => 'fixed',
			'value'             => 0,
			'min_fee'           => 0,
			'max_fee'           => 0,
			'coupons_rule'      => 'disabled',
			'countries_include' => array(),
			'countries_exclude' => array(),
			'states_include'    => array(),
			'states_exclude'    => array(),
			'cats_include'      => array(),
			'cats_exclude'      => array(),
			'shipping_include'  => array(),
			'shipping_exclude'  => array(),
		);

		return array(
			'fee_1'      => $fee_defaults,
			'fee_2'      => $fee_defaults,
			'general'    => array(
				'min_cart_amount'        => 0,
				'max_cart_amount'        => 0,
				'rounding_enabled'       => false,
				'rounding_precision'     => 0,
				'tax_enabled'            => false,
				'tax_class'              => '',
				'exclude_shipping'       => false,
				'add_taxes'              => false,
				'countries_include'      => array(),
				'countries_exclude'      => array(),
				'cats_include_calc_type' => '',
				'cats_exclude_calc_type' => '',
			),
			'card_rules' => array(
				'enabled'                   => false,
				'show_card_payment_display' => false,
				'rules'                     => array(
					array(
						'type'     => 'any',
						'scheme'   => array( 'any' ),
						'country'  => array( 'any' ),
						'location' => 'any',
						'bank'     => array( 'Any' ),
						'fee'      => 0,
						'fee_type' => 'fixed',
					),
				),
			),
		);
	}
}