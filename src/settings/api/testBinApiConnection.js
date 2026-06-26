// src/api/testBinApiConnection.js
import apiFetch from '@wordpress/api-fetch';

const testBinApiConnection = async ( provider, userId = '', apiKey = '' ) => {
    const response = await apiFetch( {
        path  : '/pgbf-pro/v1/bin-apis/test',
        method: 'POST',
        data  : { provider, user_id: userId, api_key: apiKey },
    } );
    return response?.data ?? null;
};

export default testBinApiConnection;
