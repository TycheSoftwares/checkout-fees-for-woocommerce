<?php
/**
 * Class Product_Meta_Migration
 *
 * Migrates per-product fee data from individual postmeta rows (old format)
 * into a single consolidated postmeta key _pgbf_pro_product_fees (new format).
 *
 * ── Old format (one row per field per gateway per product) ────────────────────
 *   _alg_checkout_fees_enabled_{gid}
 *   _alg_checkout_fees_title_{gid}
 *   _alg_checkout_fees_global_override_{gid}
 *   _alg_checkout_fees_type_{gid}
 *   _alg_checkout_fees_value_{gid}
 *   _alg_checkout_fees_min_fee_{gid}
 *   _alg_checkout_fees_max_fee_{gid}
 *   _alg_checkout_fees_coupons_rule_{gid}
 *   _alg_checkout_fees_title_2_{gid}
 *   _alg_checkout_fees_global_override_fee_2_{gid}
 *   _alg_checkout_fees_type_2_{gid}
 *   _alg_checkout_fees_value_2_{gid}
 *   _alg_checkout_fees_min_fee_2_{gid}
 *   _alg_checkout_fees_max_fee_2_{gid}
 *   _alg_checkout_fees_coupons_rule_2_{gid}
 *   _alg_checkout_fees_min_cart_amount_{gid}
 *   _alg_checkout_fees_max_cart_amount_{gid}
 *   _alg_checkout_fees_rounding_enabled_{gid}
 *   _alg_checkout_fees_rounding_precision_{gid}
 *   _alg_checkout_fees_tax_enabled_{gid}
 *   _alg_checkout_fees_tax_class_{gid}
 *   _alg_checkout_fees_exclude_shipping_{gid}
 *   _alg_checkout_fees_add_taxes_{gid}
 *   _alg_checkout_fees_percent_usage_{gid}
 *   _alg_checkout_fees_fixed_usage_{gid}
 *
 * ── New format (one row per product) ─────────────────────────────────────────
 *   _pgbf_pro_product_fees → JSON-encoded array:
 *   {
 *     "bacs": {
 *       "enabled": true,
 *       "fee_1":   { "title":"", "override_global":"no", "type":"fixed",
 *                    "value":"", "min_fee":"", "max_fee":"", "coupons_rule":"disabled" },
 *       "fee_2":   { same shape },
 *       "general": { "min_cart_amount":"", "max_cart_amount":"",
 *                    "rounding_enabled":false, "rounding_precision":"",
 *                    "tax_enabled":false, "tax_class":"",
 *                    "exclude_shipping":false, "add_taxes":false,
 *                    "percent_usage":"for_all_cart", "fixed_usage":"once" }
 *     },
 *     "cod": { ... }
 *   }
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \Throwable;
use \WC_Log_Levels;
use \WP_REST_Request;
use \WP_REST_Response;
use \WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Product_Meta_Migration {

	/** New consolidated meta key */
	const NEW_META_KEY        = '_pgbf_pro_product_fees';

	/** Per-product flag set after successful migration */
	const MIGRATED_FLAG       = '_pgbf_pro_product_fees_migrated';

	/** Migration version stored in options */
	const MIGRATION_VERSION   = '1.0.0';
	const VERSION_OPTION      = 'pgbf_pro_product_meta_migration_version';

	/** Options tracking overall progress */
	const PROGRESS_OPTION     = 'pgbf_pro_product_meta_migration_progress';
	const STATUS_OPTION       = 'pgbf_pro_product_meta_migration_status';

	/** ActionScheduler hook names */
	const AS_HOOK_BATCH       = 'pgbf_pro_migrate_product_batch';
	const AS_HOOK_SINGLE      = 'pgbf_pro_migrate_single_product';

	/** Products processed per batch */
	const BATCH_SIZE          = 20;

	/** WC logger channel */
	const LOG_CHANNEL         = 'pgbf-migration';

	/**
	 * Register ActionScheduler hooks.
	 * Call once from the main plugin bootstrap.
	 */
	public static function init(): void {
		add_action( self::AS_HOOK_BATCH,  array( __CLASS__, 'process_batch' ) );
		add_action( self::AS_HOOK_SINGLE, array( __CLASS__, 'migrate_single_product' ) );
		add_action( 'rest_api_init',      array( __CLASS__, 'register_rest_routes' ) );
	}

	/**
	 * Kick off migration if not already running or complete.
	 *
	 * @return bool True if migration was started, false if already done/running.
	 */
	public static function start(): bool {
		$status = get_option( self::STATUS_OPTION, 'none' );

		if ( 'complete' === $status && get_option( self::VERSION_OPTION ) === self::MIGRATION_VERSION ) {
			return false;
		}
		if ( 'running' === $status && self::has_pending_actions() ) {
			return false;
		}

		$total = self::count_products_needing_migration();

		if ( 0 === $total ) {
			update_option( self::VERSION_OPTION,  self::MIGRATION_VERSION );
			update_option( self::STATUS_OPTION,   'complete' );
			update_option( self::PROGRESS_OPTION, array( 'total' => 0, 'done' => 0, 'failed' => 0 ) );
			return false;
		}

		update_option( self::STATUS_OPTION, 'running' );
		update_option( self::PROGRESS_OPTION, array(
			'total'   => $total,
			'done'    => 0,
			'failed'  => 0,
			'started' => time(),
		) );

		self::log( "Migration started. Total products to migrate: {$total}." );
		self::schedule_next_batch( 0 );

		return true;
	}

	/**
	 * Return current migration status.
	 *
	 * @return array
	 */
	public static function get_status(): array {
		$status   = get_option( self::STATUS_OPTION, 'none' );
		$progress = get_option( self::PROGRESS_OPTION, array() );

		$total   = isset( $progress['total'] ) ? (int) $progress['total'] : 0;
		$done    = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
		$failed  = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;
		$pending = max( 0, $total - $done - $failed );
		$percent = $total > 0 ? min( 100, (int) round( ( $done / $total ) * 100 ) ) : 0;

		return array(
			'status'  => $status,
			'total'   => $total,
			'done'    => $done,
			'failed'  => $failed,
			'pending' => $pending,
			'percent' => $percent,
		);
	}

	/**
	 * Process one batch of products.
	 *
	 * @param int $offset Batch offset (unused but kept for compatibility).
	 */
	public static function process_batch( int $offset = 0 ): void {
		global $wpdb;

		if ( 'running' !== get_option( self::STATUS_OPTION ) ) {
			return;
		}

		$ids = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT DISTINCT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm
			         ON pm.post_id = p.ID
			        AND pm.meta_key LIKE %s
			 WHERE p.post_type IN ('product','product_variation')
			   AND p.post_status != 'trash'
			   AND p.ID NOT IN (
			       SELECT post_id FROM {$wpdb->postmeta}
			       WHERE meta_key = %s
			   )
			 ORDER BY p.ID ASC
			 LIMIT %d",
			'\_alg\_checkout\_fees\_%',
			self::MIGRATED_FLAG,
			self::BATCH_SIZE
		) );

		if ( empty( $ids ) ) {
			self::finish();
			return;
		}

		foreach ( $ids as $product_id ) {
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time(),
					self::AS_HOOK_SINGLE,
					array( 'product_id' => (int) $product_id ),
					'pgbf-migration'
				);
			} else {
				self::migrate_single_product( (int) $product_id );
			}
		}

		self::schedule_next_batch( $offset + self::BATCH_SIZE, 5 );
	}

	/**
	 * Migrate a single product's postmeta.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function migrate_single_product( int $product_id ): void {
		if ( ! $product_id ) {
			return;
		}
		if ( get_post_meta( $product_id, self::MIGRATED_FLAG, true ) ) {
			self::increment_progress( 'done' );
			return;
		}

		try {
			$fees_data = self::read_old_meta( $product_id );
			update_post_meta( $product_id, self::NEW_META_KEY, wp_json_encode( $fees_data ) );
			update_post_meta( $product_id, self::MIGRATED_FLAG, self::MIGRATION_VERSION );
			self::increment_progress( 'done' );
			self::log( "Product {$product_id} migrated successfully." );
		} catch ( Throwable $e ) {
			self::increment_progress( 'failed' );
			self::log(
				"Product {$product_id} migration failed: " . $e->getMessage(),
				WC_Log_Levels::ERROR
			);
		}
	}

	/**
	 * Read all old _alg_checkout_fees_* postmeta for a product and return
	 * the consolidated array in the new format.
	 *
	 * This version handles keys where the gateway ID appears at the end of the key,
	 * e.g. _alg_checkout_fees_enabled_bacs.
	 *
	 * @param int $product_id Product ID.
	 * @return array Consolidated fees array keyed by gateway ID.
	 */
	private static function read_old_meta( int $product_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT meta_key, meta_value
			 FROM {$wpdb->postmeta}
			 WHERE post_id = %d
			   AND meta_key LIKE %s",
			$product_id,
			'\_alg\_checkout\_fees\_%'
		), ARRAY_A );

		if ( empty( $rows ) ) {
			return array();
		}

		$meta = array();
		foreach ( $rows as $row ) {
			$meta[ $row['meta_key'] ] = $row['meta_value'];
		}

		// Extract all unique gateway IDs (suffix after the last underscore).
		$gateway_ids = array();
		foreach ( array_keys( $meta ) as $key ) {
			$last_underscore = strrpos( $key, '_' );
			if ( $last_underscore !== false ) {
				$candidate = substr( $key, $last_underscore + 1 );
				if ( ! empty( $candidate ) ) {
					$gateway_ids[ $candidate ] = true;
				}
			}
		}
		$gateway_ids = array_keys( $gateway_ids );

		if ( empty( $gateway_ids ) ) {
			return array();
		}

		$data = array();

		foreach ( $gateway_ids as $gid ) {
			$data[ $gid ] = array(
				'enabled' => self::get_meta_bool( $meta, "_alg_checkout_fees_enabled_{$gid}", false ),
				'fee_1'   => array(
					'title'           => self::get_meta_string( $meta, "_alg_checkout_fees_title_{$gid}", '' ),
					'override_global' => self::get_meta_string( $meta, "_alg_checkout_fees_global_override_{$gid}", 'no' ),
					'type'            => self::get_meta_string( $meta, "_alg_checkout_fees_type_{$gid}", 'fixed' ),
					'value'           => self::get_meta_string( $meta, "_alg_checkout_fees_value_{$gid}", '' ),
					'min_fee'         => self::get_meta_string( $meta, "_alg_checkout_fees_min_fee_{$gid}", '' ),
					'max_fee'         => self::get_meta_string( $meta, "_alg_checkout_fees_max_fee_{$gid}", '' ),
					'coupons_rule'    => self::get_meta_string( $meta, "_alg_checkout_fees_coupons_rule_{$gid}", 'disabled' ),
				),
				'fee_2'   => array(
					'title'           => self::get_meta_string( $meta, "_alg_checkout_fees_title_2_{$gid}", '' ),
					'override_global' => self::get_meta_string( $meta, "_alg_checkout_fees_global_override_fee_2_{$gid}", 'no' ),
					'type'            => self::get_meta_string( $meta, "_alg_checkout_fees_type_2_{$gid}", 'fixed' ),
					'value'           => self::get_meta_string( $meta, "_alg_checkout_fees_value_2_{$gid}", '' ),
					'min_fee'         => self::get_meta_string( $meta, "_alg_checkout_fees_min_fee_2_{$gid}", '' ),
					'max_fee'         => self::get_meta_string( $meta, "_alg_checkout_fees_max_fee_2_{$gid}", '' ),
					'coupons_rule'    => self::get_meta_string( $meta, "_alg_checkout_fees_coupons_rule_2_{$gid}", 'disabled' ),
				),
				'general' => array(
					'min_cart_amount'    => self::get_meta_string( $meta, "_alg_checkout_fees_min_cart_amount_{$gid}", '' ),
					'max_cart_amount'    => self::get_meta_string( $meta, "_alg_checkout_fees_max_cart_amount_{$gid}", '' ),
					'rounding_enabled'   => self::get_meta_bool( $meta, "_alg_checkout_fees_rounding_enabled_{$gid}", false ),
					'rounding_precision' => self::get_meta_string( $meta, "_alg_checkout_fees_rounding_precision_{$gid}", '' ),
					'tax_enabled'        => self::get_meta_bool( $meta, "_alg_checkout_fees_tax_enabled_{$gid}", false ),
					'tax_class'          => self::get_meta_string( $meta, "_alg_checkout_fees_tax_class_{$gid}", '' ),
					'exclude_shipping'   => self::get_meta_bool( $meta, "_alg_checkout_fees_exclude_shipping_{$gid}", false ),
					'add_taxes'          => self::get_meta_bool( $meta, "_alg_checkout_fees_add_taxes_{$gid}", false ),
					'percent_usage'      => self::get_meta_string( $meta, "_alg_checkout_fees_percent_usage_{$gid}", 'for_all_cart' ),
					'fixed_usage'        => self::get_meta_string( $meta, "_alg_checkout_fees_fixed_usage_{$gid}", 'once' ),
				),
			);
		}

		return $data;
	}

	/**
	 * Helper: get meta value as string with fallback.
	 *
	 * @param array  $meta    Meta array.
	 * @param string $key     Meta key.
	 * @param string $default Default value.
	 * @return string
	 */
	private static function get_meta_string( array $meta, string $key, string $default ): string {
		return isset( $meta[ $key ] ) ? (string) $meta[ $key ] : $default;
	}

	/**
	 * Helper: get meta value as boolean with fallback.
	 *
	 * @param array  $meta    Meta array.
	 * @param string $key     Meta key.
	 * @param bool   $default Default value.
	 * @return bool
	 */
	private static function get_meta_bool( array $meta, string $key, bool $default ): bool {
		if ( isset( $meta[ $key ] ) ) {
			$value = $meta[ $key ];
			return in_array( strtolower( $value ), array( 'yes', '1', 'true', 'on' ), true );
		}
		return $default;
	}

	/**
	 * Atomically increment a progress counter.
	 *
	 * @param string $key 'done' or 'failed'.
	 */
	private static function increment_progress( string $key ): void {
		$progress = get_option( self::PROGRESS_OPTION, array() );
		$current  = isset( $progress[ $key ] ) ? (int) $progress[ $key ] : 0;
		$progress[ $key ] = $current + 1;
		update_option( self::PROGRESS_OPTION, $progress );

		$total  = isset( $progress['total'] ) ? (int) $progress['total'] : 0;
		$done   = isset( $progress['done'] ) ? (int) $progress['done'] : 0;
		$failed = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;

		if ( $total > 0 && ( $done + $failed ) >= $total ) {
			self::finish();
		}
	}

	/**
	 * Mark migration as complete and log summary.
	 */
	private static function finish(): void {
		$current = get_option( self::STATUS_OPTION, 'running' );
		if ( in_array( $current, array( 'complete', 'failed' ), true ) ) {
			return;
		}

		$progress = get_option( self::PROGRESS_OPTION, array() );
		$failed   = isset( $progress['failed'] ) ? (int) $progress['failed'] : 0;

		if ( $failed > 0 ) {
			update_option( self::STATUS_OPTION, 'partial' );
			self::log(
				"Migration completed with {$failed} error(s). "
				. "Failed products retain their original meta. "
				. "Consider switching to the previous plugin version if fees are not applying correctly.",
				WC_Log_Levels::WARNING
			);
		} else {
			update_option( self::STATUS_OPTION, 'complete' );
			update_option( self::VERSION_OPTION, self::MIGRATION_VERSION );
			self::log( 'Migration completed successfully. All products migrated.' );
		}

		$elapsed = time() - ( isset( $progress['started'] ) ? (int) $progress['started'] : time() );
		self::log( "Migration finished in {$elapsed} seconds. Done: {$progress['done']}, Failed: {$failed}, Total: {$progress['total']}." );
	}

	/**
	 * Schedule the next batch action.
	 *
	 * @param int $offset Batch offset to pass.
	 * @param int $delay  Seconds delay before running.
	 */
	private static function schedule_next_batch( int $offset, int $delay = 0 ): void {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			self::process_batch( $offset );
			return;
		}
		if ( as_next_scheduled_action( self::AS_HOOK_BATCH, array( 'offset' => $offset ), 'pgbf-migration' ) ) {
			return;
		}
		as_schedule_single_action(
			time() + $delay,
			self::AS_HOOK_BATCH,
			array( 'offset' => $offset ),
			'pgbf-migration'
		);
	}

	/**
	 * Check whether any migration actions are still pending.
	 *
	 * @return bool
	 */
	private static function has_pending_actions(): bool {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return false;
		}
		return (bool) as_next_scheduled_action( self::AS_HOOK_BATCH, array(), 'pgbf-migration' )
			|| (bool) as_next_scheduled_action( self::AS_HOOK_SINGLE, array(), 'pgbf-migration' );
	}

	/**
	 * Count products that have old-format meta but no migration flag.
	 *
	 * @return int
	 */
	public static function count_products_needing_migration(): int {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT( DISTINCT p.ID )
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm
			         ON pm.post_id = p.ID
			        AND pm.meta_key LIKE %s
			 WHERE p.post_type IN ('product','product_variation')
			   AND p.post_status != 'trash'
			   AND p.ID NOT IN (
			       SELECT post_id FROM {$wpdb->postmeta}
			       WHERE meta_key = %s
			   )",
			'\_alg\_checkout\_fees\_%',
			self::MIGRATED_FLAG
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Register REST routes.
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			'pgbf-pro/v1',
			'/product-migration/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);

		register_rest_route(
			'pgbf-pro/v1',
			'/product-migration/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_start' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);

		register_rest_route(
			'pgbf-pro/v1',
			'/product-migration/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_dismiss' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);
	}

	/**
	 * REST endpoint: get status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_get_status( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => true, 'data' => self::get_status() ), 200 );
	}

	/**
	 * REST endpoint: start migration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_start( WP_REST_Request $request ): WP_REST_Response {
		$started = self::start();
		return new WP_REST_Response( array(
			'success' => true,
			'data'    => array_merge( self::get_status(), array( 'newly_started' => $started ) ),
		), 200 );
	}

	/**
	 * REST endpoint: dismiss notice.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function rest_dismiss( WP_REST_Request $request ): WP_REST_Response {
		update_option( 'pgbf_pro_product_migration_dismissed', '1' );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Write a message to the WooCommerce log.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level.
	 */
	private static function log( string $message, string $level = 'info' ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->log( $level, '[PGBF Product Migration] ' . $message, array( 'source' => self::LOG_CHANNEL ) );
	}

	/**
	 * Determine if admin notice should be displayed.
	 *
	 * @return bool
	 */
	public static function should_show_notice(): bool {
		if ( get_option( 'pgbf_pro_product_migration_dismissed' ) ) {
			return false;
		}
		$status = get_option( self::STATUS_OPTION, 'none' );
		return in_array( $status, array( 'running', 'complete', 'partial', 'pending' ), true );
	}
}
