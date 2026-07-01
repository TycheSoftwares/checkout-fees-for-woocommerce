<?php
/**
 * Class Api_Options
 *
 * Provides dynamic option lists needed by the React settings UI.
 *
 * Routes registered:
 *   GET /pgbf-pro/v1/options                 → get_all_options()
 *   GET /pgbf-pro/v1/options/categories      → search_categories()  (?search=...)
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \WC_Tax;
use \WP_REST_Request;
use \WP_REST_Response;
use \WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class Api_Options extends Api_Base {

	protected $rest_base = 'options';

	public function register_routes(): void {

		// GET /options — all option lists in one call
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_options' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// GET /options/categories?search=...
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/categories',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search_categories' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'search' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * GET /options
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_all_options( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( [
			'countries'        => $this->get_countries(),
			'states'           => $this->get_states(),
			'tax_classes'      => $this->get_tax_classes(),
			'shipping_methods' => $this->get_shipping_methods(),
			'card_schemes'     => $this->get_card_schemes(),
			'bank_names'         => $this->get_bank_names(),
			'product_categories' => $this->get_product_categories(),
		] );
	}

	/**
	 * GET /options/categories?search=...
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function search_categories( WP_REST_Request $request ): WP_REST_Response {
		$search = $request->get_param( 'search' );
		$args   = [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => 50,
			'orderby'    => 'name',
			'order'      => 'ASC',
		];

		if ( ! empty( $search ) ) {
			$args['search'] = $search;
		}

		$terms   = get_terms( $args );
		$options = [];

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = [
					'value' => (string) $term->term_id,
					'label' => $term->name,
				];
			}
		}

		return $this->success( $options );
	}

	// ─── Data helpers ──────────────────────────────────────────────────────────

	private function get_countries(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return [];
		}
		$options = [];
		$countries = array_merge( alg_checkout_fees_get_countries_sets(), alg_checkout_fees_get_countries() );
		foreach ( $countries as $code => $name ) {
			$options[] = [ 'value' => $code, 'label' => $name ];
		}
		return $options;
	}

	private function get_states(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return [];
		}

		$options    = [];
		$all_states = WC()->countries->get_states();

		if ( empty( $all_states ) || ! is_array( $all_states ) ) {
			return [];
		}

		$all_countries = WC()->countries->get_countries();

		foreach ( $all_states as $country_code => $states ) {
			if ( empty( $states ) || ! is_array( $states ) ) {
				continue;
			}
			$country_name = $all_countries[ $country_code ] ?? $country_code;
			foreach ( $states as $state_code => $state_name ) {
				$options[] = [
					'value'        => $country_code . ':' . $state_code,
					'label'        => wp_strip_all_tags( $state_name ),
					'country_code' => $country_code,
					'country_name' => wp_strip_all_tags( $country_name ),
					'state_code'   => $state_code,
				];
			}
		}

		return $options;
	}

	private function get_tax_classes(): array {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return array();
		}

		$options   = array();
		// Standard rate – value "0"
		$options[] = array(
			'value' => '0',
			'label' => __( 'Standard rate', 'checkout-fees-for-woocommerce' ),
		);

		$index = 1; // Start numbering from 1 for custom tax classes
		foreach ( WC_Tax::get_tax_classes() as $class ) {
			$options[] = array(
				'value' => (string) $index,
				'label' => $class,
			);
			$index++;
		}

		return $options;
	}

	private function get_shipping_methods(): array {
		$options = [];
		$seen    = [];

		$zones = \WC_Shipping_Zones::get_zones();
		foreach ( $zones as $zone_data ) {
			$zone = new \WC_Shipping_Zone( $zone_data['zone_id'] );
			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				$key = $method->id . ':' . $method->get_instance_id();
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$options[] = [
					'value' => $key,
					'label' => sprintf( '%s (%s)', wp_strip_all_tags( $method->get_title() ), $zone_data['zone_name'] ),
				];
				$seen[ $key ] = true;
			}
		}

		// Rest of World (zone 0)
		$row = new \WC_Shipping_Zone( 0 );
		foreach ( $row->get_shipping_methods( true ) as $method ) {
			$key = $method->id . ':' . $method->get_instance_id();
			if ( ! isset( $seen[ $key ] ) ) {
				$options[] = [
					'value' => $key,
					'label' => sprintf( '%s (Rest of World)', wp_strip_all_tags( $method->get_title() ) ),
				];
				$seen[ $key ] = true;
			}
		}

		return $options;
	}

	private function get_card_schemes(): array {
		// Delegates to alg_checkout_fees_card_scheme() defined in functions.php
		// so both the REST API and the PHP card rules use the same filterable list.
		$options = [];
		if ( function_exists( 'alg_checkout_fees_card_scheme' ) ) {
			foreach ( alg_checkout_fees_card_scheme() as $value => $label ) {
				$options[] = [ 'value' => $value, 'label' => $label ];
			}
		}
		return $options;
	}

	private function get_bank_names(): array {
		// Prepend 'Any' to match the PHP render_card_rule_row() pattern:
		// $bank_names = array_merge( array( 'Any' ), alg_checkout_fees_bank_names() )
		// Note: bank names use string values (not slugs), matching what PHP stores.
		$options = [
			[ 'value' => 'Any', 'label' => __( 'Any', 'checkout-fees-for-woocommerce' ) ],
		];

		if ( function_exists( 'alg_checkout_fees_bank_names' ) ) {
			foreach ( alg_checkout_fees_bank_names() as $bank_name ) {
				$options[] = [
					'value' => (string) $bank_name,
					'label' => (string) $bank_name,
				];
			}
		}

		return $options;
	}

	private function get_product_categories(): array {
		$terms   = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		$options = [];

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = [ 'value' => (string) $term->term_id, 'label' => $term->name ];
			}
		}

		return $options;
	}
}
