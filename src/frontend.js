/**
 * src/frontend.js
 *
 * Checkout block frontend script — user's existing file, unchanged.
 * Subscribes to the WC payment store and triggers extensionCartUpdate
 * when the customer switches payment method.
 *
 * Reads WC globals at runtime (no npm WC imports needed):
 *   window.wc.blocksCheckout → extensionCartUpdate
 *   window.wc.wcBlocksData   → PAYMENT_STORE_KEY, CART_STORE_KEY, checkoutStore
 *   wp.data                  → subscribe, select, dispatch
 */
const { extensionCartUpdate }                              = window.wc.blocksCheckout;
const { subscribe, select, dispatch }                      = wp.data;
const { PAYMENT_STORE_KEY, CART_STORE_KEY, checkoutStore } = window.wc.wcBlocksData;

let previouslyChosenPaymentMethod = null;
let isUpdating                    = false;

subscribe( () => {
	const chosenPaymentMethod = select( PAYMENT_STORE_KEY )?.getActivePaymentMethod?.();

	if ( ! chosenPaymentMethod || chosenPaymentMethod === previouslyChosenPaymentMethod || isUpdating ) {
		return;
	}

	previouslyChosenPaymentMethod = chosenPaymentMethod;
	isUpdating                    = true;

	dispatch( checkoutStore ).disableCheckoutFor( async () => {
		await extensionCartUpdate( {
			namespace: 'checkout-fees-for-woocommerce',
			data: { payment_method: chosenPaymentMethod },
		} );

		await new Promise( ( resolve ) => {
			const unsubscribe = subscribe( () => {
				if ( ! select( CART_STORE_KEY )?.isCalculating?.() ) {
					unsubscribe();
					resolve();
				}
			} );
		} );

		isUpdating = false;
	} );
}, PAYMENT_STORE_KEY );
