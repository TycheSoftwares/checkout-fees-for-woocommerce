<?php
/**
 * Checkout Fees for WooCommerce
 *
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce-pro/checkout
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
if ( ! class_exists( 'Alg_WC_All_Component' ) ) {
	/**
	 * It will Add all the Boilerplate component when we activate the plugin.
	 * 
	 */
	class Alg_WC_All_Component {
	    
		/**
		 * It will Add all the Boilerplate component when we activate the plugin.
		 */
		public function __construct() {

			$is_admin = is_admin();

			if ( true === $is_admin ) {

                $pgbf_plugin_name          = self::ts_get_plugin_name();;
                $pgbf_locale               = self::ts_get_plugin_locale();
                $plugin_url                = plugins_url() . '/checkout-fees-for-woocommerce';
                $pgbf_file_name            = 'checkout-fees-for-woocommerce/checkout-fees-for-woocommerce.php';
                $pgbf_plugin_prefix        = 'pgbf_lite';
                
                $pgbf_blog_post_link    = 'https://www.tychesoftwares.com/docs/docs/payment-gateway-based-fees-and-discounts-for-woocommerce/payment-gateway-based-fees-and-discounts-usage-tracking/';
                $pgbf_get_previous_version = get_option( 'alg_woocommerce_checkout_fees_version' );

                if ( strpos( $_SERVER['REQUEST_URI'], 'plugins.php' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'action=deactivate' ) !== false || ( strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) !== false && isset( $_POST['action'] ) && $_POST['action'] === 'tyche_plugin_deactivation_submit_action' ) ) { //phpcs:ignore
                    require_once( "component/plugin-deactivation/class-tyche-plugin-deactivation.php" );
                    new Tyche_Plugin_Deactivation(
                        array(
                            'plugin_name'       => $pgbf_plugin_name,
                            'plugin_base'       => $pgbf_file_name,
                            'script_file'       => $plugin_url . '/includes/js/plugin-deactivation.js',
                            'plugin_short_name' => $pgbf_plugin_prefix,
                            'version'           => $pgbf_get_previous_version,
                            'plugin_locale'     => $pgbf_locale,
                        )
                    );
                }

                require_once( "component/plugin-tracking/class-tyche-plugin-tracking.php" );
                new Tyche_Plugin_Tracking(
                    array(
                        'plugin_name'       => $pgbf_plugin_name,
                        'plugin_locale'     => $pgbf_locale,
                        'plugin_short_name' => $pgbf_plugin_prefix,
                        'version'           => $pgbf_get_previous_version,
                        'blog_link'         => $pgbf_blog_post_link,
                    )
                );
            }
        }
        
        /**
         * It will retrun the plguin name.
         * @return string $ts_plugin_name Name of the plugin
         */
		public static function ts_get_plugin_name () {
            $pgbf_plugin_dir =  dirname ( dirname ( __FILE__ ) );
            $pgbf_plugin_dir .= '/checkout-fees-for-woocommerce.php';

            $ts_plugin_name = '';
            $plugin_data = get_file_data( $pgbf_plugin_dir, array( 'name' => 'Plugin Name' ) );
            if ( ! empty( $plugin_data['name'] ) ) {
                $ts_plugin_name = $plugin_data[ 'name' ];
            }
            return $ts_plugin_name;
        }

        /**
         * It will retrun the Plugin text Domain
         * @return string $ts_plugin_domain Name of the Plugin domain
         */
        public static function ts_get_plugin_locale () {
            $pgbf_plugin_dir =  dirname ( dirname ( __FILE__ ) );
            $pgbf_plugin_dir .= '/checkout-fees-for-woocommerce.php';

            $ts_plugin_domain = '';
            $plugin_data = get_file_data( $pgbf_plugin_dir, array( 'domain' => 'Text Domain' ) );
            if ( ! empty( $plugin_data['domain'] ) ) {
                $ts_plugin_domain = $plugin_data[ 'domain' ];
            }
            return $ts_plugin_domain;
        }
	}
	$Alg_WC_All_Component = new Alg_WC_All_Component();
}
