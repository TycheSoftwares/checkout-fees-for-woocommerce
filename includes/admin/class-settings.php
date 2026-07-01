<?php
/**
 * Class Settings
 *
 * Single-read settings helper with persistent caching.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings
 */
class Settings {

	/**
	 * Main settings option key.
	 *
	 * @var string
	 */
	const SETTINGS_KEY = 'pgbf_pro_settings';

	/**
	 * Gateway settings option key.
	 *
	 * @var string
	 */
	const GATEWAY_KEY = 'pgbf_pro_gateway_settings';

	/**
	 * Cache key prefix for settings.
	 *
	 * @var string
	 */
	const CACHE_PREFIX = 'pgbf_';

	/**
	 * Cache expiration time in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 3600;

	/**
	 * Cached settings array (request-level).
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Cached gateway settings array (request-level).
	 *
	 * @var array|null
	 */
	private static $gateway_settings = null;

	/**
	 * Whether settings are already saved to cache.
	 *
	 * @var bool
	 */
	private static $cache_initialized = false;

	/**
	 * Register the backward-compat option shim.
	 */
	public static function init(): void {
		
		
		// Hook into settings updates to clear cache.
		add_action( 'update_option_' . self::SETTINGS_KEY, array( __CLASS__, 'clear_persistent_cache' ), 10, 0 );
		add_action( 'update_option_' . self::GATEWAY_KEY, array( __CLASS__, 'clear_persistent_cache' ), 10, 0 );
		add_action( 'add_option_' . self::SETTINGS_KEY, array( __CLASS__, 'clear_persistent_cache' ), 10, 0 );
		add_action( 'add_option_' . self::GATEWAY_KEY, array( __CLASS__, 'clear_persistent_cache' ), 10, 0 );
	}

	/**
	 * Load and cache the main settings option with persistent cache.
	 *
	 * @return array
	 */
	private static function settings(): array {
		// Return request-level cached value if available.
		if ( null !== self::$settings ) {
			return self::$settings;
		}

		// Try to get from persistent cache first.
		$cache_key = self::CACHE_PREFIX . self::SETTINGS_KEY;
		$cached    = wp_cache_get( $cache_key, 'pgbf_settings' );

		if ( false !== $cached && is_array( $cached ) ) {
			self::$settings = $cached;
			return self::$settings;
		}

		// Fall back to database.
		$raw            = get_option( self::SETTINGS_KEY, array() );
		self::$settings = is_array( $raw ) ? $raw : array();

		// Store in persistent cache.
		wp_cache_set( $cache_key, self::$settings, 'pgbf_settings', self::CACHE_EXPIRATION );

		return self::$settings;
	}

	/**
	 * Load and cache the gateway settings option with persistent cache.
	 *
	 * @return array
	 */
	private static function gateway_settings(): array {
		// Return request-level cached value if available.
		if ( null !== self::$gateway_settings ) {
			return self::$gateway_settings;
		}

		// Try to get from persistent cache first.
		$cache_key = self::CACHE_PREFIX . self::GATEWAY_KEY;
		$cached    = wp_cache_get( $cache_key, 'pgbf_settings' );

		if ( false !== $cached && is_array( $cached ) ) {
			self::$gateway_settings = $cached;
			return self::$gateway_settings;
		}

		// Fall back to database.
		$raw                    = get_option( self::GATEWAY_KEY, array() );
		self::$gateway_settings = is_array( $raw ) ? $raw : array();

		// Store in persistent cache.
		wp_cache_set( $cache_key, self::$gateway_settings, 'pgbf_settings', self::CACHE_EXPIRATION );

		return self::$gateway_settings;
	}

	/**
	 * Clear persistent cache.
	 */
	public static function clear_persistent_cache(): void {
		wp_cache_delete( self::CACHE_PREFIX . self::SETTINGS_KEY, 'pgbf_settings' );
		wp_cache_delete( self::CACHE_PREFIX . self::GATEWAY_KEY, 'pgbf_settings' );
		self::flush_cache();
	}

	/**
	 * Flush the internal request-level cache.
	 */
	public static function flush_cache(): void {
		self::$settings         = null;
		self::$gateway_settings = null;
	}

	/**
	 * Return the full settings array.
	 *
	 * @return array
	 */
	public static function all(): array {
		return self::settings();
	}

	/**
	 * Read a value from the 'general' section.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function general( string $key, $default = null ) {
		$settings = self::settings();
		if ( isset( $settings['general'][ $key ] ) ) {
			return $settings['general'][ $key ];
		}
		return $default;
	}

	/**
	 * Read a value from the 'global_extra_fee' section.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function global_fee( string $key, $default = null ) {
		$settings = self::settings();
		if ( isset( $settings['global_extra_fee'][ $key ] ) ) {
			return $settings['global_extra_fee'][ $key ];
		}
		return $default;
	}

	/**
	 * Read a value from the 'info' section.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function info( string $key, $default = null ) {
		$settings = self::settings();
		if ( isset( $settings['info'][ $key ] ) ) {
			return $settings['info'][ $key ];
		}
		return $default;
	}

	/**
	 * Read a value from the 'bin_apis' section.
	 *
	 * @param string $key     Setting key (supports dot notation).
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function bin_apis( string $key, $default = null ) {
		$bin = self::settings()['bin_apis'] ?? array();

		if ( false !== strpos( $key, '.' ) ) {
			$parts   = explode( '.', $key, 2 );
			$section = $parts[0];
			$subkey  = $parts[1];
			if ( isset( $bin[ $section ][ $subkey ] ) ) {
				return $bin[ $section ][ $subkey ];
			}
			return $default;
		}

		if ( isset( $bin[ $key ] ) ) {
			return $bin[ $key ];
		}
		return $default;
	}

	/**
	 * Read a per-gateway setting.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param string $section    Section name.
	 * @param string $key        Field key.
	 * @param mixed  $default    Default value.
	 * @return mixed
	 */
	public static function fee( string $gateway_id, string $section, string $key, $default = null ) {
		$gateway_settings = self::gateway_settings();

		if ( isset( $gateway_settings[ $gateway_id ][ $section ][ $key ] ) ) {
			return $gateway_settings[ $gateway_id ][ $section ][ $key ];
		}
		return $default;
	}

	/**
	 * Read the full settings object for one gateway.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return array
	 */
	public static function gateway( string $gateway_id ): array {
		$gateway_settings = self::gateway_settings();
		if ( isset( $gateway_settings[ $gateway_id ] ) ) {
			return $gateway_settings[ $gateway_id ];
		}
		return array();
	}
}

if ( ! class_exists( 'PGBF_Settings' ) ) {
	class_alias( __NAMESPACE__ . '\\Settings', 'PGBF_Settings' );
}