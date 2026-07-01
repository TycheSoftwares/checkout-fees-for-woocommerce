/**
 * src/settings/screens/General.js
 *
 * General settings — single card layout matching the UI screenshot.
 * Left column: label + description. Right column: field control.
 * Save Changes + Reset Settings as side-by-side buttons below the card.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ }                  from '@wordpress/i18n';
import {
    Button,
    CheckboxControl,
    __experimentalNumberControl  as NumberControl,
    __experimentalInputControl   as InputControl,
    __experimentalVStack         as VStack,
    __experimentalHStack         as HStack,
    __experimentalText           as Text,
    __experimentalHeading        as Heading,
    __experimentalConfirmDialog  as ConfirmDialog,
    Spinner,
    withNotices,
} from '@wordpress/components';
import { useForm, Controller }    from 'react-hook-form';
import apiFetch                   from '@wordpress/api-fetch';


import { updateSettings, resetSection, deleteAllData } from '../api';
import { useSettings } from '../context/SettingsContext';
import HelpTip from '../components/HelpTip';

const DEFAULTS = {
    enabled              : true,
    per_product_enabled  : false,
    per_product_add_name : false,
    merge_all_fees       : false,
    max_total_discount   : 0,
    max_total_fee        : 0,
    hide_on_cart         : false,
};

// ─── Shared row layout ────────────────────────────────────────────────────────
function SettingRow( { label, description, children, noBorder = false } ) {
    return (
        <div style={ {
            display      : 'grid',
            gridTemplateColumns: '280px 1fr',
            gap          : '24px',
            padding      : '20px 0',
            borderBottom : noBorder ? 'none' : '1px solid #f0f0f0',
            alignItems   : 'start',
        } }>
            <div>
                <Text style={ { fontWeight: 600, fontSize: '14px', color: '#1d2327', display: 'block', marginBottom: '4px' } }>
                    { label }
                </Text>
                { description && (
                    <Text style={ { fontSize: '13px', color: '#646970', lineHeight: 1.5 } }>
                        { description }
                    </Text>
                ) }
            </div>
            <div>{ children }</div>
        </div>
    );
}

function SectionCard( { heading, description, children } ) {
    return (
        <div style={ {
            background   : '#fff',
            border       : '1px solid #e0e0e0',
            borderRadius : '8px',
            padding      : '24px',
        } }>
            <Heading level={ 4 } style={ { margin: '0 0 4px' } }>{ heading }</Heading>
            { description && (
                <Text style={ { color: '#646970', fontSize: '13px', display: 'block', marginBottom: '16px' } }>
                    { description }
                </Text>
            ) }
            { children }
        </div>
    );
}

// ─── Component ────────────────────────────────────────────────────────────────
function General( { noticeOperations, noticeUI } ) {
    const [ showLoader,   setShowLoader   ] = useState( false );
    const [ isResetOpen,  setIsResetOpen  ] = useState( false );
    const [ isDeleteOpen, setIsDeleteOpen ] = useState( false );
    const [ isTrackingDialogOpen, setIsTrackingDialogOpen ] = useState( false );

    const { settings, isLoading: globalLoading, loadedSections, fetchSection, updateSettingsData } = useSettings();

    const { control, handleSubmit, reset, watch } = useForm( {
        defaultValues: { ...DEFAULTS, ...( settings?.general || {} ) },
    } );

    const perProductEnabled = watch( 'per_product_enabled' );

    useEffect( () => {
        if ( settings?.general && loadedSections.settings ) {
            reset( { ...DEFAULTS, ...settings.general } );
        }
    }, [ settings, loadedSections.settings, reset ] );

    useEffect( () => {
        if ( ! loadedSections.settings ) fetchSection( 'settings' );
    }, [] ); // eslint-disable-line

    // ── Handlers ─────────────────────────────────────────────────────────────

    const onSubmit = async ( data ) => {
        setShowLoader( true );
        try {
            const merged = { ...( settings || {} ), general: data };
            await updateSettings( merged );
            updateSettingsData( 'settings', merged );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Settings saved successfully.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Error saving settings. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        } finally { setShowLoader( false ); }
    };

    const onReset = async () => {
        setIsResetOpen( false );
        setShowLoader( true );
        try {
            const defaults = await resetSection( 'general' );
            reset( { ...DEFAULTS, ...defaults } );
            updateSettingsData( 'settings', { ...( settings || {} ), general: defaults } );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Settings reset to defaults.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Reset failed. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        } finally { setShowLoader( false ); }
    };

    const onDeleteAllData = async () => {
        setIsDeleteOpen( false );
        setShowLoader( true );
        try {
            await deleteAllData();
            reset( DEFAULTS );
            updateSettingsData( 'settings', {} );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'All plugin data deleted successfully.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Delete failed. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        } finally { setShowLoader( false ); }
    };

    const resetTracking = async () => {
        setShowLoader( true );
        try {
            await apiFetch( {
                path   : '/wp/v2/settings',
                method : 'POST',
                data   : {
                    pgbf_pro_allow_tracking : '',
                    ts_tracker_last_send    : '',
                },
            } );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Tracking has been successfully reset.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Failed to reset tracking.', 'checkout-fees-for-woocommerce' ) } );
        } finally {
            setShowLoader( false );
            setIsTrackingDialogOpen( false );
        }
    };

    if ( globalLoading && ! loadedSections.settings ) return <Spinner />;

    return (
        <VStack spacing={ 4 }>
            <form onSubmit={ handleSubmit( onSubmit ) }>
                <VStack spacing={ 4 }>

                    <SectionCard
                        heading={ __( 'General Options', 'checkout-fees-for-woocommerce' ) }
                        description={ __( 'Configure the basic settings for Payment Gateway Based Fees and Discounts', 'checkout-fees-for-woocommerce' ) }
                    >
                        { /* Enable Plugin */ }
                        <SettingRow
                            label={ __( 'Enable Plugin', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="enabled" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ marginLeft: '32px' }}>
                                        <CheckboxControl
                                            checked={ !! field.value }
                                            onChange={ field.onChange }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Per Product */ }
                        <SettingRow
                            label={ __( 'Product-Level Fee Overrides', 'checkout-fees-for-woocommerce' ) }
                            description={ __( 'Adds fee settings to each product\'s edit page.', 'checkout-fees-for-woocommerce' ) }
                        >
                            <VStack spacing={ 2 }>
                                <Controller name="per_product_enabled" control={ control }
                                    render={ ( { field } ) => (
                                        <div style={{ marginLeft: '32px' }}>
                                            <CheckboxControl
                                                label={ __( 'Enable per-product fees/discounts', 'checkout-fees-for-woocommerce' ) }
                                                checked={ !! field.value }
                                                onChange={ field.onChange }
                                            />
                                        </div>
                                    ) }
                                />
                                { perProductEnabled && (
                                    <Controller name="per_product_add_name" control={ control }
                                        render={ ( { field } ) => (
                                            <div style={{ marginLeft: '32px' }}>
                                                <CheckboxControl
                                                    label={ __( 'Add product title to fees (helps with variable products)', 'checkout-fees-for-woocommerce' ) }
                                                    checked={ !! field.value }
                                                    onChange={ field.onChange }
                                                />
                                            </div>
                                        ) }
                                    />
                                ) }
                            </VStack>
                        </SettingRow>

                        { /* Merge Fees */ }
                        <SettingRow
                            label={ __( 'Combine Gateway Fees into One Line', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="merge_all_fees" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ display: 'flex', gap: '8px' }}>
                                        <HelpTip message={ __( 'Show all fees for a gateway as a single line at checkout.', 'checkout-fees-for-woocommerce' ) } />
                                        <CheckboxControl
                                            checked={ !! field.value }
                                            onChange={ field.onChange }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Max Range */ }
                        <SettingRow
                            label={ __( 'Max Range Options', 'checkout-fees-for-woocommerce' ) }
                            description={ __( 'Set maximum limits for discounts and fees', 'checkout-fees-for-woocommerce' ) }
                        >
                            <VStack spacing={ 3 }>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <HelpTip message={ __( 'Negative number. Set 0 to disable.', 'checkout-fees-for-woocommerce' ) } />
                                    <div>
                                        <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                            { __( 'Maximum Total Discount', 'checkout-fees-for-woocommerce' ) }
                                        </Text>
                                        <Controller name="max_total_discount" control={ control }
                                            render={ ( { field } ) => (
                                                <InputControl
                                                    type="number"
                                                    value={ field.value }
                                                    onChange={ field.onChange }
                                                    style={ { maxWidth: '360px' } }
                                                />
                                            ) }
                                        />
                                    </div>
                                </div>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <HelpTip message={ __( 'Set 0 to disable.', 'checkout-fees-for-woocommerce' ) } />
                                    <div>
                                        <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                            { __( 'Maximum Total Fee', 'checkout-fees-for-woocommerce' ) }
                                        </Text>
                                        <Controller name="max_total_fee" control={ control }
                                            render={ ( { field } ) => (
                                                <InputControl
                                                    type="number"
                                                    value={ field.value }
                                                    onChange={ field.onChange }
                                                    style={ { maxWidth: '360px' } }
                                                />
                                            ) }
                                        />
                                    </div>
                                </div>
                            </VStack>
                        </SettingRow>

                        { /* Cart Options */ }
                        <SettingRow
                            label={ __( 'Hide Fees on Cart Page', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="hide_on_cart" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ display: 'flex', gap: '8px' }}>
                                        <HelpTip message={ __( 'Fees are still charged — this only hides them from the cart view.', 'checkout-fees-for-woocommerce' ) } />
                                        <CheckboxControl
                                            checked={ !! field.value }
                                            onChange={ field.onChange }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Advanced — Delete */ }
                        <SettingRow
                            label={ __( 'Delete All Plugin Data', 'checkout-fees-for-woocommerce' ) }
                            noBorder
                        >
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <HelpTip message={ __( '⚠️ Permanently deletes all fees, rules, and settings. Cannot be undone.', 'checkout-fees-for-woocommerce' ) } />
                                        
                                <VStack spacing={ 1 }>
                                    <Button
                                        variant="primary"
                                        isDestructive
                                        onClick={ () => setIsDeleteOpen( true ) }
                                        style={ { width: 'fit-content' } }
                                    >
                                        { __( 'Delete all plugin data', 'checkout-fees-for-woocommerce' ) }
                                    </Button>
                                </VStack>
                            </div>
                        </SettingRow>

                        { /* Advanced — Delete */ }
                        <SettingRow
                            label={ __( 'Reset Usage Tracking', 'checkout-fees-for-woocommerce' ) }
                        >
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <HelpTip message={ __( 'Clear all stored usage tracking data for this plugin.', 'checkout-fees-for-woocommerce' ) } />
                                        
                                <VStack spacing={ 1 }>
                                    <Button
                                        variant="secondary"
                                        type="button"
                                        disabled={ showLoader }
                                        onClick={ () => setIsTrackingDialogOpen( true ) }
                                        style={ { width: 'fit-content' } }
                                    >
                                        { __( 'Reset Usage Tracking', 'checkout-fees-for-woocommerce' ) }
                                    </Button>
                                </VStack>
                            </div>
                        </SettingRow>
                    </SectionCard>

                    { /* ── Action buttons ── */ }
                    <HStack spacing={ 3 } style={ { paddingTop: '4px', justifyContent: 'left' } }>
                        <Button
                            variant="primary"
                            type="submit"
                            isBusy={ showLoader }
                            disabled={ showLoader }
                            style={ { width: 'fit-content' } }
                        >
                            { __( 'Save Changes', 'checkout-fees-for-woocommerce' ) }
                        </Button>
                        <Button
                            variant="secondary"
                            type="button"
                            disabled={ showLoader }
                            onClick={ () => setIsResetOpen( true ) }
                            style={ { width: 'fit-content' } }
                        >
                            { __( 'Reset Settings', 'checkout-fees-for-woocommerce' ) }
                        </Button>
                    </HStack>
                    { noticeUI }
                </VStack>
            </form>

            { isResetOpen && (
                <ConfirmDialog onConfirm={ onReset } onCancel={ () => setIsResetOpen( false ) }>
                    { __( 'Reset General settings to defaults?', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
            { isDeleteOpen && (
                <ConfirmDialog onConfirm={ onDeleteAllData } onCancel={ () => setIsDeleteOpen( false ) }>
                    { __( 'Are you sure? This will permanently delete ALL plugin data and cannot be undone.', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
            { isTrackingDialogOpen && (
                <ConfirmDialog onConfirm={ resetTracking } onCancel={ () => setIsTrackingDialogOpen( false ) }>
                    { __( 'Are you sure you want to reset all usage tracking data?', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
        </VStack>
    );
}

export default withNotices( General );
