<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - File Loader
 *
 * @version 3.0.0
 * @since   3.0.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Files
 *
 * Loads all plugin files in the correct order.
 */
class Files {

	/**
	 * Load all plugin files in dependency order.
	 *
	 * @return Checkout_Fees|null
	 */
	public static function load(): ?Checkout_Fees {
		self::require( 'includes/functions/functions.php' );
		self::require( 'includes/admin/class-settings.php' );
		self::require( 'includes/admin/class-migration.php' );
		self::require( 'includes/api/class-api.php' );
		self::require( 'includes/admin/class-product-meta-migration.php' );

		self::require( 'includes/helper/class-fees-helper.php' );
		self::require( 'includes/helper/class-fees-args.php' );
		self::require( 'includes/frontend/class-fees-info.php' );
		new Checkout_Fees_Info();

		$core = self::require( 'includes/core/class-hooks.php' );

		self::require( 'includes/admin/class-order-fees.php' );
		new Checkout_Order_Fees();
		self::require_if_exists( 'includes/blocks/blocks.php' );

		if ( is_admin() ) {
			self::load_admin();
		}

		return ( $core instanceof Checkout_Fees ) ? $core : null;
	}

	/**
	 * Load admin-only files.
	 */
	private static function load_admin(): void {
		self::require( 'includes/admin/class-admin-page.php' );
		self::require( 'includes/admin/class-product-metabox.php' );
		self::require( 'includes/admin/class-admin.php' );

		$tyche_files = array(
			'includes/class-tracking.php',
			'includes/class-deactivation.php',
		);

		foreach ( $tyche_files as $file ) {
			self::require_if_exists( $file );
		}
	}

	/**
	 * Require a file relative to the plugin root.
	 *
	 * @param string $relative_path Path relative to PGBF_LITE_PLUGIN_PATH.
	 * @return mixed
	 */
	private static function require( string $relative_path ) {
		return require_once PGBF_LITE_PLUGIN_PATH . '/' . $relative_path;
	}

	/**
	 * Require a file only if it exists on disk.
	 *
	 * @param string $relative_path Path relative to PGBF_LITE_PLUGIN_PATH.
	 * @return mixed|null
	 */
	private static function require_if_exists( string $relative_path ) {
		$full_path = PGBF_LITE_PLUGIN_PATH . '/' . $relative_path;
		if ( file_exists( $full_path ) ) {
			return require_once $full_path;
		}
		return null;
	}
}