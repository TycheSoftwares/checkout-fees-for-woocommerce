/**
 * src/screens/BinApis.js — BIN APIs Configuration settings screen.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ }                  from '@wordpress/i18n';
import {
    Button, CheckboxControl, SelectControl,
    __experimentalHStack as HStack,
    __experimentalNumberControl as NumberControl,
    __experimentalInputControl as InputControl,
    __experimentalText as Text,
    Spinner, withNotices,
    __experimentalVStack as VStack,
    __experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import ProNotice from '../components/ProNotice';
import { useForm } from 'react-hook-form';
import SettingsCard from '../components/SettingsCard';
import HelpTip      from '../components/HelpTip';
import { updateSettings, resetSection } from '../api';
import { useSettings } from '../context/SettingsContext';

const DEFAULTS = {
    enabled      : false,
    provider     : 'binlist',
    binlist      : { cache_enabled: true, cache_duration_hours: 24 },
    neutrinoapi  : { user_id: '', api_key: '' },
    card_section : {
        enabled       : false,
        show_type     : false,
        show_scheme   : false,
        show_location : false,
        show_bank_name: false,
    },
};

function BinApis( { noticeOperations, noticeUI } ) {
    const [ showLoader,   setShowLoader   ] = useState( false );
    const [ isResetOpen,  setIsResetOpen  ] = useState( false );

    const { settings, isLoading: globalLoading, loadedSections, fetchSection, updateSettingsData } = useSettings();

    const { control, handleSubmit, reset, watch } = useForm( { defaultValues: DEFAULTS } );

    const provider       = watch( 'provider' );
    const binApisEnabled = watch( 'enabled' );

    useEffect( () => {
        if ( settings?.bin_apis && loadedSections.settings ) {
            reset( { ...DEFAULTS, ...settings.bin_apis } );
        }
    }, [ settings, loadedSections.settings, reset ] );

    useEffect( () => {
        if ( ! loadedSections.settings ) fetchSection( 'settings' );
    }, [] ); // eslint-disable-line

    const onSubmit = async ( data ) => {
        setShowLoader( true );
        try {
            const merged = { ...( settings || {} ), bin_apis: data };
            await updateSettings( merged );
            updateSettingsData( 'settings', merged );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Settings saved successfully.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Error saving settings.', 'checkout-fees-for-woocommerce' ) } );
        } finally { setShowLoader( false ); }
    };

    const onReset = async () => {
        setIsResetOpen( false );
        setShowLoader( true );
        try {
            const defaults = await resetSection( 'bin_apis' );
            reset( { ...DEFAULTS, ...defaults } );
            updateSettingsData( 'settings', { ...( settings || {} ), bin_apis: defaults } );
            noticeOperations.createNotice( { status: 'success', content: __( 'Settings reset to defaults.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Reset failed.', 'checkout-fees-for-woocommerce' ) } );
        } finally { setShowLoader( false ); }
    };

    if ( globalLoading && ! loadedSections.settings ) return <Spinner />;

    const mainFields = [
        {
            name: 'enabled', label: __( 'Enable Card-Based Fee Rules', 'checkout-fees-for-woocommerce' ), defaultValue: false,
            render: ( field ) => (
                <div>
                    <CheckboxControl
                        help={
                            <div style={{ marginLeft: '-24px' }}>
                                {__( 'Enable this option to apply fees/discounts based on card issuing country and bank.', 'checkout-fees-for-woocommerce' ) }
                            </div>
                        }
                        disabled
                        checked={ !! field.value } onChange={ field.onChange } />
                </div>
            ),
        },
        {
            name: 'provider', label: __( 'BIN Lookup Provider', 'checkout-fees-for-woocommerce' ), defaultValue: 'binlist',
            showWhen: binApisEnabled,
            render: ( field ) => (
                <div>
                    <SelectControl value={ field.value } disabled onChange={ field.onChange } options={ [
                        { value: 'binlist',     label: __( 'Binlist (Free)',    'checkout-fees-for-woocommerce' ) },
                        { value: 'neutrinoapi', label: __( 'Neutrino API',      'checkout-fees-for-woocommerce' ) },
                    ] } />
                    <Text style={ { fontSize: '12px', color: '#646970', marginTop: '4px' } }>
                        { __( 'Choose which BIN lookup service to use.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                </div>
            ),
        },
    ];

    const binlistFields = [
        {
            name: 'binlist.cache_enabled', label: __( 'Cache BIN Lookup Results', 'checkout-fees-for-woocommerce' ), defaultValue: true,
            render: ( field ) => (
                <CheckboxControl
                    help={
                        <div style={{ marginLeft: '-24px' }}>
                            { __( 'Enable caching to improve performance and reduce API calls to Binlist.', 'checkout-fees-for-woocommerce' ) }
                        </div>
                    }
                    disabled
                    checked={ !! field.value } onChange={ field.onChange } />
            ),
        },
        {
            name: 'binlist.cache_duration_hours', label: (
                <>{ __( 'Cache Duration (Hours)', 'checkout-fees-for-woocommerce' ) }<HelpTip text={ __( 'How long to cache BIN lookup results.', 'checkout-fees-for-woocommerce' ) } /></>
            ), defaultValue: 24,
            render: ( field ) => <NumberControl value={ field.value } disabled onChange={ field.onChange } min={ 1 } />,
        },
    ];

    const neutrinoFields = [
        {
            name: 'neutrinoapi.user_id', label: __( 'Neutrino API User ID', 'checkout-fees-for-woocommerce' ), defaultValue: '',
            render: ( field ) => <InputControl value={ field.value } onChange={ field.onChange } />,
        },
        {
            name: 'neutrinoapi.api_key', label: __( 'Neutrino API Key', 'checkout-fees-for-woocommerce' ), defaultValue: '',
            render: ( field ) => <InputControl value={ field.value } onChange={ field.onChange } type="password" />,
        },
    ];

    const cardSectionFields = [
        {
            name: 'card_section.enabled', label: __( 'Show Card Details Section at Checkout', 'checkout-fees-for-woocommerce' ), defaultValue: false,
            render: ( field ) => (
                <CheckboxControl
                    label={ __( 'Enabling this option adds a Card Details Selection Section above the payment gateway options on your checkout page.', 'checkout-fees-for-woocommerce' ) }
                    disabled
                    checked={ !! field.value } onChange={ field.onChange } />
            ),
        },
        {
            name: 'card_section.show_type',     label: __( 'Show Card Type',     'checkout-fees-for-woocommerce' ), defaultValue: false,
            render: ( field ) => <CheckboxControl disabled label={ __( 'Show the Card Type field in the Card Payment Details Section.', 'checkout-fees-for-woocommerce' ) } checked={ !! field.value } onChange={ field.onChange } />,
        },
        {
            name: 'card_section.show_scheme',   label: __( 'Show Card Scheme',   'checkout-fees-for-woocommerce' ), defaultValue: false,
            render: ( field ) => <CheckboxControl disabled label={ __( 'Show the Card Scheme field in the Card Payment Details Section.', 'checkout-fees-for-woocommerce' ) } checked={ !! field.value } onChange={ field.onChange } />,
        },
        {
            name: 'card_section.show_location', label: __( 'Show Card Location', 'checkout-fees-for-woocommerce' ), defaultValue: false,
            render: ( field ) => <CheckboxControl disabled label={ __( 'Show the Card Location field in the Card Payment Details Section.', 'checkout-fees-for-woocommerce' ) } checked={ !! field.value } onChange={ field.onChange } />,
        },
        {
            name: 'card_section.show_bank_name',label: __( 'Show Bank Name',     'checkout-fees-for-woocommerce' ), defaultValue: false,
            render: ( field ) => <CheckboxControl disabled label={ __( 'Show the Bank Name field in the Card Payment Details Section.', 'checkout-fees-for-woocommerce' ) } checked={ !! field.value } onChange={ field.onChange } />,
        },
    ];

    return (
        <VStack spacing={ 4 }>
            <form onSubmit={ handleSubmit( onSubmit ) }>

                <ProNotice feature={__('BIN APIs', 'checkout-fees-for-woocommerce')} />

                <VStack spacing={ 4 }>
                    <SettingsCard heading={ __( 'BIN APIs Configuration', 'checkout-fees-for-woocommerce' ) }
                        subHeading={ __( 'Configure BIN (Bank Identification Number) APIs to detect card issuing country and bank for applying specific fees/discounts.', 'checkout-fees-for-woocommerce' ) }
                        fields={ mainFields } control={ control } />

                    { provider === 'binlist' && (
                        <SettingsCard heading={ __( 'Binlist Configuration', 'checkout-fees-for-woocommerce' ) } fields={ binlistFields } control={ control } />
                    ) }

                    { binApisEnabled && provider === 'neutrinoapi' && (
                        <SettingsCard heading={ __( 'Neutrino API Configuration', 'checkout-fees-for-woocommerce' ) } fields={ neutrinoFields } control={ control } />
                    ) }

                    { (
                        <SettingsCard heading={ __( 'Card Details Section Configuration', 'checkout-fees-for-woocommerce' ) }
                            subHeading={ __( 'This section has the option to show the Card Payment Details Selection section on the Checkout page.', 'checkout-fees-for-woocommerce' ) }
                            fields={ cardSectionFields } control={ control } />
                    ) }

                    <HStack spacing={ 3 } style={ { paddingTop: '4px', flexWrap: 'wrap', justifyContent: 'left' } }>
                        <Button disabled={true} variant="primary" type="submit" style={ { width: 'fit-content' } }>
                            { __( 'Save Changes', 'checkout-fees-for-woocommerce' ) }
                        </Button>
                        <Button disabled={true} variant="secondary" type="button" onClick={ () => setIsResetOpen( true ) } style={ { width: 'fit-content' } }>
                            { __( 'Reset Settings', 'checkout-fees-for-woocommerce' ) }
                        </Button>

                    </HStack>
                    { noticeUI }
                </VStack>
            </form>
            { isResetOpen && (
                <ConfirmDialog onConfirm={ onReset } onCancel={ () => setIsResetOpen( false ) }>
                    { __( 'Reset BIN APIs settings to defaults?', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
        </VStack>
    );
}

export default withNotices( BinApis );
