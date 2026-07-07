<?php
/**
 * Checkout Fees Info
 * Replaces class-alg-wc-checkout-fees-info.php
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TycheSoftwares\PaymentGatewayFees\Lite\Settings;
use TycheSoftwares\PaymentGatewayFees\Lite\Product_Fees_Helper;

/**
 * Display fee info on single product pages.
 */
class Checkout_Fees_Info {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$info = get_option( 'pgbf_pro_settings', array() );
		$pp   = isset( $info['info']['product_page'] ) ? $info['info']['product_page'] : array();
		$lp   = isset( $info['info']['lowest_price'] ) ? $info['info']['lowest_price'] : array();

		if ( ! empty( $pp['enabled'] ) ) {
			add_action(
				isset( $pp['position'] ) ? $pp['position'] : 'woocommerce_single_product_summary',
				array( $this, 'show_checkout_fees_full_info' ),
				(int) ( isset( $pp['priority'] ) ? $pp['priority'] : 20 )
			);
		}

		if ( ! empty( $lp['enabled'] ) ) {
			add_action(
				isset( $lp['position'] ) ? $lp['position'] : 'woocommerce_single_product_summary',
				array( $this, 'show_checkout_fees_full_lowest_price_info' ),
				(int) ( isset( $lp['priority'] ) ? $lp['priority'] : 20 )
			);
		}

