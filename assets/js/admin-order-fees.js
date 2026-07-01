/**
 * alg-payment-gateways-admin-order-page.
 *
 */
 jQuery(document).ready(() => {
    jQuery('body').on('change', 'input[id="alg_wc_cf_add_fees"]', function() {
        if(this.checked) {
            var checkbox_value = jQuery(this).val();
        } else {
            var checkbox_value = 'off';
        }
        //var order_id = jQuery('#post_ID').val();
        var data = {
            orderid: admin_order_fees_checkbox.order_id,
			checkbox_value: checkbox_value,
            security: admin_order_fees_checkbox.admin_fees_checkbox_nonce,
			action: 'admin_fees_checkbox',
		};
        jQuery.post( admin_order_fees_checkbox.ajax_url, data, function() {
		});
    });
});

jQuery(document).ready(function(){
    jQuery('#alg_woocommerce_checkout_fees_info_hook').parent('td').find('p').insertBefore('#alg_woocommerce_checkout_fees_info_hook');
    jQuery('#alg_woocommerce_checkout_fees_info_hook_priority').parent('td').find('p').insertBefore('#alg_woocommerce_checkout_fees_info_hook_priority');
    jQuery('#alg_woocommerce_checkout_fees_lowest_price_info_hook').parent('td').find('p').insertBefore('#alg_woocommerce_checkout_fees_lowest_price_info_hook');
    jQuery('#alg_woocommerce_checkout_fees_lowest_price_info_hook_priority').parent('td').find('p').insertBefore('#alg_woocommerce_checkout_fees_lowest_price_info_hook_priority');
    jQuery('#alg_woocommerce_checkout_fees_info_hook_priority').parent('td').find('p').removeClass('description');
    jQuery('#alg_woocommerce_checkout_fees_info_hook').parent('td').find('p').removeClass('description');
  });