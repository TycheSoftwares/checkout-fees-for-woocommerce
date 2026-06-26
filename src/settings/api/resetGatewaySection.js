// src/api/resetGatewaySection.js
import apiFetch from '@wordpress/api-fetch';
import { clearGatewaySettingsCache } from './getGatewaySettings';

const resetGatewaySection = async ( gatewayId, section ) => {
    try {
        const response = await apiFetch( {
            path  : `/pgbf-pro/v1/gateways/${ gatewayId }/reset`,
            method: 'POST',
            data  : { section },
        } );
        clearGatewaySettingsCache( gatewayId );
        return response?.data ?? {};
    } catch ( error ) {
        throw error;
    }
};

export default resetGatewaySection;
