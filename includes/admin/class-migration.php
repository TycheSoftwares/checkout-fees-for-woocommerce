<?php
/**
 * Class Migration
 *
 * Migrates all settings from the old WooCommerce Settings API format
 * (individual wp_options rows) into the new unified JSON structure.
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * Class Migration
 *
 * Handles migration of plugin settings from old format to new unified format.
 */
class Migration {

	/**
	 * New unified settings option key.
	 *
	 * @var string
	 */
	const NEW_OPTION_KEY = 'pgbf_pro_settings';

	/**
	 * New unified gateway settings option key.
	 *
	 * @var string
	 */
	const GATEWAY_OPTION_KEY = 'pgbf_pro_gateway_settings';

	/**
	 * Migration version stored to prevent re-running.
	 *
	 * @var string
	 */
	const MIGRATION_FLAG = 'pgbf_pro_migration_version';

	/**
	 * Current migration version.
	 *
	 * @var string
	 */
	const MIGRATION_VERSION = '1.0.0';

	/**
	 * Run migration. Called from the plugin's activation hook and on
	 * admin_init (for existing installs that update without re-activating).
	 * Safe to call multiple times — exits immediately if already run.
	 */
	public static function run(): void {
		// Already migrated — do nothing.
		if ( get_option( self::MIGRATION_FLAG ) === self::MIGRATION_VERSION ) {
			return;
		}

		// If new settings already exist — do not overwrite, just stamp the migration flag.
		if ( false !== get_option( self::NEW_OPTION_KEY ) ) {
			update_option( self::MIGRATION_FLAG, self::MIGRATION_VERSION );
			return;
		}

		// Probe for any old data to determine install type.
		$has_old_data = false !== get_option( 'alg_woocommerce_checkout_fees_enabled' );

		if ( ! $has_old_data ) {
			// Fresh install: seed defaults.
			self::seed_defaults();
		} else {
			// Existing install: migrate all old options.
			self::migrate();
		}

		update_option( self::MIGRATION_FLAG, self::MIGRATION_VERSION );
	}

	/**
	 * Read all old individual options and write them into the new unified keys.
	 */
	private static function migrate(): void {
		$new_settings = array(
			'general'          => self::migrate_general(),
			'global_extra_fee' => self::migrate_global_extra_fee(),
			'info'             => self::migrate_info(),
			'bin_apis'         => self::migrate_bin_apis(),
		);

		update_option( self::NEW_OPTION_KEY, $new_settings, false );

		// Migrate per-gateway settings separately.
		self::migrate_gateway_settings();

		do_action( 'pgbf_pro_migration_complete', $new_settings );
	}

