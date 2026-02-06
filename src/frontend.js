const { extensionCartUpdate } = window.wc.blocksCheckout;
const { subscribe, select, dispatch } = wp.data;
const { PAYMENT_STORE_KEY, CART_STORE_KEY, checkoutStore } = window.wc.wcBlocksData;

let previouslyChosenPaymentMethod = null;
let isUpdating = false;

subscribe( () => {
	const chosenPaymentMethod = select( PAYMENT_STORE_KEY )?.getActivePaymentMethod?.();
	if ( ! chosenPaymentMethod || chosenPaymentMethod === previouslyChosenPaymentMethod || isUpdating ) {
		return;
	}
	previouslyChosenPaymentMethod = chosenPaymentMethod;
	isUpdating = true;

	dispatch( checkoutStore ).disableCheckoutFor( async () => {
		/* Update fees */
		await extensionCartUpdate( {
			namespace: 'checkout-fees-for-woocommerce',
			data: {
				payment_method: chosenPaymentMethod,
			},
		} );

		/* Wait for totals recalculation */
		await new Promise( ( resolve ) => {
			const unsubscribe = subscribe( () => {
				const isCalculating =
					select( CART_STORE_KEY )?.isCalculating?.();

				if ( ! isCalculating ) {
					unsubscribe();
					resolve();
				}
			} );
		} );

		isUpdating = false;
	} );
}, PAYMENT_STORE_KEY );