		add_shortcode( 'alg_show_checkout_fees_full_info', array( $this, 'get_checkout_fees_full_info' ) );
		add_shortcode( 'alg_show_checkout_fees_lowest_price_info', array( $this, 'get_checkout_fees_lowest_price_info' ) );
	}

	/**
	 * Show lowest price info.
	 */
	public function show_checkout_fees_full_lowest_price_info() {
		global $post;
		$product  = wc_get_product( $post->ID );
		$hide_oos = Settings::info( 'hide_on_out_of_stock', false );

		if ( $hide_oos ) {
			if ( $product->is_in_stock() ) {
				echo wp_kses_post( $this->get_checkout_fees_info( true ) );
			}
		} else {
			echo wp_kses_post( $this->get_checkout_fees_info( true ) );
		}
	}

	/**
	 * Show full checkout fees info.
	 */
	public function show_checkout_fees_full_info() {
		global $post;
		$product  = wc_get_product( $post->ID );
		$hide_oos = Settings::info( 'hide_on_out_of_stock', false );

		if ( $hide_oos ) {
			if ( $product->is_in_stock() ) {
				echo wp_kses_post( $this->get_checkout_fees_info( false ) );
			}
		} else {
			echo wp_kses_post( $this->get_checkout_fees_info( false ) );
		}
	}

	/**
	 * Get lowest price info.
	 *
	 * @return string
	 */
	public function get_checkout_fees_lowest_price_info() {
		return $this->get_checkout_fees_info( true );
	}

	/**
	 * Get full checkout fees info.
	 *
	 * @return string
	 */
	public function get_checkout_fees_full_info() {
		return $this->get_checkout_fees_info( false );
	}

	/**
	 * Core info renderer.
	 *
	 * @param bool $lowest_price_only Whether to show only lowest price.
	 * @return string
	 */
	public function get_checkout_fees_info( $lowest_price_only ) {
		$product_id  = get_the_ID();
		$the_product = wc_get_product( $product_id );

		if ( ! $the_product ) {
			return '';
		}

		$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );

		// Build products array.
		$products_array = array();
		if ( $the_product->is_type( 'variable' ) ) {
			foreach ( $the_product->get_available_variations() as $product_variation ) {
				$variation_product = wc_get_product( $product_variation['variation_id'] );
				$products_array[]  = array(
					'variation_atts' => wc_get_formatted_variation( $variation_product, true ),
					'price_excl_tax' => wc_get_price_excluding_tax( $variation_product ),
					'price_incl_tax' => wc_get_price_including_tax( $variation_product ),
					'display_price'  => wc_get_price_to_display( $variation_product ),
				);
			}
		} else {
			$products_array = array(
				array(
					'variation_atts' => '',
					'price_excl_tax' => wc_get_price_excluding_tax( $the_product ),
					'price_incl_tax' => wc_get_price_including_tax( $the_product ),
					'display_price'  => wc_get_price_to_display( $the_product ),
				),
			);
		}

		$gateways_data      = array();
		$lowest_price_array = array();

		foreach ( $products_array as $product_data ) {
			$the_variation_atts = $product_data['variation_atts'];
			$the_price_excl_tax = $product_data['price_excl_tax'];
			$the_price_incl_tax = $product_data['price_incl_tax'];
			$the_display_price  = $product_data['display_price'];

			$single_product_gateways_data = array();
			$lowest_price                 = PHP_INT_MAX;
			$lowest_price_gateway         = '';

			$wc_available_gateways = WC()->payment_gateways->get_available_payment_gateways();
			$available_gateways    = array();

			foreach ( $wc_available_gateways as $gid => $wc_gw ) {
				$globally_fees_enabled = Settings::fee( $gid, 'fee_1', 'enabled', false ) ? 'yes' : 'no';
				$per_product_fees      = Product_Fees_Helper::get_field( $product_id, $gid, 'root', 'enabled', 'no' );

				if ( is_bool( $per_product_fees ) ) {
					$per_product_fees = $per_product_fees ? 'yes' : 'no';
				}

				if ( 'yes' === $globally_fees_enabled || 'yes' === $per_product_fees ) {
					$available_gateways[ $gid ] = $wc_gw;
				}
			}

			foreach ( $available_gateways as $gid => $available_gateway ) {
				pgbf_lite()->core->get_max_ranges();

				$global_fee = 0;
				if ( pgbf_lite()->core->check_countries( $gid ) ) {
					$args       = pgbf_lite()->core->args_manager->get_the_args_global( $gid );
					$global_fee = pgbf_lite()->core->get_the_fee( $args, 'fee_both', $the_price_excl_tax, true, $product_id );
				}

				$local_fee = 0;
				if ( Settings::general( 'per_product_enabled', false ) &&
					( 'bacs' === $gid || apply_filters( 'alg_wc_checkout_fees_option', false, 'per_product' ) ) ) {
					$args      = pgbf_lite()->core->args_manager->get_the_args_local( $gid, $product_id, 0, 1 );
					$local_fee = pgbf_lite()->core->get_the_fee( $args, 'fee_both', $the_price_excl_tax, true, $product_id );
				}

				if ( 'incl' === $tax_display_mode ) {
					$the_price = $the_price_incl_tax;

					if ( 0 != $global_fee ) { // phpcs:ignore
						if ( Settings::fee( $gid, 'general', 'tax_enabled', false ) ) {
							$tax_class_names = array_merge( array( '' ), WC_Tax::get_tax_classes() );
							$tax_class_name  = Settings::fee( $gid, 'general', 'tax_class', 0 );
							$tax_class_name  = isset( $tax_class_names[ $tax_class_name ] ) ? $tax_class_names[ $tax_class_name ] : '';
							$fee_taxes       = WC_Tax::calc_tax( $global_fee, WC_Tax::get_rates( $tax_class_name ), false );

							if ( ! empty( $fee_taxes ) ) {
								$global_fee += array_sum( $fee_taxes );
							}
						}
						$the_price += $global_fee;
					}

					if ( 0 != $local_fee ) { // phpcs:ignore
						if ( Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'tax_enabled', false ) ) {
							$tax_class_names = array_merge( array( '' ), WC_Tax::get_tax_classes() );
							$tax_class_name  = Product_Fees_Helper::get_field( $product_id, $gid, 'general', 'tax_class', '' );
							$tax_class_name  = isset( $tax_class_names[ $tax_class_name ] ) ? $tax_class_names[ $tax_class_name ] : '';
							$fee_taxes       = WC_Tax::calc_tax( $local_fee, WC_Tax::get_rates( $tax_class_name ), false );

							if ( ! empty( $fee_taxes ) ) {
								$local_fee += array_sum( $fee_taxes );
							}
						}
						$the_price += $local_fee;
					}

					$price_diff         = (float) ( $the_price - $the_price_incl_tax );
					$price_diff_percent = ( 0 != $the_price_incl_tax ? round( ( $price_diff / $the_price_incl_tax ) * 100, 0 ) : 0 ); // phpcs:ignore
				} else {
					$the_price          = $the_price_excl_tax;
					$the_price         += $global_fee;
					$the_price         += $local_fee;
					$price_diff         = ( $the_price - $the_price_excl_tax );
					$price_diff_percent = ( 0 != $the_price_excl_tax ? round( ( $price_diff / $the_price_excl_tax ) * 100, 0 ) : 0 ); // phpcs:ignore
				}

				if ( false === $lowest_price_only ) {
					$single_product_gateways_data[ $gid ] = array(
						'gateway_title'              => $available_gateway->title,
						'gateway_description'        => $available_gateway->get_description(),
						'gateway_icon'               => $available_gateway->get_icon(),
						'product_gateway_price'      => $the_price,
						'product_original_price'     => $the_display_price,
						'product_price_diff'         => $price_diff,
						'product_price_diff_percent' => $price_diff_percent,
						'product_title'              => $the_product->get_title(),
						'product_variation_atts'     => $the_variation_atts,
					);
				} else {
					if ( $the_price < $lowest_price ) {
						$lowest_price                     = $the_price;
						$lowest_price_gateway             = $available_gateway->title;
						$lowest_price_gateway_description = $available_gateway->get_description();
						$lowest_price_gateway_icon        = $available_gateway->get_icon();
						$lowest_price_diff                = $price_diff;
						$lowest_price_diff_percent        = $price_diff_percent;
					}
				}
			}

			$gateways_data[] = $single_product_gateways_data;

			if ( true === $lowest_price_only && '' !== $lowest_price_gateway ) {
				$lowest_price_array[] = array(
					'gateway_title'              => $lowest_price_gateway,
					'gateway_description'        => isset( $lowest_price_gateway_description ) ? $lowest_price_gateway_description : '',
					'gateway_icon'               => isset( $lowest_price_gateway_icon ) ? $lowest_price_gateway_icon : '',
					'product_gateway_price'      => $lowest_price,
					'product_original_price'     => $the_display_price,
					'product_price_diff'         => isset( $lowest_price_diff ) ? $lowest_price_diff : 0,
					'product_price_diff_percent' => isset( $lowest_price_diff_percent ) ? $lowest_price_diff_percent : 0,
					'product_title'              => $the_product->get_title(),
					'product_variation_atts'     => $the_variation_atts,
				);
			}
		}

		$info           = get_option( 'pgbf_pro_settings', array() );
		$pp_settings    = isset( $info['info']['product_page'] ) ? $info['info']['product_page'] : array();
		$lp_settings    = isset( $info['info']['lowest_price'] ) ? $info['info']['lowest_price'] : array();
		$variable_info  = isset( $info['info']['variable_info_display'] ) ? $info['info']['variable_info_display'] : 'for_each_variation';

		$row_tpl    = isset( $pp_settings['row_html'] ) ? $pp_settings['row_html'] : '<row><td><strong>%gateway_title%</strong></td><td>%product_original_price%</td><td>%product_gateway_price%</td><td>%product_price_diff%</td></tr>';
		$start_tpl  = isset( $pp_settings['start_html'] ) ? $pp_settings['start_html'] : '<table>';
		$end_tpl    = isset( $pp_settings['end_html'] ) ? $pp_settings['end_html'] : '</table>';
		$lowest_tpl = isset( $lp_settings['template_html'] ) ? $lp_settings['template_html'] : '<p><strong>%gateway_title%</strong> %product_gateway_price% (%product_price_diff%)</p>';

		$price_keys = array( 'product_gateway_price', 'product_original_price', 'product_price_diff' );
		$final_html = '';

		if ( 'for_each_variation' === $variable_info ) {
			if ( false === $lowest_price_only && ! empty( $gateways_data ) ) {
				foreach ( $gateways_data as $single_product_gateways_data ) {
					$single_product_gateways_data_html = '';
					foreach ( $single_product_gateways_data as $row ) {
						$row_html = $row_tpl;
						foreach ( $row as $key => $value ) {
							if ( in_array( $key, $price_keys, true ) ) {
								$value = wc_price( $value );
							}
							$row_html = str_replace( '%' . $key . '%', $value, $row_html );
						}
						$single_product_gateways_data_html .= $row_html;
					}
					$final_html .= $start_tpl . $single_product_gateways_data_html . $end_tpl;
				}
			} elseif ( true === $lowest_price_only && ! empty( $lowest_price_array ) ) {
				foreach ( $lowest_price_array as $lowest_price ) {
					$row_html = $lowest_tpl;
					foreach ( $lowest_price as $key => $value ) {
						if ( in_array( $key, $price_keys, true ) ) {
							$value = wc_price( $value );
						}
						$row_html = str_replace( '%' . $key . '%', $value, $row_html );
					}
					$final_html .= $row_html;
				}
			}
		} elseif ( 'ranges' === $variable_info ) {
			// Range display logic (simplified - keep original logic).
			if ( false === $lowest_price_only && ! empty( $gateways_data ) ) {
				$modified_array = array();
				foreach ( $gateways_data as $i => $single_product_gateways_data ) {
					foreach ( $single_product_gateways_data as $gateway_key => $row ) {
						foreach ( $row as $key => $value ) {
							$modified_array[ $gateway_key ][ $key ][ $i ] = $value;
						}
					}
				}

				foreach ( $modified_array as $gateway_key => $values ) {
					$row_html = $row_tpl;
					foreach ( $values as $key => $values_array ) {
						$values_array = array_unique( $values_array );
						if ( in_array( $key, $price_keys, true ) ) {
							$value = ( count( $values_array ) > 1 )
								? wc_price( min( $values_array ) ) . '&ndash;' . wc_price( max( $values_array ) )
								: wc_price( min( $values_array ) );
						} else {
							$value = implode( '<br>', $values_array );
						}
						$row_html = str_replace( '%' . $key . '%', $value, $row_html );
					}
					$final_html .= $row_html;
				}
				$final_html = $start_tpl . $final_html . $end_tpl;
			} elseif ( true === $lowest_price_only && ! empty( $lowest_price_array ) ) {
				$modified_array = array();
				foreach ( $lowest_price_array as $i => $row ) {
					foreach ( $row as $key => $value ) {
						$modified_array[ $key ][ $i ] = $value;
					}
				}

				$row_html = $lowest_tpl;
				foreach ( $modified_array as $key => $values_array ) {
					$values_array = array_unique( $values_array );
					if ( in_array( $key, $price_keys, true ) ) {
						$value = ( count( $values_array ) > 1 )
							? wc_price( min( $values_array ) ) . '&ndash;' . wc_price( max( $values_array ) )
							: wc_price( min( $values_array ) );
					} else {
						$value = implode( '<br>', $values_array );
					}
					$row_html = str_replace( '%' . $key . '%', $value, $row_html );
				}
				$final_html = $row_html;
			}
		}

		return $final_html;
	}
}

// Backward-compat alias.
if ( ! class_exists( 'Alg_WC_Checkout_Fees_Info' ) ) {
	class_alias( __NAMESPACE__ . '\\Checkout_Fees_Info', 'Alg_WC_Checkout_Fees_Info' );
}
