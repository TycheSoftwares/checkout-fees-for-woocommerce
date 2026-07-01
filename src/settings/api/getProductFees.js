// src/api/getProductFees.js
import apiFetch from '@wordpress/api-fetch';

const getProductFees = async ( productId ) => {
    try {
        const response = await apiFetch( { path: `/pgbf-pro/v1/products/${ productId }/fees` } );
        return response?.data ?? {};
    } catch ( error ) {
        console.error( `[PGBF Pro] getProductFees(${ productId }) error:`, error );
        return {};
    }
};

export default getProductFees;
