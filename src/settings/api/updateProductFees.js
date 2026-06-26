// src/api/updateProductFees.js
import apiFetch from '@wordpress/api-fetch';

const updateProductFees = async ( productId, feesData ) => {
    try {
        const response = await apiFetch( {
            path  : `/pgbf-pro/v1/products/${ productId }/fees`,
            method: 'PUT',
            data  : feesData,
        } );
        return response?.data ?? null;
    } catch ( error ) {
        throw error;
    }
};

export default updateProductFees;
