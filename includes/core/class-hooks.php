<?php
/**
 * Checkout Fees Core
 *
 * @version 2.5.4
 * @since   1.0.0
 * @author  Tyche Softwares
 *
 * @package checkout-fees-for-woocommerce
 */

namespace TycheSoftwares\PaymentGatewayFees\Lite;

use WC_Session;
use WC_Subscriptions_Cart;
use WC_Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Core checkout fee calculation class.
 */
class Checkout_Fees {

	/**
	 * Args manager instance.
	 *
	 * @var Checkout_Fees_Args
	 */
	public $args_manager;

	/**
	 * Merge fees flag.
	 *
	 * @var bool
	 */
	public $do_merge_fees;

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
	 * Base currency.
	 *
	 * @var string
	 */
	public $base_currency;

	/**
	 * Current currency.
	 *
	 * @var string
	 */
	public $current_currency;

	/**
	 * Fees array for merging.
	 *
	 * @var array
	 */
	public $fees = array();

	/**
	 * Added fees tracking.
	 *
	 * @var array
	 */
	public $fees_added = array();

	/**
	 * Added fee 2 tracking.
	 *
	 * @var array
	 */
	public $fees_added_2 = array();

	/**
	 * Last fee added.
	 *
	 * @var string
	 */
	public $last_fee_added = '';

	/**
	 * Last fee 2 added.
	 *
	 * @var string
	 */
	public $last_fee_added_2 = '';

	/**
	 * Is WC version below 3.2?
	 *
	 * @var bool
	 */
	public $is_wc_version_below_3_2;

	/**
	 * Last known current gateway.
	 *
	 * @var string
	 */
	public $last_known_current_gateway = '';

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