	/**
	 * Migrate General settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function migrate_general(): array {
		return array(
			'enabled'              => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_enabled', true ),
			'per_product_enabled'  => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_per_product_enabled', false ),
			'per_product_add_name' => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_per_product_add_product_name', false ),
			'merge_all_fees'       => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_merge_all_fees', false ),
			'max_total_discount'   => (float) get_option( 'alg_woocommerce_checkout_fees_range_max_total_discounts', 0 ),
			'max_total_fee'        => (float) get_option( 'alg_woocommerce_checkout_fees_range_max_total_fees', 0 ),
			'hide_on_cart'         => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_hide_on_cart', false ),
		);
	}

	/**
	 * Migrate Global Extra Fee settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function migrate_global_extra_fee(): array {
		return array(
			'enabled'          => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_global_fee_enabled', false ),
			'as_extra_only'    => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_global_fee_as_extra_enabled', false ),
			'gateways_exclude' => (array) get_option( 'alg_woocommerce_checkout_fees_global_fee_gateways_excl', array() ),
			'title'            => (string) get_option( 'alg_woocommerce_checkout_fees_global_fee_title', '' ),
			'type'             => (string) get_option( 'alg_woocommerce_checkout_fees_global_fee_type', 'fixed' ),
			'value'            => (float) get_option( 'alg_woocommerce_checkout_fees_global_fee_value', 0 ),
			'min_cart_amount'  => (float) get_option( 'alg_woocommerce_checkout_fees_golbal_min_fees', 0 ),
			'max_cart_amount'  => (float) get_option( 'alg_woocommerce_checkout_fees_golbal_max_fees', 0 ),
		);
	}

	/**
	 * Migrate Info settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function migrate_info(): array {
		return array(
			'product_page'          => array(
				'enabled'    => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_info_enabled', false ),
				'start_html' => (string) get_option( 'alg_woocommerce_checkout_fees_info_start_template', '<tr>' ),
				'row_html'   => (string) get_option(
					'alg_woocommerce_checkout_fees_info_row_template',
					'</table><tr><strong>%gateway_title%</strong></td><td>%product_original_price%</td><td>%product_gateway_price%</td><td>%product_price_diff%</td></tr>'
				),
				'end_html'   => (string) get_option( 'alg_woocommerce_checkout_fees_info_end_template', '</table>' ),
				'position'   => (string) get_option( 'alg_woocommerce_checkout_fees_info_hook', 'woocommerce_single_product_summary' ),
				'priority'   => (int) get_option( 'alg_woocommerce_checkout_fees_info_hook_priority', 20 ),
			),
			'lowest_price'          => array(
				'enabled'       => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_lowest_price_info_enabled', false ),
				'template_html' => (string) get_option(
					'alg_woocommerce_checkout_fees_lowest_price_info_template',
					'<p><strong>%gateway_title%</strong> %product_gateway_price% (%product_price_diff%)</p>'
				),
				'position'      => (string) get_option( 'alg_woocommerce_checkout_fees_lowest_price_info_hook', 'woocommerce_single_product_summary' ),
				'priority'      => (int) get_option( 'alg_woocommerce_checkout_fees_lowest_price_info_hook_priority', 20 ),
			),
			'hide_on_out_of_stock'  => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_hide_info_on_out_of_stock', false ),
			'variable_info_display' => (string) get_option( 'alg_woocommerce_checkout_fees_variable_info', 'for_each_variation' ),
		);
	}

	/**
	 * Migrate BIN APIs settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function migrate_bin_apis(): array {
		return array(
			'enabled'      => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_bin_apis_enabled', false ),
			'provider'     => (string) get_option( 'alg_woocommerce_checkout_fees_bin_api_provider', 'binlist' ),
			'binlist'      => array(
				'cache_enabled'        => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_binlist_cache_enabled', true ),
				'cache_duration_hours' => (int) get_option( 'alg_woocommerce_checkout_fees_binlist_cache_duration', 24 ),
			),
			'neutrinoapi'  => array(
				'user_id' => (string) get_option( 'alg_woocommerce_checkout_fees_neutrinoapi_user_id', '' ),
				'api_key' => (string) get_option( 'alg_woocommerce_checkout_fees_neutrinoapi_api_key', '' ),
			),
			'card_section' => array(
				'enabled'        => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_card_section_enabled', false ),
				'show_type'      => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_card_type', false ),
				'show_scheme'    => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_card_scheme', false ),
				'show_location'  => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_card_location', false ),
				'show_bank_name' => self::get_yes_no_option( 'alg_woocommerce_checkout_fees_card_bank_name', false ),
			),
		);
	}

	/**
	 * Migrate per-gateway settings into pgbf_pro_gateway_settings.
	 */
	private static function migrate_gateway_settings(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return;
		}

		$gateway_settings = array();

		foreach ( WC()->payment_gateways->payment_gateways() as $gateway_id => $gateway ) {
			$gid                              = sanitize_key( $gateway_id );
			$gateway_settings[ $gid ]         = self::migrate_single_gateway( $gid );
		}

