<?php
/**
 * Class Api_Settings
 *
 * Handles reading and writing the plugin's main settings object.
 *
 * Routes registered:
 *   GET  /wp-json/pgbf-pro/v1/settings       → get_settings()
 *   POST /wp-json/pgbf-pro/v1/settings       → update_settings()
 *   POST /wp-json/pgbf-pro/v1/settings/reset → reset_section()
 *   DELETE /wp-json/pgbf-pro/v1/data         → delete_all_data()
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \WP_REST_Request;
use \WP_REST_Response;
use \WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Api_Settings
 */
class Api_Settings extends Api_Base {

	/**
	 * WordPress option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'pgbf_pro_settings';

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_update_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reset_section' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'section' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'description'       => __( 'Settings section key to reset.', 'checkout-fees-for-woocommerce' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/data',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_all_data' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * GET /settings
	 * Retrieve the full saved settings object, merged with plugin defaults.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$saved    = get_option( self::OPTION_KEY, array() );
		$defaults = $this->get_defaults();
		$merged   = $this->deep_merge( $defaults, is_array( $saved ) ? $saved : array() );

		$merged['_pgbf_gateway_configured'] = $this->is_any_gateway_configured();

		return $this->success( $merged );
	}

	/**
	 * Check whether at least one gateway has fee_1 (or fee_2) enabled.
	 *
	 * @return bool
	 */
	private function is_any_gateway_configured(): bool {
		$gateway_settings = get_option( 'pgbf_pro_gateway_settings', array() );

		if ( ! is_array( $gateway_settings ) || empty( $gateway_settings ) ) {
			return false;
		}

		foreach ( $gateway_settings as $gw ) {
			if ( ! is_array( $gw ) ) {
				continue;
			}
			$fee1_enabled = ! empty( $gw['fee_1']['enabled'] );
			$fee2_enabled = ! empty( $gw['fee_2']['enabled'] );

			if ( $fee1_enabled || $fee2_enabled ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * POST /settings
	 * Persist the full settings object after sanitisation.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$body     = $request->get_json_params();
		$incoming = $this->get_param( $body, 'settings', array() );

		if ( ! is_array( $incoming ) ) {
			return $this->error(
				'pgbf_invalid_settings',
				__( 'Settings must be a valid object.', 'checkout-fees-for-woocommerce' ),
				400
			);
		}

		$sanitised = $this->sanitize_settings( $incoming );
		$existing  = get_option( self::OPTION_KEY, array() );
		$merged    = $this->smart_merge( is_array( $existing ) ? $existing : array(), $sanitised );

		update_option( self::OPTION_KEY, $merged, false );

		do_action( 'pgbf_pro_settings_saved', $merged, $incoming );

		return $this->success( $merged );
	}

	/**
	 * POST /settings/reset
	 * Reset a single section to plugin defaults.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function reset_section( WP_REST_Request $request ): WP_REST_Response {
		$section  = $request->get_param( 'section' );
		$defaults = $this->get_defaults();

		if ( ! array_key_exists( $section, $defaults ) ) {
			return $this->error(
				'pgbf_unknown_section',
				sprintf(
					/* translators: %s: section key */
					__( 'Unknown settings section: %s', 'checkout-fees-for-woocommerce' ),
					$section
				),
				404
			);
		}

		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings[ $section ] = $defaults[ $section ];

		update_option( self::OPTION_KEY, $settings, false );

		do_action( 'pgbf_pro_section_reset', $section, $defaults[ $section ] );

		return $this->success( $defaults[ $section ] );
	}

	/**
	 * DELETE /data
	 * Permanently delete all plugin options and product meta.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function delete_all_data( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		delete_option( self::OPTION_KEY );
		delete_option( 'pgbf_pro_gateway_settings' );
		delete_option( 'pgbf_pro_migration_version' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE 'alg_woocommerce_checkout_fees_%'
			    OR option_name LIKE 'alg_gateways_fees_%'
			    OR option_name LIKE 'alg_wc_checkout_fees_%'"
		);

		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta}
			 WHERE meta_key LIKE '_alg_checkout_fees_%'"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		do_action( 'pgbf_pro_all_data_deleted' );

		return $this->success( array( 'deleted' => true ) );
	}

	/**
	 * Sanitise a full settings array section by section.
	 *
	 * @param array $data Raw incoming settings array.
	 * @return array Sanitised settings array.
	 */
	private function sanitize_settings( array $data ): array {
		$clean = array();

		if ( isset( $data['general'] ) ) {
			$general = $data['general'];
			$clean['general'] = array(
				'enabled'              => isset( $general['enabled'] ) ? (bool) $general['enabled'] : true,
				'per_product_enabled'  => isset( $general['per_product_enabled'] ) ? (bool) $general['per_product_enabled'] : false,
				'per_product_add_name' => isset( $general['per_product_add_name'] ) ? (bool) $general['per_product_add_name'] : false,
				'merge_all_fees'       => isset( $general['merge_all_fees'] ) ? (bool) $general['merge_all_fees'] : false,
				'max_total_discount'   => isset( $general['max_total_discount'] ) ? (float) $general['max_total_discount'] : 0,
				'max_total_fee'        => isset( $general['max_total_fee'] ) ? (float) $general['max_total_fee'] : 0,
				'hide_on_cart'         => isset( $general['hide_on_cart'] ) ? (bool) $general['hide_on_cart'] : false,
			);
		}

		if ( isset( $data['global_extra_fee'] ) ) {
			$global = $data['global_extra_fee'];
			$clean['global_extra_fee'] = array(
				'enabled'          => isset( $global['enabled'] ) ? (bool) $global['enabled'] : false,
				'as_extra_only'    => isset( $global['as_extra_only'] ) ? (bool) $global['as_extra_only'] : false,
				'gateways_exclude' => isset( $global['gateways_exclude'] ) ? array_map( 'sanitize_key', (array) $global['gateways_exclude'] ) : array(),
				'title'            => isset( $global['title'] ) ? sanitize_text_field( $global['title'] ) : '',
				'type'             => isset( $global['type'] ) ? sanitize_key( $global['type'] ) : 'fixed',
				'value'            => isset( $global['value'] ) ? (float) $global['value'] : 0,
				'min_cart_amount'  => isset( $global['min_cart_amount'] ) ? (float) $global['min_cart_amount'] : 0,
				'max_cart_amount'  => isset( $global['max_cart_amount'] ) ? (float) $global['max_cart_amount'] : 0,
			);
		}

		if ( isset( $data['info'] ) ) {
			$info          = $data['info'];
			$product_page  = isset( $info['product_page'] ) ? $info['product_page'] : array();
			$lowest_price  = isset( $info['lowest_price'] ) ? $info['lowest_price'] : array();
			$clean['info'] = array(
				'product_page' => array(
					'enabled'    => isset( $product_page['enabled'] ) ? (bool) $product_page['enabled'] : false,
					'start_html' => isset( $product_page['start_html'] ) ? wp_kses_post( $product_page['start_html'] ) : '<table>',
					'row_html'   => isset( $product_page['row_html'] ) ? wp_kses_post( $product_page['row_html'] ) : '',
					'end_html'   => isset( $product_page['end_html'] ) ? wp_kses_post( $product_page['end_html'] ) : '</table>',
					'position'   => isset( $product_page['position'] ) ? sanitize_text_field( $product_page['position'] ) : 'woocommerce_single_product_summary',
					'priority'   => isset( $product_page['priority'] ) ? absint( $product_page['priority'] ) : 20,
				),
				'lowest_price' => array(
					'enabled'       => isset( $lowest_price['enabled'] ) ? (bool) $lowest_price['enabled'] : false,
					'template_html' => isset( $lowest_price['template_html'] ) ? wp_kses_post( $lowest_price['template_html'] ) : '',
					'position'      => isset( $lowest_price['position'] ) ? sanitize_text_field( $lowest_price['position'] ) : 'woocommerce_single_product_summary',
					'priority'      => isset( $lowest_price['priority'] ) ? absint( $lowest_price['priority'] ) : 20,
				),
				'hide_on_out_of_stock'  => isset( $info['hide_on_out_of_stock'] ) ? (bool) $info['hide_on_out_of_stock'] : false,
				'variable_info_display' => isset( $info['variable_info_display'] ) ? sanitize_key( $info['variable_info_display'] ) : 'for_each_variation',
			);
		}

		if ( isset( $data['bin_apis'] ) ) {
			$bin            = $data['bin_apis'];
			$binlist        = isset( $bin['binlist'] ) ? $bin['binlist'] : array();
			$neutrino       = isset( $bin['neutrinoapi'] ) ? $bin['neutrinoapi'] : array();
			$card_section   = isset( $bin['card_section'] ) ? $bin['card_section'] : array();
			$clean['bin_apis'] = array(
				'enabled'      => isset( $bin['enabled'] ) ? (bool) $bin['enabled'] : false,
				'provider'     => isset( $bin['provider'] ) ? sanitize_key( $bin['provider'] ) : 'binlist',
				'binlist'      => array(
					'cache_enabled'        => isset( $binlist['cache_enabled'] ) ? (bool) $binlist['cache_enabled'] : true,
					'cache_duration_hours' => isset( $binlist['cache_duration_hours'] ) ? absint( $binlist['cache_duration_hours'] ) : 24,
				),
				'neutrinoapi'  => array(
					'user_id' => isset( $neutrino['user_id'] ) ? sanitize_text_field( $neutrino['user_id'] ) : '',
					'api_key' => isset( $neutrino['api_key'] ) ? sanitize_text_field( $neutrino['api_key'] ) : '',
				),
				'card_section' => array(
					'enabled'          => isset( $card_section['enabled'] ) ? (bool) $card_section['enabled'] : false,
					'show_type'        => isset( $card_section['show_type'] ) ? (bool) $card_section['show_type'] : false,
					'show_scheme'      => isset( $card_section['show_scheme'] ) ? (bool) $card_section['show_scheme'] : false,
					'show_location'    => isset( $card_section['show_location'] ) ? (bool) $card_section['show_location'] : false,
					'show_bank_name'   => isset( $card_section['show_bank_name'] ) ? (bool) $card_section['show_bank_name'] : false,
				),
			);
		}

		return $clean;
	}

	/**
	 * Full default settings tree.
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		return array(
			'general' => array(
				'enabled'              => true,
				'per_product_enabled'  => false,
				'per_product_add_name' => false,
				'merge_all_fees'       => false,
				'max_total_discount'   => 0,
				'max_total_fee'        => 0,
				'hide_on_cart'         => false,
			),
			'global_extra_fee' => array(
				'enabled'          => false,
				'as_extra_only'    => false,
				'gateways_exclude' => array(),
				'title'            => '',
				'type'             => 'fixed',
				'value'            => 0,
				'min_cart_amount'  => 0,
				'max_cart_amount'  => 0,
			),
			'info' => array(
				'product_page' => array(
					'enabled'    => false,
					'start_html' => '<table>',
					'row_html'   => '<tr><td><strong>%gateway_title%</strong></td><td>%product_original_price%</td><td>%product_gateway_price%</td><td>%product_price_diff%</td></tr>',
					'end_html'   => '</table>',
					'position'   => 'woocommerce_single_product_summary',
					'priority'   => 20,
				),
				'lowest_price' => array(
					'enabled'       => false,
					'template_html' => '<p><strong>%gateway_title%</strong> %product_gateway_price% (%product_price_diff%)</p>',
					'position'      => 'woocommerce_single_product_summary',
					'priority'      => 20,
				),
				'hide_on_out_of_stock'  => false,
				'variable_info_display' => 'for_each_variation',
			),
			'bin_apis' => array(
				'enabled'      => false,
				'provider'     => 'binlist',
				'binlist'      => array(
					'cache_enabled'        => true,
					'cache_duration_hours' => 24,
				),
				'neutrinoapi'  => array(
					'user_id' => '',
					'api_key' => '',
				),
				'card_section' => array(
					'enabled'        => false,
					'show_type'      => false,
					'show_scheme'    => false,
					'show_location'  => false,
					'show_bank_name' => false,
				),
			),
			'license' => array(
				'key'              => '',
				'status'           => 'inactive',
				'expires'          => '',
				'activations_left' => '',
			),
		);
	}

	/**
	 * Get item schema.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'pgbf-settings',
			'type'       => 'object',
			'properties' => array(
				'settings' => array(
					'description' => __( 'Plugin settings object.', 'checkout-fees-for-woocommerce' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
				),
			),
		);
	}

	/**
	 * Get update arguments schema.
	 *
	 * @return array
	 */
	private function get_update_args(): array {
		return array(
			'settings' => array(
				'required'    => true,
				'type'        => 'object',
				'description' => __( 'Full settings object to persist.', 'checkout-fees-for-woocommerce' ),
			),
		);
	}
}