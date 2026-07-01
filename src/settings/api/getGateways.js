// src/api/getGateways.js
import apiFetch from '@wordpress/api-fetch';

let gatewaysCache = null;

const getGateways = async () => {
    if ( gatewaysCache ) return gatewaysCache;
    try {
        const response = await apiFetch( { path: '/pgbf-pro/v1/gateways' } );
        gatewaysCache  = response?.data ?? [];
        return gatewaysCache;
    } catch ( error ) {
        console.error( '[PGBF Pro] getGateways error:', error );
        return [];
    }
};

export const clearGatewaysCache = () => { gatewaysCache = null; };

export default getGateways;