		update_option( self::GATEWAY_OPTION_KEY, $gateway_settings, false );
	}

	/**
	 * Build the migrated settings object for a single gateway.
	 *
	 * @param string $gid Gateway ID (e.g. 'cod', 'bacs').
	 * @return array<string, mixed>
	 */
	private static function migrate_single_gateway( string $gid ): array {
		return array(
			'fee_1'      => array(
				'enabled'          => self::get_yes_no_option( "alg_gateways_fees_enabled_{$gid}", false ),
				'title'            => (string) get_option( "alg_gateways_fees_text_{$gid}", '' ),
				'type'             => (string) get_option( "alg_gateways_fees_type_{$gid}", 'fixed' ),
				'value'            => (float) get_option( "alg_gateways_fees_value_{$gid}", 0 ),
				'min_fee'          => (float) get_option( "alg_gateways_fees_min_fee_{$gid}", 0 ),
				'max_fee'          => (float) get_option( "alg_gateways_fees_max_fee_{$gid}", 0 ),
				'coupons_rule'     => (string) get_option( "alg_gateways_fees_coupons_rule_{$gid}", 'disabled' ),
				'countries_include' => (array) get_option( "alg_gateways_fees_countries_include_fee_1_{$gid}", array() ),
				'countries_exclude' => (array) get_option( "alg_gateways_fees_countries_exclude_fee_1_{$gid}", array() ),
				'states_include'    => get_option( "alg_gateways_fees_states_include_fee_1_{$gid}", '' ),
				'states_exclude'    => get_option( "alg_gateways_fees_states_exclude_fee_1_{$gid}", '' ),
				'cats_include'      => (array) get_option( "alg_gateways_fees_cats_include_{$gid}", array() ),
				'cats_exclude'      => (array) get_option( "alg_gateways_fees_cats_exclude_{$gid}", array() ),
				'shipping_include'  => (array) get_option( "alg_gateways_fees_shipping_methods_include_fee_1_{$gid}", array() ),
				'shipping_exclude'  => (array) get_option( "alg_gateways_fees_shipping_methods_exclude_fee_1_{$gid}", array() ),
			),
			'fee_2'      => array(
				'enabled'           => false,
				'title'             => (string) get_option( "alg_gateways_fees_text_2_{$gid}", '' ),
				'type'              => (string) get_option( "alg_gateways_fees_type_2_{$gid}", 'fixed' ),
				'value'             => (float) get_option( "alg_gateways_fees_value_2_{$gid}", 0 ),
				'min_fee'           => (float) get_option( "alg_gateways_fees_min_fee_2_{$gid}", 0 ),
				'max_fee'           => (float) get_option( "alg_gateways_fees_max_fee_2_{$gid}", 0 ),
				'coupons_rule'      => (string) get_option( "alg_gateways_fees_coupons_rule_2_{$gid}", 'disabled' ),
				'countries_include' => (array) get_option( "alg_gateways_fees_countries_include_fee_2_{$gid}", array() ),
				'countries_exclude' => (array) get_option( "alg_gateways_fees_countries_exclude_fee_2_{$gid}", array() ),
				'states_include'    => get_option( "alg_gateways_fees_states_include_fee_2_{$gid}", '' ),
				'states_exclude'    => get_option( "alg_gateways_fees_states_exclude_fee_2_{$gid}", '' ),
				'cats_include'      => (array) get_option( "alg_gateways_fees_cats_include_fee_2_{$gid}", array() ),
				'cats_exclude'      => (array) get_option( "alg_gateways_fees_cats_exclude_fee_2_{$gid}", array() ),
				'shipping_include'  => (array) get_option( "alg_gateways_fees_shipping_methods_include_fee_2_{$gid}", array() ),
				'shipping_exclude'  => (array) get_option( "alg_gateways_fees_shipping_methods_exclude_fee_2_{$gid}", array() ),
			),
			'general'    => array(
				'min_cart_amount'        => (float) get_option( "alg_gateways_fees_min_cart_amount_{$gid}", 0 ),
				'max_cart_amount'        => (float) get_option( "alg_gateways_fees_max_cart_amount_{$gid}", 0 ),
				'rounding_enabled'       => self::get_yes_no_option( "alg_gateways_fees_round_{$gid}", false ),
				'rounding_precision'     => (int) get_option( "alg_gateways_fees_round_precision_{$gid}", 0 ),
				'tax_enabled'            => self::get_yes_no_option( "alg_gateways_fees_is_taxable_{$gid}", false ),
				'tax_class'              => (string) get_option( "alg_gateways_fees_tax_class_id_{$gid}", '' ),
				'exclude_shipping'       => self::get_yes_no_option( "alg_gateways_fees_exclude_shipping_{$gid}", false ),
				'add_taxes'              => self::get_yes_no_option( "alg_gateways_fees_add_taxes_{$gid}", false ),
				'countries_include'      => (array) get_option( "alg_gateways_fees_countries_include_{$gid}", array() ),
				'countries_exclude'      => (array) get_option( "alg_gateways_fees_countries_exclude_{$gid}", array() ),
				'cats_include_calc_type' => (string) get_option( "alg_gateways_fees_cats_include_calc_type_{$gid}", '' ),
				'cats_exclude_calc_type' => (string) get_option( "alg_gateways_fees_cats_exclude_calc_type_{$gid}", '' ),
			),
			'card_rules' => array(
				'enabled'                   => self::get_yes_no_option( "alg_wc_checkout_fees_{$gid}_enable_card_rules", false ),
				'show_card_payment_display' => self::get_yes_no_option( "alg_wc_checkout_fees_{$gid}_enable_card_payment_display", false ),
				'rules'                     => (array) get_option( "alg_wc_checkout_fees_{$gid}_card_rules", array() ),
			),
		);
	}

	/**
	 * Get boolean value from yes/no option.
	 *
	 * @param string $option_key Option key.
	 * @param bool   $default    Default value.
	 * @return bool
	 */
	private static function get_yes_no_option( string $option_key, bool $default = false ): bool {
		$value = get_option( $option_key, $default ? 'yes' : 'no' );
		return 'yes' === $value;
	}

	/**
	 * Migrate a states option (comma-separated string in old format) to an array.
	 *
	 * @param string $option_key Option key.
	 * @return array<string>
	 */
	private static function migrate_states( string $option_key ): array {
		$value = get_option( $option_key, '' );

		$raw = is_array( $value )
			? array_filter( array_map( 'trim', $value ) )
			: array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );

		if ( empty( $raw ) ) {
			return [];
		}

		if ( str_contains( reset( $raw ), ':' ) ) {
			return array_values( $raw );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return array_values( $raw );
		}

		$all_states = WC()->countries->get_states();
		$resolved   = [];

		foreach ( $raw as $bare_code ) {
			$found = false;
			foreach ( $all_states as $country_code => $states ) {
				if ( isset( $states[ $bare_code ] ) ) {
					$resolved[] = $country_code . ':' . $bare_code;
					$found = true;
					break;
				}
			}

			if ( ! $found ) {
				$resolved[] = $bare_code;
			}
		}

		return $resolved;
	}

	/**
	 * Seed default settings for a fresh install.
	 */
	private static function seed_defaults(): void {
		$base_file       = PGBF_LITE_PLUGIN_PATH . '/includes/api/class-api-base.php';
		$controller_file = PGBF_LITE_PLUGIN_PATH . '/includes/api/class-api-settings.php';

		if ( file_exists( $base_file ) && ! class_exists( 'Api_Base' ) ) {
			require_once $base_file;
		}

		if ( ! file_exists( $controller_file ) ) {
			return;
		}

		if ( ! class_exists( 'Api_Settings' ) ) {
			require_once $controller_file;
		}

		$controller = new Api_Settings();
		add_option( self::NEW_OPTION_KEY, $controller->get_defaults(), '', false );

		// Seed empty gateway settings object — will be populated per gateway on first use.
		add_option( self::GATEWAY_OPTION_KEY, array(), '', false );
	}

	/**
	 * Read a value from the new unified settings, falling back to a default.
	 *
	 * @param string $section Top-level section key.
	 * @param string $key     Field key within the section.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $section, string $key, $default = null ) {
		$settings = get_option( self::NEW_OPTION_KEY, null );

		if ( $settings && isset( $settings[ $section ][ $key ] ) ) {
			return $settings[ $section ][ $key ];
		}

		return $default;
	}
}