<?php
/**
 * Payment Gateway Based Fees and Discounts for WooCommerce - Main Class
 *
 * @version 3.0.0
 * @since   3.0.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use \Automattic\WooCommerce\Utilities\FeaturesUtil;
use TycheSoftwares\PaymentGatewayFees\Lite\Admin_Page;
use TycheSoftwares\PaymentGatewayFees\Lite\Product_Metabox;
use TycheSoftwares\PaymentGatewayFees\Lite\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
final class Plugin {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '3.1.0';

	/**
	 * Core fee calculation object.
	 *
	 * @var Checkout_Fees|null
	 */
	public $core = null;

	/**
	 * Legacy settings objects.
	 *
	 * @var array|string
	 */
	public $settings = '';

	/**
	 * Meta-box settings object.
	 *
	 * @var mixed
	 */
	public $meta_box_settings = '';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin
	 */
	protected static $instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Initialize all hooks.
	 */
	private function init_hooks(): void {

		register_setting( 'options', 'pgbf_pro_allow_tracking', [
			'type'         => 'string',
			'default'      => '',
			'show_in_rest' => true,
		] );
		register_setting( 'options', 'ts_tracker_last_send', [
			'type'         => 'string',
			'default'      => '',
			'show_in_rest' => true,
		] );
		add_action( 'before_woocommerce_init', array( $this, 'pgbf_custom_order_tables_compatibility' ), 999 );
		register_deactivation_hook( PGBF_LITE_PLUGIN_FILE, array( $this, 'cf_deactivate' ) );
		add_filter( 'alg_wc_checkout_fees_option', array( $this, 'checkout_fees_option' ), 10, 3 );

		require_once PGBF_LITE_PLUGIN_PATH . '/includes/admin/class-settings.php';
		Settings::init();

		$this->core = $this->includes();

		// Load REST API file BEFORE calling its init method
		require_once PGBF_LITE_PLUGIN_PATH . '/includes/api/class-api.php';
		add_action( 'admin_init', array( 'TycheSoftwares\PaymentGatewayFees\Lite\Migration', 'run' ) );
		Api::init();
		require_once PGBF_LITE_PLUGIN_PATH . '/includes/admin/class-product-meta-migration.php';
		Product_Meta_Migration::init();

		$this->register_cache_flush_hooks();

		if ( is_admin() ) {
			$this->init_admin_hooks();
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
	}

	/**
	 * Register cache flush hooks.
	 */
	private function register_cache_flush_hooks(): void {
		$flush_events = array(
			'pgbf_pro_settings_saved',
			'pgbf_pro_gateway_saved',
			'pgbf_pro_gateway_section_reset',
			'pgbf_pro_section_reset',
			'pgbf_pro_all_data_deleted',
		);

		foreach ( $flush_events as $event ) {
			add_action( $event, array( Settings::class, 'flush_cache' ) );
		}
	}

	/**
	 * Initialize admin hooks.
	 */
	private function init_admin_hooks(): void {
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		add_filter( 'plugin_action_links_' . PGBF_LITE_PLUGIN_BASENAME, array( $this, 'action_links' ) );

		require_once PGBF_LITE_PLUGIN_PATH . '/includes/admin/class-admin-page.php';
		require_once PGBF_LITE_PLUGIN_PATH . '/includes/admin/class-product-metabox.php';
		require_once PGBF_LITE_PLUGIN_PATH . '/includes/admin/class-migration.php';

		new Admin_Page();
		new Product_Metabox();

		add_action( 'admin_init', array( $this, 'maybe_start_product_meta_migration' ) );
		add_action( 'admin_notices', array( $this, 'product_migration_admin_notice' ) );

		if ( get_option( 'alg_woocommerce_checkout_fees_version', '' ) !== $this->version ) {
			add_action( 'admin_init', array( $this, 'version_updated' ) );
		}

		add_action( 'alg_get_plugins_list', array( $this, 'cf_remove_plugin_name' ), PHP_INT_MAX );
	}

	/**
	 * Prevent cloning of the instance.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is not allowed.', 'checkout-fees-for-woocommerce' ), PGBF_LITE_PLUGIN_VERSION ); // phpcs:ignore
	}

	/**
	 * Prevent unserializing of the instance.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is not allowed.', 'checkout-fees-for-woocommerce' ), PGBF_LITE_PLUGIN_VERSION ); // phpcs:ignore
	}

	/**
	 * Load all plugin files.
	 *
	 * @return Checkout_Fees|null
	 */
	public function includes() {
		$files_class_path = PGBF_LITE_PLUGIN_PATH . '/includes/core/class-files.php';

		if ( ! file_exists( $files_class_path ) ) {
			$this->log_error( 'Critical: class-files.php not found' );
			return null;
		}

		require_once $files_class_path;

		if ( ! class_exists( 'TycheSoftwares\PaymentGatewayFees\Lite\Files' ) ) {
			$this->log_error( 'Critical: Files class not loaded' );
			return null;
		}

		return Files::load();
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( '[PGBF Pro] ' . $message, array( 'source' => 'pgbf-pro' ) );
		}
		error_log( 'PGBF Pro Error: ' . $message ); // phpcs:ignore
	}

	/**
	 * Define all PGBF_LITE_* constants.
	 */
	private function define_constants(): void {
		$constants = array(
			'PGBF_LITE_PLUGIN_VERSION'  => $this->version,
			'PGBF_LITE_PLUGIN_FILE'     => PGBF_LITE_BOOTSTRAP_FILE,
			'PGBF_LITE_PLUGIN_PATH'     => untrailingslashit( plugin_dir_path( PGBF_LITE_BOOTSTRAP_FILE ) ),
			'PGBF_LITE_PLUGIN_BASENAME' => plugin_basename( PGBF_LITE_BOOTSTRAP_FILE ),
			'PGBF_LITE_PLUGIN_URL'      => untrailingslashit( plugin_dir_url( PGBF_LITE_BOOTSTRAP_FILE ) ),
			'PGBF_LITE_STORE_URL'       => 'https://www.tychesoftwares.com/',
			'PGBF_LITE_ITEM_NAME'       => 'Payment Gateway Based Fees and Discounts for WooCommerce',
		);

		foreach ( $constants as $name => $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public function plugin_url(): string {
		return PGBF_LITE_PLUGIN_URL;
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public function plugin_path(): string {
		return PGBF_LITE_PLUGIN_PATH;
	}

	/**
	 * Check if WooCommerce version meets minimum requirement.
	 *
	 * @param string $min_version Minimum required version.
	 * @return bool
	 */
	public function is_wc_version_ge( string $min_version ): bool {
		if ( ! defined( 'WC_VERSION' ) ) {
			return false;
		}
		return version_compare( WC_VERSION, $min_version, '>=' );
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function enqueue_script(): void {
		wp_register_script(
			'tyche',
			PGBF_LITE_PLUGIN_URL . '/assets/js/tyche.js',
			array( 'jquery' ),
			$this->version,
			true
		);
		wp_enqueue_script( 'tyche' );

		wp_register_script(
			'checkout-fees-admin-js',
			PGBF_LITE_PLUGIN_URL . '/assets/js/checkout-fees-admin.js',
			array(),
			$this->version,
			true
		);
		wp_enqueue_script( 'checkout-fees-admin-js' );
	}

	/**
	 * Handle alg_wc_checkout_fees_option filter.
	 *
	 * @param mixed  $value Current value.
	 * @param string $type  Filter type.
	 * @param array  $args  Additional arguments.
	 * @return mixed
	 */
	public function checkout_fees_option( $value, $type, $args = array() ) {
		$args = is_array( $args ) ? $args : array();

		switch ( $type ) {
			case 'settings':
				return '';

			case 'per_product':
				return (bool) Settings::general( 'per_product_enabled', false );

			case 'countries':
				return $this->get_fee_setting( $args, 'countries', array() );

			case 'states':
				$states = $this->get_fee_setting( $args, 'states', '' );
				if ( is_string( $states ) ) {
					$states = array_filter( array_map( 'trim', explode( ',', $states ) ) );
				} else {
					$states = (array) $states;
				}

				return array_values( array_filter( array_map( function( $s ) {
					return str_contains( $s, ':' ) ? explode( ':', $s, 2 )[1] : $s;
				}, $states ) ) );

			case 'cats':
				return $this->get_fee_setting( $args, 'cats', array() );

			default:
				return $value;
		}
	}

	/**
	 * Get fee setting value.
	 *
	 * @param array  $args    Arguments.
	 * @param string $field   Field name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_fee_setting( array $args, string $field, $default = array() ) {
		$gid     = isset( $args['current_gateway'] ) ? sanitize_key( $args['current_gateway'] ) : '';
		$dir     = isset( $args['type'] ) ? sanitize_text_field( $args['type'] ) : 'include';
		$fee_num = isset( $args['fee_num'] ) ? sanitize_text_field( $args['fee_num'] ) : '';

		if ( empty( $fee_num ) ) {
			$section = 'general';
		} else {
			$section = ( 'fee_2_' === $fee_num ) ? 'fee_2' : 'fee_1';
		}

		return Settings::fee( $gid, $section, $field . '_' . $dir, $default );
	}

	/**
	 * Add action links on plugins page.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ): array {
		$custom_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=payment-gateway-fees-for-woocommerce' ) ) . '">' . esc_html__( 'Settings', 'checkout-fees-for-woocommerce' ) . '</a>',
			'<a href="https://www.tychesoftwares.com/products/woocommerce-payment-gateway-based-fees-and-discounts-plugin/?utm_source=pgbflite&utm_medium=notice&utm_campaign=upgrade">' . __( 'Unlock All', 'checkout-fees-for-woocommerce' ) . '</a>'
		);
		return array_merge( $custom_links, $links );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_plugin_textdomain(): void {
		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'checkout-fees-for-woocommerce' );

		unload_textdomain( 'checkout-fees-for-woocommerce' );
		load_textdomain( 'checkout-fees-for-woocommerce', WP_LANG_DIR . '/checkout-fees-for-woocommerce/checkout-fees-for-woocommerce-' . $locale . '.mo' );
		load_plugin_textdomain( 'checkout-fees-for-woocommerce', false, dirname( PGBF_LITE_PLUGIN_BASENAME ) . '/languages/' );
	}

	/**
	 * Handle version update.
	 */
	public function version_updated(): void {
		if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}

		foreach ( $this->settings as $section ) {
			if ( ! is_object( $section ) || ! method_exists( $section, 'get_settings' ) ) {
				continue;
			}

			$settings = $section->get_settings();
			if ( ! is_array( $settings ) ) {
				continue;
			}

			foreach ( $settings as $value ) {
				if ( isset( $value['default'], $value['id'] ) ) {
					$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
					add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}

		update_option( 'alg_woocommerce_checkout_fees_version', $this->version );
	}

	/**
	 * Remove plugin name from helper list.
	 */
	public function cf_remove_plugin_name(): void {
		$plugin_list = get_option( 'alg_wpcodefactory_helper_plugins', array() );

		if ( ! empty( $plugin_list ) && is_array( $plugin_list ) ) {
			$plugin_list = array_diff( $plugin_list, array( 'checkout-fees-for-woocommerce' ) );
			update_option( 'alg_wpcodefactory_helper_plugins', $plugin_list );
		}
	}

	/**
	 * Plugin deactivation handler.
	 */
	public function cf_deactivate(): void {
		if ( function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( 'ts_send_data_tracking_usage' ) ) {
			as_unschedule_action( 'ts_send_data_tracking_usage' );
		}
		do_action( 'cf_deactivate' );
	}

	/**
	 * Declare HPOS compatibility.
	 */
	public static function pgbf_custom_order_tables_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			FeaturesUtil::declare_compatibility( 'custom_order_tables', PGBF_LITE_BOOTSTRAP_FILE, true );
			FeaturesUtil::declare_compatibility( 'orders_cache', PGBF_LITE_BOOTSTRAP_FILE, true );
		}
	}

	/**
	 * Auto-start product meta migration if needed.
	 */
	public function maybe_start_product_meta_migration(): void {

		if ( ! class_exists( 'TycheSoftwares\PaymentGatewayFees\Lite\Product_Meta_Migration' ) ) {
			return;
		}

		$status = get_option( Product_Meta_Migration::STATUS_OPTION, 'none' );
		if ( in_array( $status, array( 'none', '' ), true ) ) {
			Product_Meta_Migration::start();
		}
	}

	/**
	 * Display admin notice for product migration.
	 */
	public function product_migration_admin_notice(): void {
		if ( ! $this->should_show_migration_notice() ) {
			return;
		}

		$status_data = Product_Meta_Migration::get_status();

		if ( 'complete' === $status_data['status'] ) {
			return;
		}

		$notice_class = ( 'partial' === $status_data['status'] ) ? 'notice-warning' : 'notice-info';
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p><strong><?php esc_html_e( 'Payment Gateway Fees — Product Data Migration', 'checkout-fees-for-woocommerce' ); ?></strong></p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %1$d: done count, %2$d: total count, %3$d: percent, %4$d: pending */
						__( 'Migrating product fee data: %1$d of %2$d done (%3$d%%), %4$d pending.', 'checkout-fees-for-woocommerce' ),
						$status_data['done'],
						$status_data['total'],
						$status_data['percent'],
						$status_data['pending']
					)
				);
				?>
			</p>
			<div class="pgbf-migration-progress">
				<div class="pgbf-migration-progress-bar" style="width: <?php echo esc_attr( $status_data['percent'] ); ?>%;"></div>
			</div>
			<?php if ( 'partial' === $status_data['status'] ) : ?>
				<p><?php esc_html_e( 'Some products failed — original data preserved. Check WooCommerce → Status → Logs (channel: pgbf-migration).', 'checkout-fees-for-woocommerce' ); ?></p>
			<?php endif; ?>
		</div>
		<style>
			.pgbf-migration-progress {
				background: #e0e0e0;
				border-radius: 4px;
				height: 8px;
				width: 100%;
				max-width: 400px;
				overflow: hidden;
				margin: 6px 0;
			}
			.pgbf-migration-progress-bar {
				background: #2271b1;
				height: 100%;
				border-radius: 4px;
				transition: width .5s;
			}
		</style>
		<?php
	}

	/**
	 * Check if migration notice should be shown.
	 *
	 * @return bool
	 */
	private function should_show_migration_notice(): bool {
		if ( ! is_admin() || ! class_exists( 'Product_Meta_Migration' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( $screen && ! in_array( $screen->id, array( 'dashboard', 'woocommerce_page_wc-settings', 'product', 'edit-product' ), true ) ) {
			return false;
		}

		return Product_Meta_Migration::should_show_notice();
	}
}

// Backward-compat alias.
if ( ! class_exists( 'Alg_Woocommerce_Checkout_Fees' ) ) {
	class_alias( __NAMESPACE__ . '\\Plugin', 'Alg_Woocommerce_Checkout_Fees' );
}