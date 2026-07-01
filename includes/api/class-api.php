<?php
/**
 * Class Api
 *
 * Central router for all PGBF Pro REST endpoints.
 *
 * Usage (from the main plugin bootstrap):
 *   Api::init();
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;

class Api {

	/**
	 * Controller instances.
	 *
	 * @var Api_Base[]
	 */
	private static array $controllers = [];

	/**
	 * Initialise the router. Call once from the main plugin file.
	 */
	public static function init(): void {
		self::load_controllers();
		add_action( 'rest_api_init', [ __CLASS__, 'register_all_routes' ] );
	}

	/**
	 * Require all controller files and store instances.
	 */
	private static function load_controllers(): void {
		$api_dir = PGBF_LITE_PLUGIN_PATH . '/includes/api/';

		// Base class must be loaded first.
		require_once $api_dir . 'class-api-base.php';

		$controller_files = [
			'settings'      => $api_dir . 'class-api-settings.php',
			'gateways'      => $api_dir . 'class-api-gateways.php',
			'product_fees'  => $api_dir . 'class-api-product-fees.php',
			'options'       => $api_dir . 'class-api-options.php',
		];

		$controller_classes = [
			'settings'      => 'TycheSoftwares\PaymentGatewayFees\Lite\Api_Settings',
			'gateways'      => 'TycheSoftwares\PaymentGatewayFees\Lite\Api_Gateways',
			'product_fees'  => 'TycheSoftwares\PaymentGatewayFees\Lite\Api_Product_Fees',
			'options'       => 'TycheSoftwares\PaymentGatewayFees\Lite\Api_Options',
		];

		foreach ( $controller_files as $key => $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				self::$controllers[ $key ] = new $controller_classes[ $key ]();
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				trigger_error( esc_html( "PGBF Pro: Controller file not found: $file" ), E_USER_WARNING ); // phpcs:ignore
			}
		}
	}

	/**
	 * Called on rest_api_init. Delegates route registration to each controller.
	 */
	public static function register_all_routes(): void {
		foreach ( self::$controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Retrieve a specific controller instance (useful for unit tests).
	 *
	 * @param string $key  One of: settings, gateways, product_fees, options.
	 * @return Api_Base|null
	 */
	public static function get_controller( string $key ): ?Api_Base {
		return self::$controllers[ $key ] ?? null;
	}
}
