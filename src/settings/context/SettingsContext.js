/**
 * src/context/SettingsContext.js
 *
 * Global settings state — avoids repeated API calls on tab switches.
 * Loads all settings once on mount and distributes to screens via context.
 * Mirrors the COS Pro SettingsContext pattern exactly.
 */

import { createContext, useContext, useState, useEffect, useCallback } from '@wordpress/element';
import { getSettings, getGateways, getOptions } from '../api';

const SettingsContext = createContext();

export const useSettings = () => {
    const context = useContext( SettingsContext );
    if ( ! context ) {
        throw new Error( 'useSettings must be used within SettingsProvider' );
    }
    return context;
};

export const SettingsProvider = ( { children } ) => {
    const [ settings, setSettings ]   = useState( {} );
    const [ gateways, setGateways ]   = useState( [] );
    const [ options, setOptions ]     = useState( {} );
    const [ isLoading, setIsLoading ] = useState( true );
    const [ error, setError ]         = useState( null );

    const [ loadedSections, setLoadedSections ] = useState( {
        settings: false,
        gateways: false,
        options:  false,
    } );

    // ── Initial load ─────────────────────────────────────────────────────────

    const fetchAllData = useCallback( async () => {
        setIsLoading( true );
        setError( null );

        const [ settingsResult, gatewaysResult, optionsResult ] = await Promise.allSettled( [
            getSettings(),
            getGateways(),
            getOptions(),
        ] );

        if ( settingsResult.status === 'fulfilled' ) {
            setSettings( settingsResult.value || {} );
        } else {
            console.error( '[PGBF Pro] Failed to load settings:', settingsResult.reason );
            setSettings( {} );
        }

        if ( gatewaysResult.status === 'fulfilled' ) {
            setGateways( gatewaysResult.value || [] );
        } else {
            console.error( '[PGBF Pro] Failed to load gateways:', gatewaysResult.reason );
            setGateways( [] );
        }

        if ( optionsResult.status === 'fulfilled' ) {
            setOptions( optionsResult.value || {} );
        } else {
            console.error( '[PGBF Pro] Failed to load options:', optionsResult.reason );
            setOptions( {} );
        }

        setLoadedSections( { settings: true, gateways: true, options: true } );
        setIsLoading( false );
    }, [] );

    useEffect( () => {
        fetchAllData();
    }, [ fetchAllData ] );

    // ── Section fetchers (lazy, called by individual screens) ─────────────────

    const fetchSection = useCallback( async ( section ) => {
        if ( loadedSections[ section ] ) return;

        try {
            switch ( section ) {
                case 'settings': {
                    const data = await getSettings();
                    setSettings( data || {} );
                    setLoadedSections( ( prev ) => ( { ...prev, settings: true } ) );
                    break;
                }
                case 'gateways': {
                    const data = await getGateways();
                    setGateways( data || [] );
                    setLoadedSections( ( prev ) => ( { ...prev, gateways: true } ) );
                    break;
                }
                case 'options': {
                    const data = await getOptions();
                    setOptions( data || {} );
                    setLoadedSections( ( prev ) => ( { ...prev, options: true } ) );
                    break;
                }
                default:
                    break;
            }
        } catch ( err ) {
            console.error( `[PGBF Pro] Failed to fetch ${ section }:`, err );
            setError( err.message );
            setLoadedSections( ( prev ) => ( { ...prev, [ section ]: true } ) );
        }
    }, [ loadedSections ] );

    // ── Refresh helpers ───────────────────────────────────────────────────────

    /**
     * Refresh a single section unconditionally (bypasses loaded check).
     * Safe to call after any save/delete.
     */
    const refreshSection = useCallback( async ( section ) => {
        try {
            switch ( section ) {
                case 'settings': {
                    const data = await getSettings();
                    setSettings( data || {} );
                    setLoadedSections( ( prev ) => ( { ...prev, settings: true } ) );
                    break;
                }
                case 'gateways': {
                    const data = await getGateways();
                    setGateways( data || [] );
                    setLoadedSections( ( prev ) => ( { ...prev, gateways: true } ) );
                    break;
                }
                case 'options': {
                    const data = await getOptions();
                    setOptions( data || {} );
                    setLoadedSections( ( prev ) => ( { ...prev, options: true } ) );
                    break;
                }
                default:
                    break;
            }
        } catch ( err ) {
            console.error( `[PGBF Pro] Failed to refresh ${ section }:`, err );
            setError( err.message );
        }
    }, [] ); // No deps — reads API directly, never reads stale state.

    const refreshAll = useCallback( async () => {
        setLoadedSections( { settings: false, gateways: false, options: false } );
        await fetchAllData();
    }, [ fetchAllData ] );

    // ── Settings updater (used after save to keep context in sync) ────────────

    const updateSettingsData = useCallback( ( section, data ) => {
        switch ( section ) {
            case 'settings':
                setSettings( ( prev ) => ( { ...prev, ...data } ) );
                break;
            case 'gateways':
                setGateways( data );
                break;
            case 'options':
                setOptions( data );
                break;
            default:
                break;
        }
    }, [] );

    const value = {
        // Data
        settings,
        gateways,
        options,
        isLoading,
        error,
        loadedSections,

        // Actions
        fetchSection,
        refreshSection,
        refreshAll,
        updateSettingsData,

        // Convenience helpers
        getGeneralSettings:       () => settings?.general          || {},
        getGlobalExtraFeeSettings:() => settings?.global_extra_fee || {},
        getInfoSettings:          () => settings?.info             || {},
        getBinApisSettings:       () => settings?.bin_apis         || {},
        getGatewaysList:          () => gateways                   || [],
        getOptions:               () => options                    || {},
    };

    return (
        <SettingsContext.Provider value={ value }>
            { children }
        </SettingsContext.Provider>
    );
};
