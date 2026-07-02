/**
 * src/settings/screens/GlobalExtraFee.js
 *
 * Global Extra Fee — single card layout matching the UI screenshot.
 * Sections: Enable, Add as Extra Only, Exclude from Gateways, Fee Title,
 *           Fee Configuration (type + value grouped), Cart Amount Requirements.
 * Save Changes + Reset Settings as side-by-side buttons below the card.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ }                  from '@wordpress/i18n';
import {
    Button,
    CheckboxControl,
    SelectControl,
    __experimentalInputControl  as InputControl,
    __experimentalVStack        as VStack,
    __experimentalHStack        as HStack,
    __experimentalText          as Text,
    __experimentalHeading       as Heading,
    __experimentalConfirmDialog as ConfirmDialog,
    Spinner,
    withNotices,
} from '@wordpress/components';
import ProNotice, { ProInlineNotice } from '../components/ProNotice';
import Select from 'react-select';
import { useForm, Controller } from 'react-hook-form';

import { updateSettings, resetSection } from '../api';
import { useSettings } from '../context/SettingsContext';
import HelpTip from '../components/HelpTip';

const DEFAULTS = {
    enabled         : false,
    as_extra_only   : false,
    gateways_exclude: [],
    title           : '',
    type            : 'fixed',
    value           : 0,
    min_cart_amount : 0,
    max_cart_amount : 0,
};

// ─── Shared layout helpers (same as General) ──────────────────────────────────
function SettingRow( { label, description, children, noBorder = false } ) {
    return (
        <div style={ {
            display             : 'grid',
            gridTemplateColumns : '280px 1fr',
            gap                 : '24px',
            padding             : '20px 0',
            borderBottom        : noBorder ? 'none' : '1px solid #f0f0f0',
            alignItems          : 'start',
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
function GlobalExtraFee( { noticeOperations, noticeUI } ) {
    const [ showLoader,  setShowLoader  ] = useState( false );
    const [ isResetOpen, setIsResetOpen ] = useState( false );
    const [ gatewayOptions, setGatewayOptions ] = useState( [] );

    const { settings, gateways, isLoading: globalLoading, loadedSections, fetchSection, updateSettingsData } = useSettings();

    const { control, handleSubmit, reset } = useForm( {
        defaultValues: { ...DEFAULTS, ...( settings?.global_extra_fee || {} ) },
    } );

    useEffect( () => {
        if ( gateways && loadedSections.gateways ) {
            setGatewayOptions( gateways.map( ( gw ) => ( { value: gw.id, label: gw.title } ) ) );
        }
    }, [ gateways, loadedSections.gateways ] );

    useEffect( () => {
        if ( settings?.global_extra_fee && loadedSections.settings ) {
            reset( { ...DEFAULTS, ...settings.global_extra_fee } );
        }
    }, [ settings, loadedSections.settings, reset ] );

    useEffect( () => {
        if ( ! loadedSections.settings ) fetchSection( 'settings' );
        if ( ! loadedSections.gateways ) fetchSection( 'gateways' );
    }, [] ); // eslint-disable-line

    // ── Handlers ─────────────────────────────────────────────────────────────

    const onSubmit = async ( data ) => {
        setShowLoader( true );
        try {
            const merged = { ...( settings || {} ), global_extra_fee: data };
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
            const defaults = await resetSection( 'global_extra_fee' );
            reset( { ...DEFAULTS, ...defaults } );
            updateSettingsData( 'settings', { ...( settings || {} ), global_extra_fee: defaults } );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Settings reset to defaults.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Reset failed. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        } finally { setShowLoader( false ); }
    };

    if ( globalLoading && ! loadedSections.settings ) return <Spinner />;

    return (
        <VStack spacing={ 4 }>

            <form onSubmit={ handleSubmit( onSubmit ) }>
                <VStack spacing={ 4 }>

                    <SectionCard
                        heading={ __( 'Global Fee Configuration', 'checkout-fees-for-woocommerce' ) }
                        description={ __( 'Set up a fee that will be applied across all payment gateways', 'checkout-fees-for-woocommerce' ) }
                    >
                        { /* Enable Global Fee */ }
                        <SettingRow
                            label={ __( 'Enable a Fee for All Gateways', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="enabled" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ display: 'flex', gap: '8px' }}>
                                        <HelpTip message={ __( 'Applies a single flat fee across every payment gateway at checkout, regardless of which gateway the customer selects. This fee is not taxable. For gateway-specific fees, configure them under the Payment Gateways tab instead.', 'checkout-fees-for-woocommerce' ) } />
                                        <CheckboxControl
                                            checked={ !! field.value }
                                            onChange={ field.onChange }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Add as Extra Fee Only */ }
                        <SettingRow
                            label={ __( 'Apply Only When Gateway Fee Exists', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="as_extra_only" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ display: 'flex', gap: '8px' }}>
                                        <HelpTip message={ __( 'Only charges this fee if the selected gateway already has its own fee.', 'checkout-fees-for-woocommerce' ) } />
                                        <CheckboxControl
                                            checked={ !! field.value }
                                            onChange={ field.onChange }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Exclude from Gateways */ }
                        <SettingRow
                            label={ __( 'Exclude Specific Gateways', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="gateways_exclude" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ display: 'flex', gap: '8px' }}>
                                        <HelpTip message={ __( 'Select gateways that should not have this fee applied.', 'checkout-fees-for-woocommerce' ) } />
                                        <Select
                                            isMulti
                                            options={ gatewayOptions }
                                            value={ gatewayOptions.filter( ( opt ) =>
                                                ( field.value || [] ).includes( opt.value )
                                            ) }
                                            onChange={ ( sel ) => field.onChange( sel ? sel.map( ( s ) => s.value ) : [] ) }
                                            placeholder={ __( 'e.g., Direct bank transfer, Check payments', 'checkout-fees-for-woocommerce' ) }
                                            styles={ { container: ( base ) => ( { ...base, maxWidth: '360px', width: '360px' } ) } }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Fee Title */ }
                        <SettingRow
                            label={ __( 'Fee Label (shown at checkout)', 'checkout-fees-for-woocommerce' ) }
                        >
                            <Controller name="title" control={ control }
                                render={ ( { field } ) => (
                                    <div style={{ display: 'flex', gap: '8px' }}>
                                        <HelpTip message={ __( 'Fee (or Discount) label to show to customer.', 'checkout-fees-for-woocommerce' ) } />
                                        <InputControl
                                            value={ field.value }
                                            onChange={ field.onChange }
                                            placeholder={ __( 'e.g., Global processing fee', 'checkout-fees-for-woocommerce' ) }
                                            style={ { maxWidth: '360px', width: '360px' } }
                                        />
                                    </div>
                                ) }
                            />
                        </SettingRow>

                        { /* Fee Configuration — type + value grouped */ }
                        <SettingRow
                            label={ __( 'Fee Configuration', 'checkout-fees-for-woocommerce' ) }
                        >
                            <VStack spacing={ 3 }>
                                <div style={{ display: 'flex', gap: '8px' }}>
                                    <HelpTip message={ __( 'Choose fees type that can be either fixed or percentage based. For discount enter a negative number to Fee Amount.', 'checkout-fees-for-woocommerce' ) } />

                                    <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                        { __( 'Fee Type', 'checkout-fees-for-woocommerce' ) }
                                    </Text>
                                </div>
                                <div style={ { marginLeft: '32px', width: '360px' } }>
                                    <Controller name="type" control={ control }
                                        render={ ( { field } ) => (
                                            <SelectControl
                                                value={ field.value }
                                                onChange={ field.onChange }
                                                options={ [
                                                    { value: 'fixed',   label: __( 'Fixed Amount', 'checkout-fees-for-woocommerce' ) },
                                                    { value: 'percent', label: __( 'Percentage',   'checkout-fees-for-woocommerce' ) },
                                                ] }
                                                style={ { maxWidth: '360px' } }
                                            />
                                        ) }
                                    />
                                </div>

                                <div style={ { marginLeft: '32px' } }>
                                    <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                        { __( 'Fee Amount', 'checkout-fees-for-woocommerce' ) }
                                    </Text>
                                    <Controller name="value" control={ control }
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
                            </VStack>
                        </SettingRow>

                        { /* Cart Amount Requirements — min + max grouped */ }
                        <SettingRow
                            label={ __( 'Cart Total Threshold', 'checkout-fees-for-woocommerce' ) }
                            noBorder
                        >
                            <VStack spacing={ 3 }>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'nowrap' }}>
                                    <HelpTip message={ __( 'Apply fee when cart subtotal is within this range. Enter 0 for no limit.', 'checkout-fees-for-woocommerce' ) } />
                                    <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                        { __( 'Minimum Cart Amount', 'checkout-fees-for-woocommerce' ) }
                                    </Text>
                                    <ProInlineNotice />
                                </div>
                                <div style={{ marginLeft: '32px'} }>
                                    <Controller name="min_cart_amount" control={ control }
                                        render={ ( { field } ) => (
                                            <InputControl
                                                type="number"
                                                min="0"
                                                disabled
                                                value={ field.value }
                                                onChange={ field.onChange }
                                                style={ { maxWidth: '360px' } }
                                            />
                                        ) }
                                    />
                                </div>
                                <div style={{ marginLeft: '32px'} }>
                                    <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                        { __( 'Maximum Cart Amount', 'checkout-fees-for-woocommerce' ) }
                                    </Text>
                                    <Controller name="max_cart_amount" control={ control }
                                        render={ ( { field } ) => (
                                            <InputControl
                                                type="number"
                                                min="0"
                                                disabled
                                                value={ field.value }
                                                onChange={ field.onChange }
                                                style={ { maxWidth: '360px' } }
                                            />
                                        ) }
                                    />
                                </div>
                            </VStack>
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
                    { __( 'Reset Global Extra Fee settings to defaults?', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
        </VStack>
    );
}

export default withNotices( GlobalExtraFee );
