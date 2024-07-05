const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData; // "wc/store/payment"
const { extensionCartUpdate } = window.wc.blocksCheckout;
const { subscribe, select } = wp.data;
let previouslyChosenPaymentMethod = '';

subscribe( function () {
	const chosenPaymentMethod = select( PAYMENT_STORE_KEY ).getActivePaymentMethod();
		extensionCartUpdate( {
			namespace: 'checkout-fees-for-woocommerce',
			data: {
                payment_method: chosenPaymentMethod,
            },
		} );
}, PAYMENT_STORE_KEY );