			add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_gateways_fees' ), PHP_INT_MAX );
			add_filter( 'woocommerce_cart_totals_get_fees_from_cart_taxes', array( $this, 'alg_woocommerce_checkout_fees_cart_totals_get_fees_from_cart_taxes' ), 10, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_script' ) );
			add_action( 'init', array( $this, 'register_script' ) );

			$this->args_manager  = new Checkout_Fees_Args();
			$this->base_currency = get_option( 'woocommerce_currency' );
			$this->do_merge_fees = Settings::general( 'merge_all_fees', false );

			add_filter( 'woocommerce_cart_totals_fee_html', array( $this, 'modify_fee_html_for_taxes' ), 10, 2 );
			add_filter( 'wc_stripe_params', array( $this, 'modify_stripe_params' ) );

			if ( in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
				add_filter( 'woocommerce_subscriptions_is_recurring_fee', array( $this, 'renewals_set_fees_recurring' ), 10, 3 );
			}

			add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'add_order_meta_fees' ), 11 );
		}
	}

	/**
	 * Convert currency amount.
	 *
	 * @param float $amount Amount to convert.
	 * @return float
	 */
	public function convert_currency( $amount ) {
		if ( ! isset( $this->current_currency ) ) {
			$this->current_currency = get_woocommerce_currency();
		}
		return apply_filters( 'wc_aelia_cs_convert', $amount, $this->base_currency, $this->current_currency );
	}
	
	/**
		 * Check if HPOS is enabled or not.
		 *
		 * @since 2.8.0
		 * return boolean true if enabled else false
		 */
		public function pgbf_wc_hpos_enabled() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
				if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
					return true;
				}
			}
			return false;
		}

	/**
	 * Get max ranges for discounts and fees.
	 */
	public function get_max_ranges() {
		$this->max_total_all_discounts = $this->convert_currency( Settings::general( 'max_total_discount', 0 ) );
		$this->max_total_all_fees      = $this->convert_currency( Settings::general( 'max_total_fee', 0 ) );

		if ( 0 == $this->max_total_all_discounts || '' === $this->max_total_all_discounts ) {
			$this->max_total_all_discounts = false;
		}
		if ( 0 == $this->max_total_all_fees || '' === $this->max_total_all_fees ) {
			$this->max_total_all_fees = false;
		}
	}

	/**
	 * Get product categories.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function get_product_cats( $product_id ) {
		$product_cats  = array();
		$product_terms = get_the_terms( $product_id, 'product_cat' );
		if ( is_array( $product_terms ) ) {
			foreach ( $product_terms as $term ) {
				$product_cats[] = $term->term_id;
			}
		}
		return $product_cats;
	}

	/**
	 * Check countries and states.
	 *
	 * @param string $current_gateway Gateway ID.
	 * @param string $fee_num         Fee number (optional).
	 * @return bool
	 */
	public function check_countries( $current_gateway, $fee_num = '' ) {
		if ( '' !== $fee_num ) {
			$fee_num = $fee_num . '_';
		}

		$customer_country = '';
		if ( null === WC()->customer ) {
			if ( isset( $_POST['post_type'] ) && 'shop_order' === $_POST['post_type'] && isset( $_POST['_billing_country'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$customer_country = sanitize_text_field( wp_unslash( $_POST['_billing_country'] ) );
			}
		} else {
			$customer_country = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ?
				WC()->customer->get_country() :
				WC()->customer->get_billing_country() );
		}

		$include_countries = $this->replace_country_sets(
			apply_filters(
				'alg_wc_checkout_fees_option',
				'',
				'countries',
				array(
					'type'            => 'include',
					'fee_num'         => $fee_num,
					'current_gateway' => $current_gateway,
				)
			)
		);
		if ( ! empty( $include_countries ) && ! in_array( $customer_country, $include_countries, true ) ) {
			return false;
		}

		$exclude_countries = $this->replace_country_sets(
			apply_filters(
				'alg_wc_checkout_fees_option',
				'',
				'countries',
				array(
					'type'            => 'exclude',
					'fee_num'         => $fee_num,
					'current_gateway' => $current_gateway,
				)
			)
		);
		if ( ! empty( $exclude_countries ) && in_array( $customer_country, $exclude_countries, true ) ) {
			return false;
		}

		if ( '' !== $fee_num ) {
			$customer_state = '';
			if ( null === WC()->customer ) {
				if ( isset( $_POST['post_type'] ) && 'shop_order' === $_POST['post_type'] && isset( $_POST['_billing_state'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$customer_state = sanitize_text_field( wp_unslash( $_POST['_billing_state'] ) );
				}
			} else {
				$customer_state = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ?
					WC()->customer->get_state() :
					WC()->customer->get_billing_state() );
			}

			$include_states = apply_filters(
				'alg_wc_checkout_fees_option',
				'',
				'states',
				array(
					'type'            => 'include',
					'fee_num'         => $fee_num,
					'current_gateway' => $current_gateway,
				)
			);
			if ( ! empty( $include_states ) && ! in_array( $customer_state, $include_states, true ) ) {
				return false;
			}

			$exclude_states = apply_filters(
				'alg_wc_checkout_fees_option',
				'',
				'states',
				array(
					'type'            => 'exclude',
					'fee_num'         => $fee_num,
					'current_gateway' => $current_gateway,
				)
			);
			if ( ! empty( $exclude_states ) && in_array( $customer_state, $exclude_states, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Replace country sets with actual country codes.
	 *
	 * @param array $countries Countries array.
	 * @return array
	 */
	public function replace_country_sets( $countries ) {
		if ( ! empty( $countries ) ) {
			foreach ( alg_checkout_fees_get_country_set_countries() as $id => $set ) {
				if ( in_array( $id, $countries, true ) ) {
					$countries = array_merge( $countries, $set );
				}
			}
		}
		return $countries;
	}

	/**
	 * Register checkout script.
	 */
	public function register_script() {
		wp_register_script(
			'alg-payment-gateways-checkout',
			PGBF_LITE_PLUGIN_URL . '/assets/js/checkout-fees.js',
			array( 'jquery' ),
			pgbf_lite()->version,
			true
		);
	}

	/**
	 * Enqueue checkout script.
	 */
	public function enqueue_checkout_script() {
		global $wp;
		if ( ! is_checkout() ) {
			return;
		}

		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			if ( isset( $wp->query_vars['order-pay'] ) && absint( $wp->query_vars['order-pay'] ) > 0 ) {
				$order_id       = absint( $wp->query_vars['order-pay'] );
				if ( $this->pgbf_wc_hpos_enabled() ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						if ( $order->meta_exists( '_payment_method' ) ) {
							$payment_method = $order->get_meta( '_payment_method' );
						} else {
							$payment_method = $order->get_payment_method();
						}
					}
				} else {
					$payment_method = get_post_meta( $order_id, '_payment_method', true );
				}
				if ( '' !== get_query_var( 'order-pay' ) ) {
					wp_localize_script(
						'alg-payment-gateways-checkout',
						'pgf_checkout_order_id',
						array(
							'order_id'       => get_query_var( 'order-pay' ),
							'payment_method' => $payment_method,
						)
					);
				}
			}
		}

		wp_enqueue_script( 'alg-payment-gateways-checkout' );
		wp_localize_script(
			'alg-payment-gateways-checkout',
			'pgf_checkout_params',
			array(
				'update_payment_method_nonce' => wp_create_nonce( 'update-payment-method' ),
			)
		);
	}

	/**
	 * Get current gateway.
	 *
	 * @return string
	 */
	public function get_current_gateway() {
		$current_gateway = WC()->session->chosen_payment_method;
		if ( '' === $current_gateway ) {
			$current_gateway = ( ! empty( $_REQUEST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['payment_method'] ) ) : '' );// phpcs:ignore WordPress.Security.NonceVerification
			if ( '' === $current_gateway ) {
				$current_gateway = ( isset( $this->last_known_current_gateway ) ? $this->last_known_current_gateway : get_option( 'woocommerce_default_gateway', '' ) );
			}
		}
		$current_gateway                  = apply_filters( 'alg_wc_checkout_current_gateway', $current_gateway );
		$this->last_known_current_gateway = $current_gateway;
		return $current_gateway;
	}

	/**
	 * Add gateway fees to cart.
	 *
	 * @param WC_Cart $the_cart Cart object.
	 */
	public function add_gateways_fees( $the_cart ) {
		if ( ! function_exists( 'WC' ) || ! method_exists( WC(), 'payment_gateways' ) || null === WC()->payment_gateways() ) {
			return;
		}

		if ( Settings::general( 'hide_on_cart', false ) && is_cart() ) {
			return;
		}

		$current_gateway = apply_filters( 'alg_wc_add_default_gateway_on_cart', $this->get_current_gateway() );
		if ( ! $current_gateway ) {
			return;
		}
		// Added this check for klarna payment method, as in the $current_gateway name of the Klarna payment was not coming proper, hence we add this check and pass the correct name in the $current_gateway.
		$klarna_payment = 'klarna_payments';
		if ( strpos( $current_gateway, $klarna_payment ) !== false ) {
			$current_gateway = 'klarna_payments';
		}
		if ( strpos( $current_gateway, 'alma_in_page' ) !== false ) {
			$current_gateway = 'alma';
		}
		if ( strpos( $current_gateway, 'xpay_paypal' ) !== false ) {
			$current_gateway = 'xpay';
		}
		if ( strpos( $current_gateway, 'everypay' ) !== false ) {
			$current_gateway = 'everypay';
		}
		// Subscription double-call guard.
		if ( in_array( 'woocommerce-subscriptions/woocommerce-subscriptions.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			$cart_contains_subscription = WC_Subscriptions_Cart::cart_contains_subscription();
			if ( $cart_contains_subscription && ( count( $this->fees_added ) > 0 || count( $this->fees_added_2 ) > 0 ) &&
				( ( is_checkout() && ( empty( $_POST['woocommerce-process-checkout-nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['woocommerce-process-checkout-nonce'] ), 'woocommerce-process_checkout' ) ) ) || is_cart() ) ) {
				return;
			}
		}

		$this->get_max_ranges();

		if ( $this->do_merge_fees ) {
			$this->fees = array();
		}

		// Global fees.
		$do_add_fees_global = $this->check_countries( $current_gateway );
		if ( $do_add_fees_global ) {
			$args = $this->args_manager->get_the_args_global( $current_gateway );
			$this->maybe_add_cart_fee( $args );
		}

		// Per-product fees.
		if ( Settings::general( 'per_product_enabled', false ) &&
			( 'bacs' === $current_gateway || apply_filters( 'alg_wc_checkout_fees_option', false, 'per_product' ) ) ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$args = $this->args_manager->get_the_args_local( $current_gateway, $values['product_id'], $values['variation_id'], $values['quantity'] );
				$this->maybe_add_cart_fee( $args );
			}
		}

		// Super-global fee.
		if ( Settings::global_fee( 'enabled', false ) ) {
			$do_add = true;
			if ( Settings::global_fee( 'as_extra_only', false ) ) {
				$current_fees = ( $this->do_merge_fees ? $this->fees : WC()->cart->get_fees() );
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
					$global_fee = ( WC()->cart->cart_contents_total + WC()->cart->shipping_total ) / 100 * $global_value;
				}
				if ( $this->do_merge_fees ) {
					$this->fees[] = array(
						'title'     => $global_title,
						'value'     => $global_fee,
						'taxable'   => false,
						'tax_class' => '',
					);
				} else {
					WC()->cart->add_fee( $global_title, $global_fee );
					$this->fees_added[]   = $global_title;
					$this->last_fee_added = $global_title;
				}
			}
		}

		// Merge fees.
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
				WC()->cart->add_fee( $merged_fee['title'], $merged_fee['value'], $merged_fee['taxable'], $merged_fee['tax_class'] );
				$this->fees_added[]   = $merged_fee['title'];
				$this->last_fee_added = $merged_fee['title'];
			}
		}

		do_action( 'alg_wc_checkout_fees_after_fees_added', $this );
	}

	/**
	 * Calculate the fee amount.
	 *
	 * @param array  $args           Fee arguments.
	 * @param float  $final_fee_to_add Current fee total.
	 * @param float  $total_in_cart   Cart total.
	 * @param string $fee_num         Fee number.
	 * @return float
	 */
	public function calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, $fee_num ) {
		if ( 'fee_2' === $fee_num ) {
			$fee_type  = $args['fee_type_2'];
			$fee_value = $args['fee_value_2'];
			$min_fee   = $args['min_fee_2'];
			$max_fee   = $args['max_fee_2'];
		} else {
			$fee_type  = $args['fee_type'];
			$fee_value = $args['fee_value'];
			$min_fee   = $args['min_fee'];
			$max_fee   = $args['max_fee'];
		}

		$new_fee = 0;
		switch ( $fee_type ) {
			case 'fixed':
				$fixed_fee = ( 'by_quantity' === $args['fixed_usage'] ) ? (float) $fee_value * $args['product_qty'] : $fee_value;
				$fixed_fee = $this->convert_currency( $fixed_fee );
				$new_fee   = $fixed_fee;
				break;
			case 'percent':
				if ( 0 != $args['product_id'] ) {
					$_product    = wc_get_product( $args['product_id'] );
					$sum_for_fee = $_product->get_price() * $args['product_qty'];
				} else {
					// IF cart has depsoit then use it as base for fee calculation instead of total.
					$deposit_base  = 0;
					$deposit_found = false;
					foreach ( WC()->cart->get_cart() as $_item ) {
						if ( isset( $_item['deposit_amount'] ) && (float) $_item['deposit_amount'] > 0 ) {
							$deposit_base += (float) $_item['deposit_amount'] * (int) $_item['quantity'];
							$deposit_found = true;
						} else {
							$deposit_base += (float) $_item['line_total'];
						}
					}
					$sum_for_fee = $deposit_found ? $deposit_base : $total_in_cart;
				}
				$new_fee = ( (float) $fee_value / 100 ) * $sum_for_fee;
				break;
		}

		// Min fee.
		if ( 0 != $min_fee && $new_fee < $min_fee ) {
			$new_fee = $min_fee;
		}
		if ( '' === $max_fee ) {
			$max_fee = 0;
		}
		// Max fee.
		if ( $max_fee > 0 ) {
			if ( 0 != $max_fee && $new_fee > $max_fee ) {
				$new_fee = $max_fee;
			}
		} elseif ( $max_fee < 0 ) {
			if ( 0 != $max_fee && $new_fee < $max_fee ) {
				$new_fee = $max_fee;
			}
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
			$precision        = '' === $args['precision'] ? 0 : $args['precision'];
			$final_fee_to_add = round( $final_fee_to_add, $precision );
		}

		return $final_fee_to_add;
	}

	/**
	 * Remove tax from negative fees.
	 *
	 * @param array  $taxes Taxes array.
	 * @param object $fee  Fee object.
	 * @return array
	 */
	public function alg_woocommerce_checkout_fees_cart_totals_get_fees_from_cart_taxes( $taxes, $fee ) {
		if ( $fee->object->amount < 0 && ! $fee->taxable ) {
			$taxes = array();
		}
		return $taxes;
	}

	/**
	 * Get sum for fee by included and excluded categories.
	 *
	 * @param float  $total_in_cart    Cart total.
	 * @param string $fee_num          Fee number.
	 * @param string $current_gateway  Gateway ID.
	 * @return float
	 */
	public function get_sum_for_fee_by_included_and_excluded_cats( $total_in_cart, $fee_num, $current_gateway ) {
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
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product_cats  = $this->get_product_cats( $values['product_id'] );
				$the_intersect = array_intersect( $product_cats, $include_cats );
				if ( ! empty( $the_intersect ) ) {
					if ( ! $this->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $values['product_id'] ) ) {
						$sum_for_fee += $values['line_total'];
					}
				}
			}
		} elseif ( ! empty( $exclude_cats ) && 'only_for_selected_products' === Settings::fee( $current_gateway, 'general', 'cats_exclude_calc_type', 'for_all_cart' ) ) {
			$sum_for_fee = 0;
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				$product_cats  = $this->get_product_cats( $values['product_id'] );
				$the_intersect = array_intersect( $product_cats, $exclude_cats );
				if ( empty( $the_intersect ) ) {
					if ( ! $this->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $values['product_id'] ) ) {
						$sum_for_fee += $values['line_total'];
					}
				}
			}
		} else {
			$sum_for_fee = $total_in_cart;
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				if ( $this->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $values['product_id'] ) ) {
					$sum_for_fee -= $values['line_total'];
				}
			}
		}

		return $sum_for_fee;
	}

	/**
	 * Check if override global fees is enabled for product.
	 *
	 * @param string $fee_num        Fee number.
	 * @param string $current_gateway Gateway ID.
	 * @param int    $product_id      Product ID.
	 * @return bool
	 */
	public function is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $product_id ) {
		$enabled = Product_Fees_Helper::get_field( $product_id, $current_gateway, 'root', 'enabled', false );
		$override_section = ( 'fee_2' === $fee_num ) ? 'fee_2' : 'fee_1';
		$override = Product_Fees_Helper::get_field( $product_id, $current_gateway, $override_section, 'override_global', 'no' );
		return ( $this->is_yes( $enabled ) && $this->is_yes( $override ) );
	}

	/**
	 * Apply fees by categories.
	 *
	 * @param string $fee_num        Fee number.
	 * @param string $current_gateway Gateway ID.
	 * @param int    $info_product_id Product ID (0 for cart scope).
	 * @return bool
	 */
	public function do_apply_fees_by_categories( $fee_num, $current_gateway, $info_product_id ) {
		if ( 0 != $info_product_id ) {
			if ( $this->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $info_product_id ) ) {
				return false;
			}
		} else {
			$do_override_global_fees_for_all_cart = true;
			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
				if ( ! $this->is_override_global_fees_enabled_for_product( $fee_num, $current_gateway, $values['product_id'] ) ) {
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
				$product_cats = $this->get_product_cats( $info_product_id );
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
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
						$product_cats  = $this->get_product_cats( $values['product_id'] );
						$the_intersect = array_intersect( $product_cats, $include_cats );
						if ( ! empty( $the_intersect ) ) {
							return true;
						}
					}
					return false;
				}
				if ( ! empty( $exclude_cats ) ) {
					if ( 'for_all_cart' === Settings::fee( $current_gateway, 'general', 'cats_exclude_calc_type', 'for_all_cart' ) ) {
						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
							$product_cats  = $this->get_product_cats( $values['product_id'] );
							$the_intersect = array_intersect( $product_cats, $exclude_cats );
							if ( ! empty( $the_intersect ) ) {
								return false;
							}
						}
						return true;
					} else {
						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
							$product_cats  = $this->get_product_cats( $values['product_id'] );
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
	 * Check if WC version is below 3.2.
	 *
	 * @return bool
	 */
	public function is_wc_version_below_3_2() {
		if ( ! isset( $this->is_wc_version_below_3_2 ) ) {
			$this->is_wc_version_below_3_2 = version_compare( get_option( 'woocommerce_version', null ), '3.2.0', '<' );
		}
		return $this->is_wc_version_below_3_2;
	}

	/**
	 * Get the fee amount.
	 *
	 * @param array   $args           Fee arguments.
	 * @param string  $fee_num        Fee number.
	 * @param float   $total_in_cart  Cart total.
	 * @param bool    $is_info_only   Info only flag.
	 * @param int     $info_product_id Product ID for info.
	 * @return float
	 */
	public function get_the_fee( $args, $fee_num, $total_in_cart = 0, $is_info_only = false, $info_product_id = 0 ) {
		$final_fee_to_add = 0;

		if ( '' !== $args['current_gateway'] && $this->is_yes( $args['is_enabled'] ) ) {
			if ( 0 == $total_in_cart ) {
				$total_in_cart = ( $this->is_yes( $args['exclude_shipping'] ) ) ? WC()->cart->cart_contents_total : WC()->cart->cart_contents_total + WC()->cart->shipping_total;
				if ( $this->is_yes( $args['add_taxes'] ) ) {
					$tax_total = ( $this->is_wc_version_below_3_2() ? WC_Tax::get_tax_total( WC()->cart->taxes ) : array_sum( WC()->cart->get_cart_contents_taxes() ) );
					if ( $this->is_yes( $args['exclude_shipping'] ) ) {
						$total_in_cart += $tax_total;
					} else {
						$shipping_tax_total = ( $this->is_wc_version_below_3_2() ? WC_Tax::get_tax_total( WC()->cart->shipping_taxes ) : array_sum( WC()->cart->get_shipping_taxes() ) );
						$total_in_cart     += $tax_total + $shipping_tax_total;
					}
				}
				if ( WC()->cart->get_fees() ) {
					foreach ( WC()->cart->get_fees() as $fee ) {
						$apply_fee = apply_filters( 'external_fee_include_in_gateway_fee', false, $fee );
						if ( $apply_fee ) {
							$total_in_cart += $fee->amount;
						}
					}
				}
				if ( ! empty( WC()->cart->credit_used ) && is_array( WC()->cart->credit_used ) ) {
					$total_in_cart -= array_sum( WC()->cart->credit_used );
				}
			}

			if ( '' === $args['min_cart_amount'] ) {
				$args['min_cart_amount'] = 0;
			}
			if ( '' === $args['max_cart_amount'] ) {
				$args['max_cart_amount'] = 0;
			}

			if ( $total_in_cart >= $args['min_cart_amount'] && ( 0 == $args['max_cart_amount'] || $total_in_cart <= $args['max_cart_amount'] ) ) {
				if ( 0 != $args['fee_value'] && 'fee_2' !== $fee_num ) {
					if ( 'local' === $args['fee_scope'] || $this->do_apply_fees_by_categories( 'fee_1', $args['current_gateway'], $info_product_id ) ) {
						if ( ! $is_info_only && 'global' === $args['fee_scope'] ) {
							$total_in_cart = $this->get_sum_for_fee_by_included_and_excluded_cats( $total_in_cart, 'fee_1', $args['current_gateway'] );
						}
						if ( ( 'local' === $args['fee_scope'] || $this->check_countries( $args['current_gateway'], 'fee_1' ) ) &&
							( $is_info_only || $this->do_apply_fees_by_coupons( $args['coupons_rule'] ) ) ) {
							$final_fee_to_add = $this->calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, 'fee_1' );
						}
					}
				}

				if ( 0 != $args['fee_value_2'] && 'fee_1' !== $fee_num ) {
					if ( 'local' === $args['fee_scope'] || $this->do_apply_fees_by_categories( 'fee_2', $args['current_gateway'], $info_product_id ) ) {
						if ( ! $is_info_only && 'global' === $args['fee_scope'] ) {
							$total_in_cart = $this->get_sum_for_fee_by_included_and_excluded_cats( $total_in_cart, 'fee_2', $args['current_gateway'] );
						}
						if ( ( 'local' === $args['fee_scope'] || $this->check_countries( $args['current_gateway'], 'fee_2' ) ) &&
							( $is_info_only || $this->do_apply_fees_by_coupons( $args['coupons_rule_2'] ) ) ) {
							$final_fee_to_add = $this->calculate_the_fee( $args, $final_fee_to_add, $total_in_cart, 'fee_2' );
						}
					}
				}
			}
		}

		$final_fee_to_add = apply_filters( 'alg_wc_gateway_fee_before_final_fee', $final_fee_to_add, $args, $this );

		return $final_fee_to_add;
	}

	/**
	 * Apply fees by coupons rule.
	 *
	 * @param string $coupon_rule Coupon rule.
	 * @return bool
	 */
	public function do_apply_fees_by_coupons( $coupon_rule ) {
		switch ( $coupon_rule ) {
			case 'only_if_no_coupons':
				return ! WC()->cart->has_discount();
			case 'only_if_coupons':
				return WC()->cart->has_discount();
			default:
				return true;
		}
	}

	/**
	 * Recheck fee title to avoid duplicates.
	 *
	 * @param string $fee_text Fee text.
	 * @param array  $fees     Existing fees.
	 * @return string
	 */
	public function recheck_fee_title( $fee_text, $fees ) {
		if ( is_checkout() ) {
			return $fee_text;
		}
		foreach ( $fees as $fee ) {
			if ( $fee_text === $fee->name ) {
				$fee_text .= ' #2';
			}
		}
		return $fee_text;
	}

	/**
	 * Maybe add cart fee.
	 *
	 * @param array $args Fee arguments.
	 */
	public function maybe_add_cart_fee( $args ) {
		if ( $args['fee_text'] === $args['fee_text_2'] || '' === $args['fee_text_2'] ) {
			$final_fee_to_add   = $this->get_the_fee( $args, 'fee_both' );
			$final_fee_to_add_2 = 0;
		} else {
			$final_fee_to_add   = $this->get_the_fee( $args, 'fee_1' );
			$final_fee_to_add_2 = $this->get_the_fee( $args, 'fee_2' );
		}

		if ( 0 != $final_fee_to_add || 0 != $final_fee_to_add_2 ) {
			$taxable        = $this->is_yes( $args['is_taxable'] );
			$tax_class_name = '';
			if ( $taxable ) {
				$tax_class_names = array_merge( array( '' ), WC_Tax::get_tax_classes() );
				$tax_class_name  = ( isset( $tax_class_names[ $args['tax_class_id'] ] ) ? $tax_class_names[ $args['tax_class_id'] ] : '' );
			}
			$fees = WC()->cart->get_fees();

			if ( 0 != $final_fee_to_add ) {
				if ( $this->do_merge_fees ) {
					$this->fees[] = array(
						'title'     => $args['fee_text'],
						'value'     => $final_fee_to_add,
						'taxable'   => $taxable,
						'tax_class' => $tax_class_name,
					);
				} else {
					$fee_text = $this->recheck_fee_title( $args['fee_text'], $fees );
					WC()->cart->add_fee( $fee_text, $final_fee_to_add, $taxable, $tax_class_name );
					$this->fees_added[]   = $args['fee_text'];
					$this->last_fee_added = $args['fee_text'];
				}
			}

			if ( 0 != $final_fee_to_add_2 ) {
				if ( $this->do_merge_fees ) {
					$this->fees[] = array(
						'title'     => $args['fee_text_2'],
						'value'     => $final_fee_to_add_2,
						'taxable'   => $taxable,
						'tax_class' => $tax_class_name,
					);
				} else {
					$fee_text_2 = $this->recheck_fee_title( $args['fee_text_2'], $fees );
					WC()->cart->add_fee( $fee_text_2, $final_fee_to_add_2, $taxable, $tax_class_name );
					$this->fees_added_2[]   = $args['fee_text_2'];
					$this->last_fee_added_2 = $args['fee_text_2'];
				}
			}
		}
	}

	/**
	 * Modify fee HTML for taxes display.
	 *
	 * @param string $cart_fee_html Fee HTML.
	 * @param object $fees          Fee object.
	 * @return string
	 */
	public function modify_fee_html_for_taxes( $cart_fee_html, $fees ) {
		$tax_data = WC()->cart->get_tax_totals();
		if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) && isset( $fees->tax ) && $fees->tax > 0 &&
			( in_array( $fees->name, $this->fees_added, true ) || in_array( $fees->name, $this->fees_added_2, true ) ) ) {
			$tax_html_parts = array();
			$total_tax_in_cart = 0;
			foreach ( $tax_data as $tax ) {
				$total_tax_in_cart += $tax->amount;
			}
			foreach ( $tax_data as $tax ) {
				$share = ( $total_tax_in_cart > 0 )
					? ( $tax->amount / $total_tax_in_cart ) * $fees->tax
					: 0;
				$share = round( $share, wc_get_price_decimals() );
				if ( $share > 0 ) {
					$tax_html_parts[] = wc_price( $share ) . ' ' . $tax->label;
				}
			}
			if ( ! empty( $tax_html_parts ) ) {
				$cart_fee_html .= '<small class="includes_tax">' .
					sprintf(
						__( '(includes %s)', 'checkout-fees-for-woocommerce' ),
						implode( ', ', $tax_html_parts )
					) .
					'</small>';
			}
		}
		$cart_fee_html = str_replace( '-', '', $cart_fee_html );
		if ( 0 > $fees->amount ) {
			$cart_fee_html = '-' . rtrim( $cart_fee_html );
		}
		return $cart_fee_html;
	}

	/**
	 * Set fees as recurring for subscriptions.
	 *
	 * @param bool   $recurring Recurring flag.
	 * @param object $fees      Fee object.
	 * @param object $cart      Cart object.
	 * @return bool
	 */
	public function renewals_set_fees_recurring( $recurring, $fees, $cart ) {
		$recurring = ( 0 != $fees->total && ( in_array( $fees->name, $this->fees_added, true ) || in_array( $fees->name, $this->fees_added_2, true ) ) ) ? true : $recurring;
		return $recurring;
	}

	/**
	 * Add order meta for fees.
	 *
	 * @param int $order_id Order ID.
	 */
	public function add_order_meta_fees( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			foreach ( $order->get_items( 'fee' ) as $item_id => $item ) {
				if ( '' !== $this->last_fee_added && in_array( $item->get_name(), $this->fees_added, true ) ) {
					wc_add_order_item_meta( $item_id, '_last_added_fee', $item->get_name() );
				}
				if ( '' !== $this->last_fee_added_2 && in_array( $item->get_name(), $this->fees_added_2, true ) ) {
					wc_add_order_item_meta( $item_id, '_last_added_fee_2', $item->get_name() );
				}
			}
		}
	}

	/**
	 * Modify Stripe payment params.
	 *
	 * @param array $stripe_params Stripe parameters.
	 * @return array
	 */
	public function modify_stripe_params( $stripe_params ) {
		if ( is_checkout() ) {
			$stripe_params['is_checkout'] = 'yes';
		}
		return $stripe_params;
	}
}


// Backward-compat alias.
if ( ! class_exists( 'Alg_WC_Checkout_Fees' ) ) {
	class_alias( __NAMESPACE__ . '\\Checkout_Fees', 'Alg_WC_Checkout_Fees' );
}

return new Checkout_Fees();