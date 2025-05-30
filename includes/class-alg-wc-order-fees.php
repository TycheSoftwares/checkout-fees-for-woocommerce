<?php
/**
 * Checkout Fees for WooCommerce on Order-Pay page
 *
 * @version 2.5.4
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package checkout-fees-for-woocommerce/order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Order_Fees' ) ) :

	/**
	 * Add gateway fees on Pay Order page.
	 */
	class Alg_WC_Order_Fees {

		/**
		 * Max total discounts
		 *
		 * @var $max_total_all_discounts
		 */
		public $max_total_all_discounts = 0;
		/**
		 * Max total fees
		 *
		 * @var $max_total_all_fees
		 */
		public $max_total_all_fees = 0;
		/**
		 * Names of fees added by the plugin
		 *
		 * @var $fees_added
		 */
		public $fees_added = array();
		/**
		 * Args manager
		 *
		 * @var $args_manager
		 */
		public $args_manager = null;
		/**
		 * Do merge fees
		 *
		 * @var $do_merge_fees
		 */
		public $do_merge_fees = null;
		/**
		 * Base Currency
		 *
		 * @var $base_currency
		 */
		public $base_currency = '';
		/**
		 * Constructor.
		 *
		 * @version 2.5.0
		 * @todo    [feature] per product - add bulk settings editor/tool
		 */
		public function __construct() {
			if ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_enabled', 'yes' ) ) {
				$this->args_manager  = new Alg_WC_Checkout_Fees_Args();
				$this->base_currency = get_option( 'woocommerce_currency' );
				$this->do_merge_fees = ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_merge_all_fees', 'no' ) );
				add_action( 'wc_ajax_update_fees', array( $this, 'update_checkout_fees_ajax' ) );
				add_filter( 'alg_wc_add_gateways_fees', array( $this, 'alc_wc_deposits_for_wc_compatibility' ), 10, 2 );
				add_action( 'woocommerce_before_save_order_items', array( $this, 'alg_wc_cf_update_order_fees' ), PHP_INT_MAX, 2 );
				add_action( 'woocommerce_order_item_fee_after_calculate_taxes', array( $this, 'alg_wc_order_item_fee_after_calculate_taxes' ), 10, 2 );
			}
		}

		/**
		 * Function to add the fees in the Order when order is updated.
		 *
		 * @param int    $order_id Order ID.
		 * @param object $order Post object.
		 */
		public function alg_wc_cf_update_order_fees( $order_id, $order ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
				return;
			}
			$current_payment_method = $order->get_payment_method();
			if ( is_admin() ) {
                $posted_payment_method = isset( $_POST['_payment_method'] ) ? wc_clean( $_POST['_payment_method'] ) : $current_payment_method; // phpcs:ignore
			} else {
				$posted_payment_method = $current_payment_method;
			}
			if ( $posted_payment_method !== $current_payment_method ) {
				$this->remove_fees( $order );
				$this->add_gateways_fees( $order, $posted_payment_method );
				do_action( 'alg_wc_checkout_fees_after_order_updated', $this, $order );
			}
		}

		/**
		 * Do not add fees again if the Fees is splited into the partial payments.
		 *
		 * @param bool $status Whether to add fees or not.
		 * @param obj  $order Order Object.
		 *
		 * @return bool $status True if continue to add the fees.
		 */
		public function alc_wc_deposits_for_wc_compatibility( $status, $order ) {
			if ( 'WCDP_Payment' === get_class( $order ) ) {
				if ( 'split' === get_option( 'wc_deposits_fees_handling', '' ) ) {
					$status = false;
				}
			}

			return $status;
		}

		/**
		 * Wc_Ajac function called on change of payment method on 'pay-order' page.
		 *
		 * @return void
		 */
		public function update_checkout_fees_ajax() {
			global $wp;
			check_ajax_referer( 'update-payment-method', 'security' );

			$payment_method       = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$order_id             = isset( $_POST['order_id'] ) ? sanitize_key( $_POST['order_id'] ): 0; // phpcs:ignore
			$payment_method_title = isset( $_POST['payment_method_title'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method_title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			if ( $order_id <= 0 ) {
				wp_die();
			}

			$order = wc_get_order( $order_id );
			if ( $order ) {
				$add_fees = apply_filters( 'alg_wc_add_gateways_fees', true, $order );
				$this->remove_fees( $order );
			}
			if ( $add_fees ) {
				$this->add_gateways_fees( $order, $payment_method );

				// Update payment method record in the database.
				$order->set_payment_method( $payment_method );
				$order->set_payment_method_title( $payment_method_title );
				$order->save();
			}

			// Declare $order again to fetch updates to post meta and serve to payment templte engine.
			$order = wc_get_order( $order_id );

			ob_start();
			$this->woocommerce_order_pay( $order );
			$woocommerce_order_pay = ob_get_clean();

			wp_send_json(
				array(
					'fragments' => $woocommerce_order_pay,
				)
			);
		}

		/**
		 * Remove fees from previously selected method.
		 *
		 * @param WC_Order $order Order object.
		 * @return void
		 */
		public function remove_fees( $order ) {
			global $wpdb;
			if ( $order ) {
				foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
					$last_added   = wc_get_order_item_meta( $item_id, '_last_added_fee' );
					$last_added_2 = wc_get_order_item_meta( $item_id, '_last_added_fee_2' );
					if ( $last_added === $item->get_name() || $last_added_2 === $item->get_name() ) {
						wc_delete_order_item( $item_id );
						$order->remove_item( $item_id );
					}
				}
				$order->calculate_totals();
				$order->save();
			}
		}

		/**
		 * Clear existing fees from the order.
		 */
		public function clear_existing_fees( $order ) {
			foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
				// Check if the fee's title matches 'fee'
				if ( 'Fee' === $item->get_name() ) {
					$order->remove_item( $item_id );
				}
			}
			$order->calculate_totals();
			$order->save();
		}

		/**
		 * Add fees for a payment gateway based on local/global settings.
		 *
		 * @param WC_Order $order Order object.
		 * @param string   $current_gateway Payment gateway selected.
		 * @return void
		 */
		public function add_gateways_fees( $order, $current_gateway ) {
			$checkout_obj = new Alg_WC_Checkout_Fees();

			$this->get_max_ranges();
			if ( $this->do_merge_fees ) {
				$this->fees = array();
			}

			// Clear previous fees for order.
			$this->clear_existing_fees( $order );

			// Add fee - globally.
			$do_add_fees_global = $checkout_obj->check_countries( $current_gateway );
			if ( $do_add_fees_global ) {
				$args = $this->args_manager->get_the_args_global( $current_gateway );
				$this->maybe_add_order_fee( $args, $order );
			}

			// Add fee - per product.
			if ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_per_product_enabled', 'no' ) && ( 'bacs' === $current_gateway || apply_filters( 'alg_wc_checkout_fees_option', false, 'per_product' ) ) ) {
				foreach ( $order->get_items() as $item_id => $item ) {
					$args = $this->args_manager->get_the_args_local( $current_gateway, $item['product_id'], $item['variation_id'], $item['quantity'] );
					$this->maybe_add_order_fee( $args, $order );
				}
			}
			// Add fee - "super" global.
			if ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_global_fee_enabled', 'no' ) ) {
				$do_add = true;
				if ( 'yes' === get_option( 'alg_woocommerce_checkout_fees_global_fee_as_extra_enabled', 'no' ) ) {
					$current_fees = ( $this->do_merge_fees ? $this->fees : $order->get_items( 'fee' ) );
					if ( empty( $current_fees ) ) {
						$do_add = false;
					}
				}
				if ( $do_add ) {
					$gateways_excl = get_option( 'alg_woocommerce_checkout_fees_global_fee_gateways_excl', '' );
					if ( ! empty( $gateways_excl ) && in_array( $current_gateway, $gateways_excl, true ) ) {
						$do_add = false;
					}
				}
				if ( $do_add ) {
					$global_title = get_option( 'alg_woocommerce_checkout_fees_global_fee_title', '' );
					$global_value = get_option( 'alg_woocommerce_checkout_fees_global_fee_value', 0 );
					$global_type  = get_option( 'alg_woocommerce_checkout_fees_global_fee_type', 'fixed' );
					if ( 'fixed' === $global_type ) {
						$global_fee = $global_value;
					} else {
						$global_fee = ( $order->get_subtotal() ) / 100 * $global_value;
					}
					if ( $this->do_merge_fees ) {
						$this->fees[] = array(
							'title'     => $global_title,
							'value'     => $global_fee,
							'taxable'   => false,
							'tax_class' => '',
						);
					} else {
						$item_fee = new WC_Order_Item_Fee();

						$item_fee->set_name( $global_title );
						$item_fee->set_amount( $global_fee );
						$item_fee->set_total( $global_fee );

						// Add Fee item to the order.
						$order->add_item( $item_fee );
						$order->calculate_totals();
						$order->save();
						$this->fees_added[] = $global_title;
					}
				}
			}

			// Maybe merge.
			if ( $this->do_merge_fees && ! empty( $this->fees ) ) {
				$merged_fee = array();
				foreach ( $this->fees as $fee ) {
					if ( empty( $merged_fee ) ) {
						$merged_fee = $fee;
					} else {
						$merged_fee['value'] += $fee['value'];
					}
				}
				if ( ! empty( $merged_fee ) ) {
					$item_fee = new WC_Order_Item_Fee();

					$item_fee->set_name( $merged_fee['title'] );
					$item_fee->set_amount( $merged_fee['value'] );
					$item_fee->set_total( $merged_fee['value'] );

					// Add Fee item to the order.
					$order->add_item( $item_fee );
					$order->calculate_totals();
					$order->save();
					$this->fees_added[] = $merged_fee['title'];
				}
			}

			foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
				if ( in_array( $item->get_name(), $this->fees_added ) ) { //phpcs:ignore
					wc_add_order_item_meta( $item_id, '_last_added_fee', $item->get_name() );
				}
			}
		}

		/**
		 * Get the maximum values for the fees.
		 *
		 * @return void
		 */
		public function get_max_ranges() {
			$checkout_obj = new Alg_WC_Checkout_Fees();

			$this->max_total_all_discounts = $checkout_obj->convert_currency( get_option( 'alg_woocommerce_checkout_fees_range_max_total_discounts', 0 ) );
			$this->max_total_all_fees      = $checkout_obj->convert_currency( get_option( 'alg_woocommerce_checkout_fees_range_max_total_fees', 0 ) );
			if ( 0 == $this->max_total_all_discounts || '' === $this->max_total_all_discounts ) { //phpcs:ignore
				$this->max_total_all_discounts = false;
			}
			if ( 0 == $this->max_total_all_fees || '' === $this->max_total_all_fees ) { //phpcs:ignore
				$this->max_total_all_fees = false;
			}
		}

		/**
		 * Add fees to the order based on payment gateway.
		 *
		 * @param array    $args  Local/Global settings array.
		 * @param WC_Order $order Order object.
		 * @return void
		 */
		public function maybe_add_order_fee( $args, $order ) {
			$checkout_obj = new Alg_WC_Checkout_Fees();

			if ( $args['fee_text'] === $args['fee_text_2'] || '' === $args['fee_text_2'] ) {
				$final_fee_to_add   = $this->get_the_fee( $order, $args, 'fee_both' );
				$final_fee_to_add_2 = 0;
			} else {
				$final_fee_to_add   = $this->get_the_fee( $order, $args, 'fee_1' );
				$final_fee_to_add_2 = $this->get_the_fee( $order, $args, 'fee_2' );
			}

			if ( 0 != $final_fee_to_add || 0 != $final_fee_to_add_2 ) { //phpcs:ignore
				$taxable        = $args['is_taxable'];
				$tax_class_slug = '';
				if ( $taxable ) {
					$tax_class_slugs = array_merge( array( '' ), WC_Tax::get_tax_class_slugs() );
					$tax_class_slug  = ( isset( $tax_class_slugs[ $args['tax_class_id'] ] ) ? $tax_class_slugs[ $args['tax_class_id'] ] : '' );
				}
				if ( 'no' === $taxable ) {
					$taxable = 'none';
				}
				$fees = $order->get_fees();
				if ( 0 != $final_fee_to_add ) { //phpcs:ignore
					if ( $this->do_merge_fees ) {
						$this->fees[] = array(
							'title'     => $args['fee_text'],
							'value'     => $final_fee_to_add,
							'taxable'   => $taxable,
							'tax_class' => $tax_class_slug,
						);
					} else {
						$this->fees_added[] = $args['fee_text'];

						$item_fee = new WC_Order_Item_Fee();

						$item_fee->set_name( $args['fee_text'] ); // Generic fee name.
						$item_fee->set_amount( $final_fee_to_add ); // Fee amount.
						$item_fee->set_tax_class( $tax_class_slug ); // default for ''.
						$item_fee->set_tax_status( $taxable ); // or 'none'.
						$item_fee->set_total( $final_fee_to_add ); // Fee amount.

						// Add Fee item to the order.
						$order->add_item( $item_fee );
						$order->calculate_totals();
						$order->save();

						foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {

							if ( $args['fee_text'] === $item->get_name() ) {
								wc_add_order_item_meta( $item_id, '_last_added_fee', $args['fee_text'] );
							}
						}
					}
				}
				if ( 0 != $final_fee_to_add_2 ) { //phpcs:ignore
					if ( $this->do_merge_fees ) {
						$this->fees[] = array(
							'title'     => $args['fee_text_2'],
							'value'     => $final_fee_to_add_2,
							'taxable'   => $taxable,
							'tax_class' => $tax_class_slug,
						);
					} else {
						$this->fees_added[] = $args['fee_text_2'];
						$item_fee           = new WC_Order_Item_Fee();

						$item_fee->set_name( $args['fee_text_2'] ); // Generic fee name.
						$item_fee->set_amount( $final_fee_to_add_2 ); // Fee amount.
						$item_fee->set_tax_class( $tax_class_slug ); // default for ''.
						$item_fee->set_tax_status( $taxable ); // or 'none'.
						$item_fee->set_total( $final_fee_to_add_2 ); // Fee amount.

						// Add Fee item to the order.
						$order->add_item( $item_fee );
						$order->calculate_totals();
						$order->save();

						foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
							if ( $args['fee_text_2'] === $item->get_name() ) {
								wc_add_order_item_meta( $item_id, '_last_added_fee_2', $args['fee_text_2'] );
							}
						}
					}
				}
			}
		}

		/**
		 * Get the total fee to be added to the order.
		 *
		 * @param WC_Order $order Order object.
		 * @param array    $args  Local/global settings array.
		 * @param string   $fee_num Fee 1 or Fee 2.
		 * @param integer  $total_in_cart Total fees added to order.
		 * @param boolean  $is_info_only  If fee is info only.
		 * @param integer  $info_product_id Product ID.
		 */
		public function get_the_fee( $order, $args, $fee_num, $total_in_cart = 0, $is_info_only = false, $info_product_id = 0 ) {
			$final_fee_to_add = 0;
			$checkout_obj     = new Alg_WC_Checkout_Fees();

			if ( '' !== $args['current_gateway'] && 'yes' === $args['is_enabled'] ) {

				if ( 0 == $total_in_cart ) { //phpcs:ignore
					$total_in_cart = ( 'yes' === $args['exclude_shipping'] ) ? $order->get_subtotal() : $order->get_subtotal() + $order->get_shipping_total();

					if ( 'yes' === $args['add_taxes'] ) {
						$tax_total = $order->get_cart_tax();
						if ( 'yes' === $args['exclude_shipping'] ) {
							$total_in_cart += $tax_total;
						} else {
							$shipping_tax_total = $order->get_shipping_tax();
							$total_in_cart     += $tax_total + $shipping_tax_total;
						}
					}
				}

				$min_cart_amount = ! empty( $args['min_cart_amount'] ) ? $args['min_cart_amount'] : 0;
				$max_cart_amount = ! empty( $args['max_cart_amount'] ) ? $args['max_cart_amount'] : 0;
				if ( $total_in_cart >= $min_cart_amount && ( 0 == $max_cart_amount || $total_in_cart <= $max_cart_amount ) ) { //phpcs:ignore
					if ( 0 != $args['fee_value'] && 'fee_2' !== $fee_num ) { //phpcs:ignore
						if ( 'local' === $args['fee_scope'] || $this->do_apply_fees_by_categories( $order, 'fee_1', $args['current_gateway'], $info_product_id ) ) {
							if ( ! $is_info_only && 'global' === $args['fee_scope'] ) {
								$total_in_cart = $this->get_sum_for_fee_by_included_and_excluded_cats( $order, $total_in_cart, 'fee_1', $args['current_gateway'] );
							}

							if ( ( 'local' === $args['fee_scope'] || $checkout_obj->check_countries( $args['current_gateway'], 'fee_1' ) ) ) {
								$final_fee_to_add = $this->calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, 'fee_1', $order );
							}
						}
					}
					if ( 0 != $args['fee_value_2'] && 'fee_1' !== $fee_num ) { //phpcs:ignore
						if ( 'local' === $args['fee_scope'] || $this->do_apply_fees_by_categories( $order, 'fee_2', $args['current_gateway'], $info_product_id ) ) {
							if ( ! $is_info_only && 'global' === $args['fee_scope'] ) {
								$total_in_cart = $this->get_sum_for_fee_by_included_and_excluded_cats( $order, $total_in_cart, 'fee_2', $args['current_gateway'] );
							}
							if ( ( 'local' === $args['fee_scope'] || $checkout_obj->check_countries( $args['current_gateway'], 'fee_2' ) ) ) {
								$final_fee_to_add = $this->calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, 'fee_2', $order );
							}
						}
					}
				}
			}
			return $final_fee_to_add;
		}

		/**
		 * Get sum for fee by included and excluded cats - calculate by categories and global fees override.
		 *
		 * @param WC_Order $order Order object.
		 * @param float    $total_in_cart Total fees in cart.
		 * @param string   $fee_num Fee number.
		 * @param string   $current_gateway Current selected gateway.
		 * @version 2.5.0
		 * @since   2.1.0
		 */
		public function get_sum_for_fee_by_included_and_excluded_cats( $order, $total_in_cart, $fee_num, $current_gateway ) {
			// Categories.
			$checkout_obj = new Alg_WC_Checkout_Fees();
			if ( 'fee_2' === $fee_num ) {
				$include_cats = ( false === get_option( 'alg_gateways_fees_cats_include_fee_2_' . $current_gateway, false ) ) ?
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'include',
							'fee_num'         => '',
							'current_gateway' => $current_gateway,
						)
					) :
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'include',
							'fee_num'         => 'fee_2_',
							'current_gateway' => $current_gateway,
						)
					);
				$exclude_cats = ( false === get_option( 'alg_gateways_fees_cats_exclude_fee_2_' . $current_gateway, false ) ) ?
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'exclude',
							'fee_num'         => '',
							'current_gateway' => $current_gateway,
						)
					) :
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'exclude',
							'fee_num'         => 'fee_2_',
							'current_gateway' => $current_gateway,
						)
					);
			} else {
				$include_cats = apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'include',
						'fee_num'         => '',
						'current_gateway' => $current_gateway,
					)
				);
				$exclude_cats = apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'exclude',
						'fee_num'         => '',
						'current_gateway' => $current_gateway,
					)
				);
			}
			if ( ! empty( $include_cats ) && 'only_for_selected_products' === get_option( 'alg_gateways_fees_cats_include_calc_type_' . $current_gateway, 'for_all_cart' ) ) {
				$sum_for_fee = 0;
				foreach ( $order->get_items() as $item_id => $item ) {
					$product_cats  = $checkout_obj->get_product_cats( $item['product_id'] );
					$the_intersect = array_intersect( $product_cats, $include_cats );
					if ( ! empty( $the_intersect ) ) {
						if ( ! $checkout_obj->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
							$sum_for_fee += $item['line_total'];
						}
					}
				}
			} elseif ( ! empty( $exclude_cats ) && 'only_for_selected_products' === get_option( 'alg_gateways_fees_cats_exclude_calc_type_' . $current_gateway, 'for_all_cart' ) ) {
				$sum_for_fee = 0;
				foreach ( $order->get_items() as $item_id => $item ) {
					$product_cats  = $checkout_obj->get_product_cats( $item['product_id'] );
					$the_intersect = array_intersect( $product_cats, $exclude_cats );
					if ( empty( $the_intersect ) ) {
						if ( ! $checkout_obj->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
							$sum_for_fee += $item['line_total'];
						}
					}
				}
			} else {
				$sum_for_fee = $total_in_cart;
				// Global fees override.
				foreach ( $order->get_items() as $item_id => $item ) {
					if ( $checkout_obj->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
						$sum_for_fee -= $item['line_total'];
					}
				}
			}
			return $sum_for_fee;
		}

		/**
		 * Apply fees based on categories - check by categories and by global fee override.
		 *
		 * @param WC_Order $order Order object.
		 * @param string   $fee_num Fee number.
		 * @param string   $current_gateway Current selected gateway.
		 * @param integer  $info_product_id Product ID.
		 * @since   2.6
		 */
		public function do_apply_fees_by_categories( $order, $fee_num, $current_gateway, $info_product_id ) {
			// Global fees override.
			$checkout_obj = new Alg_WC_Checkout_Fees();

			if ( 0 != $info_product_id ) { //phpcs:ignore
				if ( $checkout_obj->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $info_product_id ) ) {
					return false;
				}
			} else {
				$do_override_global_fees_for_all_cart = true;
				$items_array                          = $order->get_items();
				if ( empty( $items_array ) ) {
					$do_override_global_fees_for_all_cart = false;
				}
				foreach ( $order->get_items() as $item_id => $item ) {
					if ( ! $checkout_obj->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
						// At least one product does not have the override, no need to check further.
						$do_override_global_fees_for_all_cart = false;
						break;
					}
				}
				if ( $do_override_global_fees_for_all_cart ) {
					return false;
				}
			}
			// Categories.
			if ( 'fee_2' === $fee_num ) {
				$include_cats = ( false === get_option( 'alg_gateways_fees_cats_include_fee_2_' . $current_gateway, false ) ) ?
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'include',
							'fee_num'         => '',
							'current_gateway' => $current_gateway,
						)
					) :
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'include',
							'fee_num'         => 'fee_2_',
							'current_gateway' => $current_gateway,
						)
					);
				$exclude_cats = ( false === get_option( 'alg_gateways_fees_cats_exclude_fee_2_' . $current_gateway, false ) ) ?
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'exclude',
							'fee_num'         => '',
							'current_gateway' => $current_gateway,
						)
					) :
					apply_filters(
						'alg_wc_checkout_fees_option',
						'',
						'cats',
						array(
							'type'            => 'exclude',
							'fee_num'         => 'fee_2_',
							'current_gateway' => $current_gateway,
						)
					);
			} else {
				$include_cats = apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'include',
						'fee_num'         => '',
						'current_gateway' => $current_gateway,
					)
				);
				$exclude_cats = apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'exclude',
						'fee_num'         => '',
						'current_gateway' => $current_gateway,
					)
				);
			}

			if ( '' !== $include_cats || '' !== $exclude_cats ) {
				if ( 0 != $info_product_id ) { //phpcs:ignore
					$product_cats = $checkout_obj->get_product_cats( $info_product_id );
					if ( ! empty( $include_cats ) ) {
						$the_intersect = array_intersect( $product_cats, $include_cats );
						if ( empty( $the_intersect ) ) {
							return false;
						}
					}
					if ( ! empty( $exclude_cats ) ) {
						$the_intersect = array_intersect( $product_cats, $exclude_cats );
						if ( ! empty( $the_intersect ) ) {
							return false;
						}
					}
				} else {
					if ( ! empty( $include_cats ) ) {
						foreach ( $order->get_items() as $item_id => $item ) {
							$product_cats  = $checkout_obj->get_product_cats( $item['product_id'] );
							$the_intersect = array_intersect( $product_cats, $include_cats );
							if ( ! empty( $the_intersect ) ) {
								// At least one product in the cart is ok, no need to check further.
								return true;
							}
						}
						return false;
					}
					if ( ! empty( $exclude_cats ) ) {
						if ( 'for_all_cart' === get_option( 'alg_gateways_fees_cats_exclude_calc_type_' . $current_gateway, 'for_all_cart' ) ) {
							foreach ( $order->get_items() as $item_id => $item ) {
								$product_cats  = $checkout_obj->get_product_cats( $item['product_id'] );
								$the_intersect = array_intersect( $product_cats, $exclude_cats );
								if ( ! empty( $the_intersect ) ) {
									// At least one product in the cart is NOT ok, no need to check further.
									return false;
								}
							}
							return true;
						} else {
							foreach ( $order->get_items() as $item_id => $item ) {
								$product_cats  = $checkout_obj->get_product_cats( $item['product_id'] );
								$the_intersect = array_intersect( $product_cats, $exclude_cats );
								if ( empty( $the_intersect ) ) {
									// At least one product in the cart is ok, no need to check further.
									return true;
								}
							}
							return false;
						}
					}
				}
			}
			return true;
		}

		/**
		 * Calculate the fee.
		 *
		 * @param array  $args local/global settings array.
		 * @param float  $final_fee_to_add Final fee to add on checkout.
		 * @param float  $total_in_cart Total fees added to cart.
		 * @param string $fee_num Fee number.
		 * @param object $order Order object.
		 * @since   2.6
		 */
		public function calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, $fee_num, $order ) {
			$checkout_obj = new Alg_WC_Checkout_Fees();

			if ( 'fee_2' === $fee_num ) {
				$fee_type  = $args['fee_type_2'];
				$fee_value = $args['fee_value_2'];
				$min_fee   = ! empty( $args['min_fee_2'] ) ? $args['min_fee_2'] : 0;
				$max_fee   = ! empty( $args['max_fee_2'] ) ? $args['max_fee_2'] : 0;
			} else {
				$fee_type  = $args['fee_type'];
				$fee_value = $args['fee_value'];
				$min_fee   = ! empty( $args['min_fee'] ) ? $args['min_fee'] : 0;
				$max_fee   = ! empty( $args['max_fee'] ) ? $args['max_fee'] : 0;
			}
			$new_fee = 0;
			switch ( $fee_type ) {
				case 'fixed':
					$fixed_fee = ( 'by_quantity' === $args['fixed_usage'] ) ? (float) $fee_value * $args['product_qty'] : $fee_value;
					$fixed_fee = $checkout_obj->convert_currency( $fixed_fee );
					$new_fee   = $fixed_fee;
					break;
				case 'percent':
					if ( 0 != $args['product_id'] ) { //phpcs:ignore
						$_product    = wc_get_product( $args['product_id'] );
						$sum_for_fee = $_product->get_price() * $args['product_qty'];
					} else { //phpcs:ignore
						if ( (float) 0 === $total_in_cart ) {
							$cf_on_fees = apply_filters( 'alg_wc_not_to_calculate_on_fees', true );
							if ( $cf_on_fees ) {
								$fee_totals = 0;
								foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
									$fee_total           = $item->get_total();
									(float) $fee_totals += $fee_total;
								}
								$sum_for_fee = $fee_totals;
							} else {
								$sum_for_fee = $total_in_cart;
							}
						} else {
							$sum_for_fee    = $total_in_cart;
							$discount_total = $order->get_discount_total();
							$sum_for_fee   -= $discount_total;
						}
					}
					$new_fee = ( (float) $fee_value / 100 ) * $sum_for_fee;
					break;
			}
			// Min fee.
			if ( 0 != $min_fee && $new_fee < $min_fee ) { //phpcs:ignore
				$new_fee = $min_fee;
			}
			// Max fee.
			if ( 0 != $max_fee && $new_fee > $max_fee ) { //phpcs:ignore
				$new_fee = $max_fee;
			}
			// Max total discount.
			if ( false !== $this->max_total_all_discounts ) {
				if ( $new_fee < $this->max_total_all_discounts ) {
					$new_fee = $this->max_total_all_discounts;
				}
				$this->max_total_all_discounts = (float) $this->max_total_all_discounts - (float) $new_fee;
				if ( $this->max_total_all_discounts > 0 ) {
					$this->max_total_all_discounts = 0;
				}
			}
			// Max total fees.
			if ( false !== $this->max_total_all_fees ) {
				if ( $new_fee > $this->max_total_all_fees ) {
					$new_fee = $this->max_total_all_fees;
				}
				$this->max_total_all_fees = (float) $this->max_total_all_fees - (float) $new_fee;
				if ( $this->max_total_all_fees < 0 ) {
					$this->max_total_all_fees = 0;
				}
			}
			// Final calculations.
			$final_fee_to_add += (float) $new_fee;
			if ( 'percent' === $fee_type && 'yes' === $args['do_round'] ) {
				// default the precision to 0 if it has been left blanks.
				$precision        = '' == $args['precision'] ? 0 : $args['precision']; //phpcs:ignore
				$final_fee_to_add = round( $final_fee_to_add, $precision );
			}
			return $final_fee_to_add;
		}

		/**
		 * Get Pay order page template
		 *
		 * @param WC_Order $order Order object.
		 */
		public function woocommerce_order_pay( $order ) {
			$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

			if ( count( $available_gateways ) ) {
				current( $available_gateways )->set_current();
			}
			wc_get_template(
				'checkout/form-pay.php',
				array(
					'order'              => $order,
					'available_gateways' => $available_gateways,
					'order_button_text'  => apply_filters( 'woocommerce_pay_order_button_text', __( 'Pay for order', 'woocommerce' ) ),
				)
			);
		}

		/**
		 * Removing the tax amount from negative fees form order edit page when click on recalculate button. This patch is for #191.
		 *
		 * Ref - https://github.com/woocommerce/woocommerce/pull/16969/files.
		 *
		 * @param object $fees Fees Object.
		 * @param array  $calculate_tax_for Calculate tax array.
		 */
		public function alg_wc_order_item_fee_after_calculate_taxes( $fees, $calculate_tax_for ) { //phpcs:ignore
			if ( $fees->get_tax_status() != 'taxable' ) { //phpcs:ignore
				if ( $fees->get_total() < 0 ) {
					$fees->set_tax_class( '' );
					$fees->set_tax_status( 'none' );
					$fees->set_total( $fees->get_total() );
					$fees->set_total_tax( 0 );
					$fees->set_taxes( array( 'total' => 0 ) );
					$fees->save();
				}
			}
		}
	}

endif;

return new Alg_WC_Order_Fees();
