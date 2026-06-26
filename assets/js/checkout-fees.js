/**
 * checkout-fees.js.
 */

jQuery(($) => {
  const orderPayReferrer = $('input[name="_wp_http_referer"]').val();
  
  let referrerArr = '';

  if (undefined !== orderPayReferrer) {
    referrerArr = orderPayReferrer.split('/');
  }

  jQuery(($) => {
    function isSquareActive() {
      return (
        $('input[name="payment_method"][value="square_credit_card"]').length > 0 || $('input[name="payment_method"][value="square_ach_payment"]').length > 0
      );
    }
    if (isSquareActive()) {
      // Square plugin active → run your ACH reload logic.
      document.addEventListener('change', function(e) {
        const target = e.target;
        if (target && target.name === 'payment_method') {
          const selectedMethod = target.value;
          if (selectedMethod === 'square_ach_payment') {
            if (window.sessionStorage) {
              sessionStorage.removeItem('wc_fragments');
              sessionStorage.removeItem('wc_cart_hash');
            }
            e.stopImmediatePropagation();
            e.preventDefault();
            setTimeout(() => {
              window.location.reload(true);
            }, 300);
          }
        }
      }, true);
    } else {
      // Existing trigger on payment method click
      $('form#order_review').on('click', 'input[name="payment_method"]', function() {
        triggerUpdateFees();
      });
    }
  });


  function triggerUpdateFees(defaultPaymentMethod = null) {
    const order_id = (pgf_checkout_order_id.order_id) ? pgf_checkout_order_id.order_id : referrerArr[3];

    $('#place_order').prop('disabled', true);

    let paymentMethod = $('input[name="payment_method"]:checked').val();
    let currentPaymentMethod = paymentMethod;

    // Get Payment Title and strip out all html tags.
    let paymentMethodTitle = $(`label[for="payment_method_${paymentMethod}"]`).text().replace(/[\t\n]+/g, '').trim();

    // On visiting Pay for order page, take the payment method and payment title which are present in the order.
    if ('' !== pgf_checkout_order_id.payment_method) {
      paymentMethod = pgf_checkout_order_id.payment_method;
      paymentMethodTitle = $(`label[for="payment_method_${paymentMethod}"]`).text().replace(/[\t\n]+/g, '').trim();
    }

    const data = {
      payment_method: paymentMethod,
      payment_method_title: paymentMethodTitle,
      order_id: order_id,
      security: pgf_checkout_params.update_payment_method_nonce
    };

    // We need to set the payment method blank because when second time when it comes here on changing the payment method it should take that changed value and not the payment method present in the order.
    pgf_checkout_order_id.payment_method = '';
    $.post('?wc-ajax=update_fees', data, (response) => {
      $('#place_order').prop('disabled', false);
      if (response && response.fragments) {
        var tempDiv = $('<div>').html(response.fragments); // Create a temporary container
				var shopTableHtml = tempDiv.find('table.shop_table').prop('outerHTML');
				$('#order_review table.shop_table').html(shopTableHtml);
				$(`input[name="payment_method"][value=${paymentMethod}]`).prop('checked', true);
				$(`.payment_method_${paymentMethod}`).css('display', 'block');
				$(`div.payment_box:not(".payment_method_${paymentMethod}")`).filter(':visible').slideUp(0);
        
        // Fix for Woocommerce Square Payment Issue #114.
        if ('square_credit_card' === currentPaymentMethod && window.wc_square_credit_card_payment_form_handler && pgf_checkout_params && pgf_checkout_params.alg_wc_square_card_payment_args) {

          // Initialize constructor for building payment form elements.
          window.wc_square_credit_card_payment_form_handler = new WC_Square_Payment_Form_Handler(pgf_checkout_params.alg_wc_square_card_payment_args);
        }

        $(document.body).trigger('updated_checkout');
      }
    });
  }

  $('body').on('change', 'input[name="payment_method"]', () => {
    $(document.body).trigger('update_checkout');
  });

  $('body').on('payment_method_selected', () => {
    if ($('.woocommerce-order-pay').length === 0) {
      const methodSelected = $('input[name="payment_method"]:checked').val();
      $('input[name="payment_method"]').val(`${methodSelected}`).trigger('change');
    }
  });
});

// Credit card fields from WooCommerce Square plugin where duplicated multiple times.
(function($) {
  function cleanupSquareCardFields() {
    const $wrappers = $('[id^="single-card-wrapper-"]');
    if ($wrappers.length > 1) {
      $wrappers.slice(0, -1).remove(); // Keep the newest one only.
    }
  }
  function initMutationObserver() {
    const target = document.querySelector('form.woocommerce-checkout, form#order_review') || document.body;
    if (!target) return;
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (
            node.nodeType === 1 &&
            node.id &&
            node.id.startsWith('single-card-wrapper-')
          ) {
            cleanupSquareCardFields();
          }
        });
      });
    });
    observer.observe(target, {
      childList: true,
      subtree: true,
    });
  }
  $(document).ready(function() {
    // Run cleanup just in case one exists already.
    cleanupSquareCardFields();
    // Set up real-time watcher.
    initMutationObserver();
  });
  // Also clean up after WooCommerce events (fallback).
  $(document).on('updated_checkout updated_fragments', function () {
    cleanupSquareCardFields();
  });
})(jQuery);
