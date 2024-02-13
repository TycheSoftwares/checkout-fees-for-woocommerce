(()=>{
    "use strict";
    const e = window.wp.element
      , t = JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":2,"name":"checkout-fees-for-woocommerce/add-fees","version":"1.0.0","title":"Payment Gateway Based Fees and Discounts","category":"woocommerce","parent":["woocommerce/checkout-shipping-address-block"],"attributes":{"lock":{"type":"object","default":{"remove":true,"move":true}}},"textdomain":"checkout-fees-for-woocommerce","editorScript":"file:./build/index.js"}')
      , o = window.wc.blocksCheckout
      , {registerCheckoutBlock: c} = (window.wp.i18n,
    wc.blocksCheckout);
    c({
        metadata: t,
        component: ({children: t, checkoutExtensionData: c})=>{
            const [a,n] = (0,
            e.useState)("")
              , {setExtensionData: s} = c;
            (0,
            e.useEffect)((()=>{
                jQuery(document).on("change", 'input[name="radio-control-wc-payment-method-options"]', (function(e) {
                    console.log("here"),
                    (0,
                    o.extensionCartUpdate)({
                        namespace: "checkout-fees-for-woocommerce",
                        data: {
                            shipping_method: document.querySelector('input[name="radio-control-0"]:checked').value,
                            payment_method: e.target.value
                        }
                    })
                }
                ))
            }
            ), []);
        }
    })
}
)();
