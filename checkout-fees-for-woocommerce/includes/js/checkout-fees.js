/**
 * checkout-fees.js.
 */

jQuery(($) => {
	const orderPayReferrer = $('input[name="_wp_http_referer"]').val();
	let referrerArr = '';
  
	if( orderPayReferrer != undefined ) {
	  referrerArr = orderPayReferrer.split('/');
	}
  
	  $('form#order_review').on('click', 'input[name="payment_method"]', () => {
	  const order_id = ( pgf_checkout_order_id.order_id ) ? pgf_checkout_order_id.order_id : referrerArr[3];
	  $('#place_order').prop('disabled', true);
		  var paymentMethod = $('input[name="payment_method"]:checked').val();
	  // On visiting Pay for order page, take the payment method which is present in the order.
	  if ( '' !== pgf_checkout_order_id.payment_method ) {
		paymentMethod = pgf_checkout_order_id.payment_method;
	  }
		  const data          = {
			  payment_method: paymentMethod,
			  order_id: order_id,
			  security: pgf_checkout_params.update_payment_method_nonce
		  };
	  // We need to set the payment method blank because when second time when it comes here on changing the payment method it should take that changed value and not the payment method present in the order.
	  pgf_checkout_order_id.payment_method = '';
	  $.post('?wc-ajax=update_fees', data, (response) => {
		$('#place_order').prop('disabled', false);
		if (response && response.fragments) {
		  $('#order_review').html(response.fragments);
		  $(`input[name="payment_method"][value=${paymentMethod}]`).prop('checked', true);
		  $(`.payment_method_${paymentMethod}`).css('display', 'block');
		  $(`div.payment_box:not(".payment_method_${paymentMethod}")`).filter(':visible').slideUp(0);
		}
	  });
	});

	$('body').on('change', 'input[name="payment_method"]', function() {
		$('body').trigger('update_checkout');
	});

	$('body').on('payment_method_selected', () => {
		if ($('.woocommerce-order-pay').length === 0) {
		  const methodSelected = $('input[name="payment_method"]:checked').val();
		  $('input[name="payment_method"]').val(`${methodSelected}`).trigger('change');
		}
	});
});
