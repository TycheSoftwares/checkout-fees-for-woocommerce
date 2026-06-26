// src/api/resetGatewayAll.js
import apiFetch from '@wordpress/api-fetch';
import { clearGatewaySettingsCache } from './getGatewaySettings';

const resetGatewayAll = async ( gatewayId ) => {
    try {
        const response = await apiFetch( {
            path  : `/pgbf-pro/v1/gateways/${ gatewayId }/reset-all`,
            method: 'POST',
        } );
        clearGatewaySettingsCache( gatewayId );
        return response?.data ?? {};
    } catch ( error ) {
        throw error;
    }
};

export default resetGatewayAll;
