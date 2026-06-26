// src/api/deleteAllData.js
import apiFetch from '@wordpress/api-fetch';

const deleteAllData = async () => {
    const response = await apiFetch( {
        path  : '/pgbf-pro/v1/data',
        method: 'DELETE',
    } );
    return response?.data ?? null;
};

export default deleteAllData;
