// src/api/getOptions.js
import apiFetch from '@wordpress/api-fetch';

let optionsCache = null;

const getOptions = async () => {
    if ( optionsCache ) return optionsCache;
    try {
        const response = await apiFetch( { path: '/pgbf-pro/v1/options' } );
        optionsCache   = response?.data ?? {};
        return optionsCache;
    } catch ( error ) {
        console.error( '[PGBF Pro] getOptions error:', error );
        return {};
    }
};

export const clearOptionsCache = () => { optionsCache = null; };

export default getOptions;
