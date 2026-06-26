// src/api/getGatewaySettings.js
import apiFetch from '@wordpress/api-fetch';

// Cache per gateway id.
const cache = {};

const getGatewaySettings = async ( gatewayId ) => {
    if ( cache[ gatewayId ] ) return cache[ gatewayId ];
    try {
        const response       = await apiFetch( { path: `/pgbf-pro/v1/gateways/${ gatewayId }` } );
        cache[ gatewayId ]   = response?.data ?? {};
        return cache[ gatewayId ];
    } catch ( error ) {
        console.error( `[PGBF Pro] getGatewaySettings(${ gatewayId }) error:`, error );
        return {};
    }
};

export const clearGatewaySettingsCache = ( gatewayId ) => {
    if ( gatewayId ) {
        delete cache[ gatewayId ];
    } else {
        Object.keys( cache ).forEach( ( k ) => delete cache[ k ] );
    }
};

export default getGatewaySettings;
