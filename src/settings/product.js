/**
 * src/product.js
 *
 * Per-product metabox entry point.
 * Mounts the React metabox into #pgbf-product-root on the product edit page.
 */

import { createRoot } from 'react-dom/client';
import ProductMetabox from './components/ProductMetabox';
import './app.scss';

window.addEventListener(
    'load',
    function () {
        const container = document.querySelector( '#pgbf-product-root' );
        if ( ! container ) return;

        const productId = parseInt( container.dataset.productId || '0', 10 );
        if ( ! productId ) {
            console.error( '[PGBF Pro] #pgbf-product-root missing data-product-id.' );
            return;
        }

        const root = createRoot( container );
        root.render( <ProductMetabox productId={ productId } /> );
    },
    false
);
