import metadata from './block.json';
import { ValidatedTextInput } from '@woocommerce/blocks-checkout';
import { __ } from '@wordpress/i18n';
import { useEffect, useState, useCallback } from '@wordpress/element';
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';

// Global import
const { registerCheckoutBlock } = wc.blocksCheckout;

const Block = ({ children, checkoutExtensionData }) => { 
    const { setExtensionData } = checkoutExtensionData;

    useEffect( () => {

        wp.hooks.addAction( 'experimental__woocommerce_blocks-checkout-set-active-payment-method', 'checkout-block-example', function( payment_method ) {
			var update_cart = extensionCartUpdate( {
                namespace: 'checkout-fees-for-woocommerce',
                data: {
                    payment_method: payment_method.value,
                },
            });
		} );

        wp.hooks.addAction( 'experimental__woocommerce_blocks-checkout-set-selected-shipping-rate', 'checkout-block-example', function( shipping ) {
            var update_cart = extensionCartUpdate( {
                namespace: 'checkout-fees-for-woocommerce',
                data: {
                    payment_method: shipping.storeCart.paymentMethods[0],
                },
            });
        } ); 

	}, [] );

    const onInputChange = useCallback(
		( value ) => {
		},
		[ setGiftMessage. setExtensionData ]
	)
}

const options = {
	metadata,
	component: Block
};

registerCheckoutBlock( options );