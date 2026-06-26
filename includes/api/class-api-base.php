<?php
/**
 * Class Api_Base
 *
 * Shared base for every REST controller in this plugin.
 * Extends WP_REST_Controller to follow WordPress conventions.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \WP_Error;
use \WP_REST_Controller;
use \WP_REST_Request;
use \WP_REST_Response;

defined( 'ABSPATH' ) || exit;

abstract class Api_Base extends WP_REST_Controller {

	/**
	 * REST namespace shared across all plugin routes.
	 *
	 * @var string
	 */
	protected $namespace = 'pgbf-pro/v1';

	/**
	 * Plugin text domain.
	 *
	 * @var string
	 */
	protected $text_domain = 'checkout-fees-for-woocommerce';

	/**
	 * Default permission callback: require manage_woocommerce capability.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'pgbf_rest_forbidden',
				__( 'You do not have permission to manage Payment Gateway Fees settings.', 'checkout-fees-for-woocommerce' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}
		return true;
	}

	/**
	 * Wrap data in a standard success envelope.
	 *
	 * @param mixed $data
	 * @param int   $status HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function success( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Return a standard error response.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code.
	 * @return WP_REST_Response
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => false,
				'code'    => $code,
				'message' => $message,
			],
			$status
		);
	}

	/**
	 * Recursively sanitise a nested array for database storage.
	 * Preserves booleans, integers, and floats with proper casting.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	protected function deep_sanitize( $data ) {
		if ( is_array( $data ) ) {
			return array_map( [ $this, 'deep_sanitize' ], $data );
		}
		if ( is_bool( $data ) ) {
			return (bool) $data;
		}
		if ( is_int( $data ) ) {
			return (int) $data;
		}
		if ( is_float( $data ) ) {
			return (float) $data;
		}
		return sanitize_textarea_field( (string) $data );
	}

	/**
	 * Recursively merge two arrays, with $override values taking precedence.
	 * Indexed arrays (lists) are replaced wholesale; associative arrays are
	 * deep-merged so only the provided keys change.
	 *
	 * @param array $base
	 * @param array $override
	 * @return array
	 */
	protected function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				// Indexed lists are replaced wholesale; associative arrays deep-merged.
				if ( array_is_list( $value ) || array_is_list( $base[ $key ] ) ) {
					$base[ $key ] = $value;
				} else {
					$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
				}
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Alias for deep_merge, used by update handlers.
	 *
	 * @param array $existing   Currently saved data.
	 * @param array $sanitised  Incoming sanitised data to merge in.
	 * @return array
	 */
	protected function smart_merge( array $existing, array $sanitised ): array {
		return $this->deep_merge( $existing, $sanitised );
	}

	/**
	 * Safely read a value from a request body with a fallback.
	 *
	 * @param array  $body
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	protected function get_param( array $body, string $key, $default = '' ) {
		return $body[ $key ] ?? $default;
	}
}
