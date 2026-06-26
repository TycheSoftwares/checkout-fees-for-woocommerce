// src/api/updateGatewaySettings.js
import apiFetch from '@wordpress/api-fetch';
import { clearGatewaySettingsCache } from './getGatewaySettings';

const updateGatewaySettings = async ( gatewayId, gatewayData ) => {
    try {
        const response = await apiFetch( {
            path  : `/pgbf-pro/v1/gateways/${ gatewayId }`,
            method: 'POST',
            data  : gatewayData,
        } );
        clearGatewaySettingsCache( gatewayId );
        return response?.data ?? null;
    } catch ( error ) {
        throw error;
    }
};

export default updateGatewaySettings;
