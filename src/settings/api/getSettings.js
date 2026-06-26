// src/api/getSettings.js
import apiFetch from '@wordpress/api-fetch';

let settingsCache = null;

const getSettings = async () => {
    if ( settingsCache ) return settingsCache;
    try {
        const response = await apiFetch( { path: '/pgbf-pro/v1/settings' } );
        settingsCache  = response?.data ?? {};
        return settingsCache;
    } catch ( error ) {
        console.error( '[PGBF Pro] getSettings error:', error );
        return {};
    }
};

export const clearSettingsCache = () => { settingsCache = null; };

export default getSettings;
