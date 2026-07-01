<?php
/**
 * Checkout Order Fees (Order‑Pay page)
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add gateway fees on Pay Order page.
 */
class Checkout_Order_Fees {

	/**
	 * Max total discounts.
	 *
	 * @var int|float|bool
	 */
	public $max_total_all_discounts = 0;

	/**
	 * Max total fees.
	 *
	 * @var int|float|bool
	 */
	public $max_total_all_fees = 0;

	/**
	 * Names of fees added by the plugin.
	 *
	 * @var array
	 */
	public $fees_added = array();

	/**
	 * Args manager.
	 *
	 * @var Checkout_Fees_Args
	 */
	public $args_manager;

	/**
	 * Do merge fees?
	 *
	 * @var bool
	 */
	public $do_merge_fees;

	/**
	 * Base currency.
	 *
	 * @var string
	 */
	public $base_currency;

	/**
	 * Convert a value to boolean (handles both bool and 'yes'/'no' strings).
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private function is_yes( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return 'yes' === $value || '1' === $value || 1 === $value;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( Settings::general( 'enabled', true ) ) {
			$this->args_manager  = new Checkout_Fees_Args();
			$this->base_currency = get_option( 'woocommerce_currency' );
			$this->do_merge_fees = Settings::general( 'merge_all_fees', false );

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
	 * Compatibility with WooCommerce Deposits.
	 *
	 * @param bool   $status Whether to add fees.
	 * @param object $order  Order object.
	 * @return bool
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
	 * AJAX handler for updating fees on order‑pay page.
	 */
	public function update_checkout_fees_ajax() {
		check_ajax_referer( 'update-payment-method', 'security' );

		$payment_method       = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';
		$order_id             = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$payment_method_title = isset( $_POST['payment_method_title'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method_title'] ) ) : '';

		if ( $order_id <= 0 ) {
			wp_send_json_error( 'Invalid order ID' );
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'Order not found' );
		}

		$current_user_id = get_current_user_id();
		$order_user_id   = (int) $order->get_user_id();

		if ( current_user_can( 'manage_woocommerce' ) ) {
			$authorized = true;
		} elseif ( $current_user_id && $current_user_id === $order_user_id ) {
			$authorized = true;
		} elseif ( ! is_user_logged_in() ) {
			$posted_order_key = isset( $_POST['order_key'] ) ? wc_clean( wp_unslash( $_POST['order_key'] ) ) : '';
			$authorized = hash_equals( $order->get_order_key(), $posted_order_key );
		} else {
			$authorized = false;
		}

		if ( ! $authorized ) {
			wp_send_json_error( 'Unauthorized access' );
		}

		$add_fees = apply_filters( 'alg_wc_add_gateways_fees', true, $order );
		if ( $add_fees ) {
			$this->remove_fees( $order );
			$this->add_gateways_fees( $order, $payment_method );
			$order->set_payment_method( $payment_method );
			$order->set_payment_method_title( $payment_method_title );
			$order->save();
		}

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
	 * Remove fees added by the plugin from an order.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function remove_fees( $order ) {
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
	 * Clear existing fee items (for a fresh start).
	 *
	 * @param WC_Order $order Order object.
	 */
	public function clear_existing_fees( $order ) {
		foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
			// Only remove items that match our previous fee names.
			if ( 'Fee' === $item->get_name() ) {
				$order->remove_item( $item_id );
			}
		}
		$order->calculate_totals();
		$order->save();
	}

	/**
	 * Add fees for a selected gateway on order‑pay page.
	 *
	 * @param WC_Order $order           Order object.
	 * @param string   $current_gateway Gateway ID.
	 */
	public function add_gateways_fees( $order, $current_gateway ) {
		$core = pgbf_lite()->core;

		$this->get_max_ranges();
		if ( $this->do_merge_fees ) {
			$this->fees = array();
		}

		$this->clear_existing_fees( $order );

		// Global fees.
		$do_add_fees_global = $core->check_countries( $current_gateway );
		if ( $do_add_fees_global ) {
			$args = $this->args_manager->get_the_args_global( $current_gateway );
			$this->maybe_add_order_fee( $args, $order );
		}

		// Per‑product fees.
		if ( Settings::general( 'per_product_enabled', false ) &&
			( 'bacs' === $current_gateway || apply_filters( 'alg_wc_checkout_fees_option', false, 'per_product' ) ) ) {
			foreach ( $order->get_items() as $item_id => $item ) {
				$args = $this->args_manager->get_the_args_local( $current_gateway, $item['product_id'], $item['variation_id'], $item['quantity'] );
				$this->maybe_add_order_fee( $args, $order );
			}
		}

		// Super‑global fee.
		if ( Settings::global_fee( 'enabled', false ) ) {
			$do_add = true;
			if ( Settings::global_fee( 'as_extra_only', false ) ) {
				$current_fees = ( $this->do_merge_fees ? $this->fees : $order->get_items( 'fee' ) );
				if ( empty( $current_fees ) ) {
					$do_add = false;
				}
			}
			if ( $do_add ) {
				$gateways_excl = Settings::global_fee( 'gateways_exclude', array() );
				if ( ! empty( $gateways_excl ) && in_array( $current_gateway, $gateways_excl, true ) ) {
					$do_add = false;
				}
			}
			if ( $do_add ) {
				$global_title = Settings::global_fee( 'title', '' );
				$global_value = Settings::global_fee( 'value', 0 );
				$global_type  = Settings::global_fee( 'type', 'fixed' );
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
					$item_fee = new \WC_Order_Item_Fee();
					$item_fee->set_name( $global_title );
					$item_fee->set_amount( $global_fee );
					$item_fee->set_total( $global_fee );
					$order->add_item( $item_fee );
					$order->calculate_totals();
					$order->save();
					$this->fees_added[] = $global_title;
				}
			}
		}

		// Merge.
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
				$item_fee = new \WC_Order_Item_Fee();
				$item_fee->set_name( $merged_fee['title'] );
				$item_fee->set_amount( $merged_fee['value'] );
				$item_fee->set_total( $merged_fee['value'] );
				$order->add_item( $item_fee );
				$order->calculate_totals();
				$order->save();
				$this->fees_added[] = $merged_fee['title'];
			}
		}

		foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
			if ( in_array( $item->get_name(), $this->fees_added, true ) ) {
				wc_add_order_item_meta( $item_id, '_last_added_fee', $item->get_name() );
			}
		}
	}

	/**
	 * Get max ranges (reuses core method).
	 */
	public function get_max_ranges() {
		$core = pgbf_lite()->core;
		$core->get_max_ranges();
		$this->max_total_all_discounts = $core->max_total_all_discounts;
		$this->max_total_all_fees      = $core->max_total_all_fees;
	}

	/**
	 * Maybe add a fee item to the order.
	 *
	 * @param array    $args  Fee arguments.
	 * @param WC_Order $order Order object.
	 */
	public function maybe_add_order_fee( $args, $order ) {
		$core = pgbf_lite()->core;

		if ( $args['fee_text'] === $args['fee_text_2'] || '' === $args['fee_text_2'] ) {
			$final_fee_to_add   = $this->get_the_fee( $order, $args, 'fee_both' );
			$final_fee_to_add_2 = 0;
		} else {
			$final_fee_to_add   = $this->get_the_fee( $order, $args, 'fee_1' );
			$final_fee_to_add_2 = $this->get_the_fee( $order, $args, 'fee_2' );
		}

		if ( 0 != $final_fee_to_add || 0 != $final_fee_to_add_2 ) {
			$taxable        = $this->is_yes( $args['is_taxable'] );
			$tax_class_slug = '';
			if ( $taxable ) {
				$tax_class_slugs = array_merge( array( '' ), WC_Tax::get_tax_class_slugs() );
				$tax_class_slug  = ( isset( $tax_class_slugs[ $args['tax_class_id'] ] ) ? $tax_class_slugs[ $args['tax_class_id'] ] : '' );
			}
			$tax_status = $taxable ? 'taxable' : 'none';

			if ( 0 != $final_fee_to_add ) {
				if ( $this->do_merge_fees ) {
					$this->fees[] = array(
						'title'     => $args['fee_text'],
						'value'     => $final_fee_to_add,
						'taxable'   => $tax_status,
						'tax_class' => $tax_class_slug,
					);
				} else {
					$this->fees_added[] = $args['fee_text'];
					$item_fee = new \WC_Order_Item_Fee();
					$item_fee->set_name( $args['fee_text'] );
					$item_fee->set_amount( $final_fee_to_add );
					$item_fee->set_tax_class( $tax_class_slug );
					$item_fee->set_tax_status( $tax_status );
					$item_fee->set_total( $final_fee_to_add );
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

			if ( 0 != $final_fee_to_add_2 ) {
				if ( $this->do_merge_fees ) {
					$this->fees[] = array(
						'title'     => $args['fee_text_2'],
						'value'     => $final_fee_to_add_2,
						'taxable'   => $tax_status,
						'tax_class' => $tax_class_slug,
					);
				} else {
					$this->fees_added[] = $args['fee_text_2'];
					$item_fee = new \WC_Order_Item_Fee();
					$item_fee->set_name( $args['fee_text_2'] );
					$item_fee->set_amount( $final_fee_to_add_2 );
					$item_fee->set_tax_class( $tax_class_slug );
					$item_fee->set_tax_status( $tax_status );
					$item_fee->set_total( $final_fee_to_add_2 );
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
	 * Get the fee amount for an order.
	 *
	 * @param WC_Order $order         Order object.
	 * @param array    $args          Fee arguments.
	 * @param string   $fee_num       Fee number.
	 * @param float    $total_in_cart Cart/order total.
	 * @param bool     $is_info_only  Info only.
	 * @param int      $info_product_id Product ID.
	 * @return float
	 */
	public function get_the_fee( $order, $args, $fee_num, $total_in_cart = 0, $is_info_only = false, $info_product_id = 0 ) {
		$core = pgbf_lite()->core;
		$final_fee_to_add = 0;

		if ( '' !== $args['current_gateway'] && $this->is_yes( $args['is_enabled'] ) ) {
			if ( 0 == $total_in_cart ) {
				$total_in_cart = ( $this->is_yes( $args['exclude_shipping'] ) ) ? $order->get_subtotal() : $order->get_subtotal() + $order->get_shipping_total();
				if ( $this->is_yes( $args['add_taxes'] ) ) {
					$tax_total = $order->get_cart_tax();
					if ( $this->is_yes( $args['exclude_shipping'] ) ) {
						$total_in_cart += $tax_total;
					} else {
						$shipping_tax_total = $order->get_shipping_tax();
						$total_in_cart     += $tax_total + $shipping_tax_total;
					}
				}
			}

			$min_cart_amount = ! empty( $args['min_cart_amount'] ) ? $args['min_cart_amount'] : 0;
			$max_cart_amount = ! empty( $args['max_cart_amount'] ) ? $args['max_cart_amount'] : 0;

			if ( $total_in_cart >= $min_cart_amount && ( 0 == $max_cart_amount || $total_in_cart <= $max_cart_amount ) ) {
				if ( 0 != $args['fee_value'] && 'fee_2' !== $fee_num ) {
					if ( 'local' === $args['fee_scope'] || $this->do_apply_fees_by_categories( $order, 'fee_1', $args['current_gateway'], $info_product_id ) ) {
						if ( ! $is_info_only && 'global' === $args['fee_scope'] ) {
							$total_in_cart = $this->get_sum_for_fee_by_included_and_excluded_cats( $order, $total_in_cart, 'fee_1', $args['current_gateway'] );
						}
						if ( ( 'local' === $args['fee_scope'] || $core->check_countries( $args['current_gateway'], 'fee_1' ) ) ) {
							$final_fee_to_add = $this->calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, 'fee_1', $order );
						}
					}
				}
				if ( 0 != $args['fee_value_2'] && 'fee_1' !== $fee_num ) {
					if ( 'local' === $args['fee_scope'] || $this->do_apply_fees_by_categories( $order, 'fee_2', $args['current_gateway'], $info_product_id ) ) {
						if ( ! $is_info_only && 'global' === $args['fee_scope'] ) {
							$total_in_cart = $this->get_sum_for_fee_by_included_and_excluded_cats( $order, $total_in_cart, 'fee_2', $args['current_gateway'] );
						}
						if ( ( 'local' === $args['fee_scope'] || $core->check_countries( $args['current_gateway'], 'fee_2' ) ) ) {
							$final_fee_to_add = $this->calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, 'fee_2', $order );
						}
					}
				}
			}
		}

		return $final_fee_to_add;
	}

	/**
	 * Get sum for fee by included/excluded categories (order version).
	 *
	 * @param WC_Order $order          Order object.
	 * @param float    $total_in_cart  Total.
	 * @param string   $fee_num        Fee number.
	 * @param string   $current_gateway Gateway ID.
	 * @return float
	 */
	public function get_sum_for_fee_by_included_and_excluded_cats( $order, $total_in_cart, $fee_num, $current_gateway ) {
		$core = pgbf_lite()->core;

		if ( 'fee_2' === $fee_num ) {
			$include_cats = ( ! empty( Settings::fee( $current_gateway, 'fee_2', 'cats_include', array() ) ) ) ?
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'include',
						'fee_num'         => 'fee_2_',
						'current_gateway' => $current_gateway,
					)
				) :
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'include',
						'fee_num'         => '',
						'current_gateway' => $current_gateway,
					)
				);
			$exclude_cats = ( ! empty( Settings::fee( $current_gateway, 'fee_2', 'cats_exclude', array() ) ) ) ?
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'exclude',
						'fee_num'         => 'fee_2_',
						'current_gateway' => $current_gateway,
					)
				) :
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'exclude',
						'fee_num'         => '',
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

		if ( ! empty( $include_cats ) && 'only_for_selected_products' === Settings::fee( $current_gateway, 'general', 'cats_include_calc_type', 'for_all_cart' ) ) {
			$sum_for_fee = 0;
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_cats  = $core->get_product_cats( $item['product_id'] );
				$the_intersect = array_intersect( $product_cats, $include_cats );
				if ( ! empty( $the_intersect ) ) {
					if ( ! $core->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
						$sum_for_fee += $item['line_total'];
					}
				}
			}
		} elseif ( ! empty( $exclude_cats ) && 'only_for_selected_products' === Settings::fee( $current_gateway, 'general', 'cats_exclude_calc_type', 'for_all_cart' ) ) {
			$sum_for_fee = 0;
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_cats  = $core->get_product_cats( $item['product_id'] );
				$the_intersect = array_intersect( $product_cats, $exclude_cats );
				if ( empty( $the_intersect ) ) {
					if ( ! $core->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
						$sum_for_fee += $item['line_total'];
					}
				}
			}
		} else {
			$sum_for_fee = $total_in_cart;
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( $core->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
					$sum_for_fee -= $item['line_total'];
				}
			}
		}

		return $sum_for_fee;
	}

	/**
	 * Apply fees by categories (order version).
	 *
	 * @param WC_Order $order          Order object.
	 * @param string   $fee_num        Fee number.
	 * @param string   $current_gateway Gateway ID.
	 * @param int      $info_product_id Product ID.
	 * @return bool
	 */
	public function do_apply_fees_by_categories( $order, $fee_num, $current_gateway, $info_product_id ) {
		$core = pgbf_lite()->core;

		if ( 0 != $info_product_id ) {
			if ( $core->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $info_product_id ) ) {
				return false;
			}
		} else {
			$do_override_global_fees_for_all_cart = true;
			$items_array = $order->get_items();
			if ( empty( $items_array ) ) {
				$do_override_global_fees_for_all_cart = false;
			}
			foreach ( $order->get_items() as $item_id => $item ) {
				if ( ! $core->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $item['product_id'] ) ) {
					$do_override_global_fees_for_all_cart = false;
					break;
				}
			}
			if ( $do_override_global_fees_for_all_cart ) {
				return false;
			}
		}

		if ( 'fee_2' === $fee_num ) {
			$include_cats = ( ! empty( Settings::fee( $current_gateway, 'fee_2', 'cats_include', array() ) ) ) ?
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'include',
						'fee_num'         => 'fee_2_',
						'current_gateway' => $current_gateway,
					)
				) :
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'include',
						'fee_num'         => '',
						'current_gateway' => $current_gateway,
					)
				);
			$exclude_cats = ( ! empty( Settings::fee( $current_gateway, 'fee_2', 'cats_exclude', array() ) ) ) ?
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'exclude',
						'fee_num'         => 'fee_2_',
						'current_gateway' => $current_gateway,
					)
				) :
				apply_filters(
					'alg_wc_checkout_fees_option',
					'',
					'cats',
					array(
						'type'            => 'exclude',
						'fee_num'         => '',
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
			if ( 0 != $info_product_id ) {
				$product_cats = $core->get_product_cats( $info_product_id );
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
						$product_cats  = $core->get_product_cats( $item['product_id'] );
						$the_intersect = array_intersect( $product_cats, $include_cats );
						if ( ! empty( $the_intersect ) ) {
							return true;
						}
					}
					return false;
				}
				if ( ! empty( $exclude_cats ) ) {
					if ( 'for_all_cart' === Settings::fee( $current_gateway, 'general', 'cats_exclude_calc_type', 'for_all_cart' ) ) {
						foreach ( $order->get_items() as $item_id => $item ) {
							$product_cats  = $core->get_product_cats( $item['product_id'] );
							$the_intersect = array_intersect( $product_cats, $exclude_cats );
							if ( ! empty( $the_intersect ) ) {
								return false;
							}
						}
						return true;
					} else {
						foreach ( $order->get_items() as $item_id => $item ) {
							$product_cats  = $core->get_product_cats( $item['product_id'] );
							$the_intersect = array_intersect( $product_cats, $exclude_cats );
							if ( empty( $the_intersect ) ) {
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
	 * Calculate the fee (order version).
	 *
	 * @param array  $args             Fee arguments.
	 * @param float  $final_fee_to_add Current total.
	 * @param float  $total_in_cart    Total.
	 * @param string $fee_num          Fee number.
	 * @param object $order            Order object.
	 * @return float
	 */
	public function calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, $fee_num, $order ) {
		$core = pgbf_lite()->core;

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
				$fixed_fee = $core->convert_currency( $fixed_fee );
				$new_fee   = $fixed_fee;
				break;
			case 'percent':
				if ( 0 != $args['product_id'] ) {
					$_product    = wc_get_product( $args['product_id'] );
					$sum_for_fee = $_product->get_price() * $args['product_qty'];
				} else {
					if ( (float) 0 === $total_in_cart ) {
						$cf_on_fees = apply_filters( 'alg_wc_not_to_calculate_on_fees', true );
						if ( $cf_on_fees ) {
							$fee_totals = 0;
							foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
								$fee_total           = $item->get_total();
								$fee_totals += $fee_total;
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

		if ( 0 != $min_fee && $new_fee < $min_fee ) {
			$new_fee = $min_fee;
		}
		if ( 0 != $max_fee && $new_fee > $max_fee ) {
			$new_fee = $max_fee;
		}

		if ( false !== $this->max_total_all_discounts ) {
			if ( $new_fee < $this->max_total_all_discounts ) {
				$new_fee = $this->max_total_all_discounts;
			}
			$this->max_total_all_discounts = (float) $this->max_total_all_discounts - (float) $new_fee;
			if ( $this->max_total_all_discounts > 0 ) {
				$this->max_total_all_discounts = 0;
			}
		}
		if ( false !== $this->max_total_all_fees ) {
			if ( $new_fee > $this->max_total_all_fees ) {
				$new_fee = $this->max_total_all_fees;
			}
			$this->max_total_all_fees = (float) $this->max_total_all_fees - (float) $new_fee;
			if ( $this->max_total_all_fees < 0 ) {
				$this->max_total_all_fees = 0;
			}
		}

		$final_fee_to_add += (float) $new_fee;
		if ( 'percent' === $fee_type && $this->is_yes( $args['do_round'] ) ) {
			$precision        = '' == $args['precision'] ? 0 : $args['precision'];
			$final_fee_to_add = round( $final_fee_to_add, $precision );
		}
		return $final_fee_to_add;
	}

	/**
	 * Render order‑pay page.
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
	 * Fix tax on negative fees in order edit (recalculating).
	 *
	 * @param object $fee Fee object.
	 * @param array  $calculate_tax_for Tax arguments.
	 */
	public function alg_wc_order_item_fee_after_calculate_taxes( $fee, $calculate_tax_for ) {
		if ( $fee->get_tax_status() != 'taxable' ) {
			if ( $fee->get_total() < 0 ) {
				$fee->set_tax_class( '' );
				$fee->set_tax_status( 'none' );
				$fee->set_total( $fee->get_total() );
				$fee->set_total_tax( 0 );
				$fee->set_taxes( array( 'total' => 0 ) );
				$fee->save();
			}
		}
	}
}