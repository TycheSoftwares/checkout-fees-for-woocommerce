// src/api/resetSection.js
import apiFetch from '@wordpress/api-fetch';
import { clearSettingsCache } from './getSettings';

const resetSection = async ( section ) => {
    try {
        const response = await apiFetch( {
            path  : '/pgbf-pro/v1/settings/reset',
            method: 'POST',
            data  : { section },
        } );
        clearSettingsCache();
        return response?.data ?? {};
    } catch ( error ) {
        throw error;
    }
};

export default resetSection;
