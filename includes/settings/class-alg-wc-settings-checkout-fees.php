<?php
/**
 * Checkout Fees for WooCommerce - Settings
 *
 * @version 2.5.0
 * @since   1.0.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce/settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Settings_Checkout_Fees' ) ) :

	/**
	 * Add a settings tab on WooCommerce settings page.
	 */
	class Alg_WC_Settings_Checkout_Fees extends WC_Settings_Page {
		/**
		 * ID
		 *
		 * @var $id
		 * @since 2.1.1
		 */
		public $id = '';
		/**
		 * Label
		 *
		 * @var $label
		 * @since 2.1.1
		 */
		public $label = '';
		/**
		 * Constructor.
		 *
		 * @version 2.5.0
		 */
		public function __construct() {

			$this->id    = 'alg_checkout_fees';
			$this->label = __( 'Payment Gateway Based Fees and Discounts', 'checkout-fees-for-woocommerce' );

			parent::__construct();

			add_action( 'admin_init', array( $this, 'maybe_reset_settings' ), PHP_INT_MAX );
			add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_unclean_option' ), PHP_INT_MAX, 3 );
			add_action( 'woocommerce_admin_field_alg_woocommerce_checkout_fees_custom_link', array( $this, 'output_custom_link' ) );
			add_action( 'woocommerce_admin_field_link', array( $this, 'add_admin_field_reset_button' ) );
		}

		/**
		 * Get_settings.
		 *
		 * @version 2.5.0
		 */
		public function get_settings() {
			global $current_section;
			return array_merge(
				apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() ),
				array(
					array(
						'title' => __( 'Reset Settings', 'checkout-fees-for-woocommerce' ),
						'type'  => 'title',
						'id'    => 'alg_woocommerce_checkout_fees_' . $current_section . '_reset_options',
					),
					array(
						'title'   => __( 'Reset section settings', 'checkout-fees-for-woocommerce' ),
						'desc'    => '<strong>' . __( 'Reset', 'checkout-fees-for-woocommerce' ) . '</strong>',
						'id'      => 'alg_woocommerce_checkout_fees_' . $current_section . '_reset',
						'default' => 'no',
						'type'    => 'checkbox',
					),
					array(
						'name'        => __( 'Reset usage tracking', 'checkout-fees-for-woocommerce' ),
						'type'        => 'link',
						'desc'        => __( 'This will reset your usage tracking settings, causing it to show the opt-in banner again and not sending any data', 'checkout-fees-for-woocommerce' ),
						'button_text' => 'Reset',
						'desc_tip'    => true,
						'class'       => 'button-secondary reset_tracking',
						'id'          => 'ts_reset_tracking',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'alg_woocommerce_checkout_fees_' . $current_section . '_reset_options',
					),
				)
			);
		}

		/**
		 * Maybe_reset_settings.
		 *
		 * @version 2.5.0
		 * @since   2.5.0
		 */
		public function maybe_reset_settings() {
			global $current_section;
			if ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_' . $current_section . '_reset', 'no' ) ) {
				foreach ( $this->get_settings() as $value ) {
					if ( isset( $value['id'] ) ) {
						if ( isset( $value['id'] ) ) {
							$option_id = $value['id'];
							if ( false !== strpos( $option_id, '[' ) ) {
								$option_id = explode( '[', $option_id )[0];
							}
							delete_option( $option_id );
						}
					}
				}
			}
		}

		/**
		 * Maybe_unclean_option.
		 *
		 * @param mixed $value sanitized value.
		 * @param array $option Options array to output.
		 * @param mixed $raw_value Entered or unsanitized value.
		 * @version 2.5.0
		 * @since   2.5.0
		 */
		public function maybe_unclean_option( $value, $option, $raw_value ) {
			return ( isset( $option['alg_woocommerce_checkout_fees_raw'] ) && $option['alg_woocommerce_checkout_fees_raw'] ? $raw_value : $value );
		}

		/**
		 * Output_custom_link.
		 *
		 * @param array $value Custom Field value.
		 * @version 2.2.2
		 * @since   2.2.2
		 */
		public function output_custom_link( $value ) {
			$tooltip_html = ( isset( $value['desc_tip'] ) && '' !== $value['desc_tip'] ) ?
			'<span class="woocommerce-help-tip" data-tip="' . $value['desc_tip'] . '"></span>' : '';
			?><tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label><?php echo $tooltip_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</th>
			<td class="forminp forminp-<?php echo sanitize_title( $value['type'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"> 
				<?php echo $value['link']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> 
			</td>
		</tr>
			<?php
		}


		/**
		 * Reset the usage tracking settings.
		 *
		 * @param array $value Settings value.
		 */
		public static function add_admin_field_reset_button( $value ) {
			if ( 'ts_reset_tracking' === $value['id'] ) {
				$description = WC_Admin_Settings::get_field_description( $value );
				$nonce       = wp_create_nonce( 'ts_nonce_action' );
				$ts_action   = 'admin.php?page=wc-settings&tab=alg_checkout_fees&ts_action=reset_tracking&nonce=' . $nonce;
				?>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
						<?php echo $description['tooltip_html']; // phpcs:ignore ?>
					</th>
					<td class="forminp forminp-<?php echo esc_attr( sanitize_title( $value['type'] ) ); ?>">
						<a  href = "<?php echo esc_url( $ts_action ); ?>"
							name ="ts_reset_tracking"
							id   ="ts_reset_tracking"
							style="<?php echo esc_attr( $value['css'] ); ?>"
							class="<?php echo esc_attr( $value['class'] ); ?>"
						> <?php echo esc_html( $value['button_text'] ); ?> </a> <?php echo $description['description']; // phpcs:ignore ?>
					</td>
				</tr>
				<?php
			}
		}
	}

endif;

return new Alg_WC_Settings_Checkout_Fees();
