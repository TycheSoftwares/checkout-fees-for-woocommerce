// src/api/searchCategories.js
import apiFetch from '@wordpress/api-fetch';

const searchCategories = async ( search = '' ) => {
    try {
        const path     = search
            ? `/pgbf-pro/v1/options/categories?search=${ encodeURIComponent( search ) }`
            : '/pgbf-pro/v1/options/categories';
        const response = await apiFetch( { path } );
        return response?.data ?? [];
    } catch ( error ) {
        console.error( '[PGBF Pro] searchCategories error:', error );
        return [];
    }
};

export default searchCategories;
