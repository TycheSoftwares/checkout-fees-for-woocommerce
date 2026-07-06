/**
 * src/settings/components/ProductMetabox.js
 *
 * Lite version restrictions:
 * - Only the FIRST payment gateway is fully configurable.
 * - All other gateways are completely disabled (locked).
 * - A notice appears inside the panel for locked gateways.
 */

import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ }                                        from '@wordpress/i18n';
import {
    Button,
    CheckboxControl,
    SelectControl,
    __experimentalInputControl as InputControl,
    __experimentalText         as Text,
    __experimentalHeading      as Heading,
    Spinner,
    withNotices,
} from '@wordpress/components';
import { useForm, Controller, useWatch } from 'react-hook-form';
import { getProductFees, updateProductFees, getGateways, getOptions } from '../api';

// ─── Toggle this constant for Lite / Pro ───────────────────────────────────
const IS_PRO = false; // Set to true for Pro version

// ─── Defaults ─────────────────────────────────────────────────────────────────

const FEE_DEFAULTS = {
    title          : '',
    override_global: 'no',
    type           : 'fixed',
    value          : '',
    min_fee        : '',
    max_fee        : '',
    coupons_rule   : 'disabled',
};

const GENERAL_DEFAULTS = {
    min_cart_amount   : '',
    max_cart_amount   : '',
    rounding_enabled  : false,
    rounding_precision: '',
    tax_enabled       : false,
    tax_class         : '',
    exclude_shipping  : false,
    add_taxes         : false,
    percent_usage     : 'for_all_cart',
    fixed_usage       : 'once',
};

// ─── Layout helpers ───────────────────────────────────────────────────────────

function SectionCard( { heading, description, children } ) {
    return (
        <div style={ {
            background  : '#fff',
            border      : '1px solid #e0e0e0',
            borderRadius: '8px',
            padding     : '20px',
            marginBottom: '12px',
        } }>
            { heading && (
                <Heading level={ 4 } style={ { margin: '0 0 4px' } }>
                    { heading }
                </Heading>
            ) }
            { description && (
                <Text style={ { color: '#646970', fontSize: '13px', display: 'block', marginBottom: '12px' } }>
                    { description }
                </Text>
            ) }
            { children }
        </div>
    );
}

function SettingRow( { label, description, children, noBorder = false } ) {
    return (
        <div style={ {
            display            : 'grid',
            gridTemplateColumns: '200px 1fr',
            gap                : '16px',
            padding            : '12px 0',
            borderBottom       : noBorder ? 'none' : '1px solid #f0f0f0',
            alignItems         : 'start',
        } }>
            <div>
                <Text style={ {
                    fontWeight  : 600,
                    fontSize    : '13px',
                    color       : '#1d2327',
                    display     : 'block',
                    marginBottom: description ? '4px' : 0,
                } }>
                    { label }
                </Text>
                { description && (
                    <Text style={ { fontSize: '12px', color: '#646970', lineHeight: 1.4 } }>
                        { description }
                    </Text>
                ) }
            </div>
            <div style={ { maxWidth: '280px' } }>{ children }</div>
        </div>
    );
}

// ─── General fields sub-component ────────────────────────────────────────────

function GeneralFields( { gwId, control, taxClassOptions, isEditable } ) {
    const roundingEnabled = useWatch( { control, name: `${ gwId }.general.rounding_enabled` } );
    const taxEnabled      = useWatch( { control, name: `${ gwId }.general.tax_enabled` } );

    return (<>

        <SettingRow label={ __( 'Min cart amount', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Leave 0 for no limit.', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.general.min_cart_amount` } control={ control }
                render={ ( { field } ) => (
                    <InputControl type="number" min="0" value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } />
                ) }
            />
        </SettingRow>

        <SettingRow label={ __( 'Max cart amount', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Leave 0 for no limit.', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.general.max_cart_amount` } control={ control }
                render={ ( { field } ) => (
                    <InputControl type="number" min="0" value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } />
                ) }
            />
        </SettingRow>

        <SettingRow
            label={ __( 'Round Fee to Decimal Places', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Rounds fee to the set decimal places before adding to order total (round half up).', 'checkout-fees-for-woocommerce' ) }
        >
            <Controller name={ `${ gwId }.general.rounding_enabled` } control={ control }
                render={ ( { field } ) => (
                    <CheckboxControl checked={ !! field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } />
                ) }
            />
            { !! roundingEnabled && (
                <div style={ { marginTop: '10px' } }>
                    <Text style={ { fontWeight: 600, fontSize: '13px', display: 'block', marginBottom: '6px' } }>
                        { __( 'Rounding precision (decimal places)', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Controller name={ `${ gwId }.general.rounding_precision` } control={ control }
                        render={ ( { field } ) => (
                            <InputControl type="number" min="0" value={ field.value } onChange={ field.onChange }
                                placeholder="0" disabled={ ! isEditable } />
                        ) }
                    />
                </div>
            ) }
        </SettingRow>

        <SettingRow
            label={ __( 'Apply Tax to This Fee', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Enable only if your tax rules require fees to be taxed.', 'checkout-fees-for-woocommerce' ) }
        >
            <Controller name={ `${ gwId }.general.tax_enabled` } control={ control }
                render={ ( { field } ) => (
                    <CheckboxControl checked={ !! field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } />
                ) }
            />
            { !! taxEnabled && (
                <div style={ { marginTop: '10px' } }>
                    <Text style={ { fontWeight: 600, fontSize: '13px', display: 'block', marginBottom: '6px' } }>
                        { __( 'Tax class', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Controller name={ `${ gwId }.general.tax_class` } control={ control }
                        render={ ( { field } ) => (
                            <SelectControl
                                value={ field.value }
                                onChange={ field.onChange }
                                options={ taxClassOptions }
                                disabled={ ! isEditable }
                            />
                        ) }
                    />
                </div>
            ) }
        </SettingRow>

        <SettingRow
            label={ __( 'Cart Calculation', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Control what is included in the cart total when calculating percentage-based fees', 'checkout-fees-for-woocommerce' ) }
        >
            <Controller name={ `${ gwId }.general.exclude_shipping` } control={ control }
                render={ ( { field } ) => (
                    <CheckboxControl
                        label={ __( 'Exclude shipping from cart total when calculating fees', 'checkout-fees-for-woocommerce' ) }
                        checked={ !! field.value }
                        onChange={ field.onChange }
                        disabled={ ! isEditable }
                    />
                ) }
            />
            <Controller name={ `${ gwId }.general.add_taxes` } control={ control }
                render={ ( { field } ) => (
                    <CheckboxControl
                        label={ __( 'Include taxes in cart total when calculating fees', 'checkout-fees-for-woocommerce' ) }
                        checked={ !! field.value }
                        onChange={ field.onChange }
                        disabled={ ! isEditable }
                    />
                ) }
            />
        </SettingRow>

        <SettingRow label={ __( 'Fee calc (Percent)', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.general.percent_usage` } control={ control }
                render={ ( { field } ) => (
                    <SelectControl value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable }
                        options={ [
                            { value: 'for_all_cart',  label: __( 'For all cart',  'checkout-fees-for-woocommerce' ) },
                            { value: 'by_product', label: __( 'For each item', 'checkout-fees-for-woocommerce' ) },
                        ] }
                    />
                ) }
            />
        </SettingRow>

        <SettingRow label={ __( 'Fee calc (Fixed)', 'checkout-fees-for-woocommerce' ) } noBorder>
            <Controller name={ `${ gwId }.general.fixed_usage` } control={ control }
                render={ ( { field } ) => (
                    <SelectControl value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable }
                        options={ [
                            { value: 'once',        label: __( 'Once',         'checkout-fees-for-woocommerce' ) },
                            { value: 'by_quantity', label: __( 'Per item qty', 'checkout-fees-for-woocommerce' ) },
                        ] }
                    />
                ) }
            />
        </SettingRow>

    </>);
}

// ─── Main component ───────────────────────────────────────────────────────────

function ProductMetabox( { productId, noticeOperations, noticeUI } ) {
    const [ gateways,   setGateways   ] = useState( [] );
    const [ activeGw,   setActiveGw   ] = useState( null );
    const [ isLoading,  setIsLoading  ] = useState( true );
    const [ isSaving,   setIsSaving   ] = useState( false );
    const [ taxClassOptions, setTaxClassOptions ] = useState( [] );

    const { control, reset, getValues } = useForm( {} );

    const getValuesRef = useRef( getValues );
    useEffect( () => { getValuesRef.current = getValues; } );

    // ── Load ──────────────────────────────────────────────────────────────────

    useEffect( () => {
        ( async () => {
            try {
                const [ gwList, feesData, optionsData ] = await Promise.all( [
                    getGateways(),
                    getProductFees( productId ),
                    getOptions(),
                ] );
                setGateways( gwList );

                // In Lite, only BACS is editable. If BACS not found, no gateway is editable.
                const bacsGw = gwList.find( g => g.id === 'bacs' );
                if ( bacsGw ) {
                    setActiveGw( bacsGw.id );
                } else if ( gwList.length ) {
                    setActiveGw( gwList[ 0 ].id );
                } else {
                    setActiveGw( null );
                }

                const taxClasses = optionsData?.tax_classes || [];
                const mappedTaxClasses = taxClasses.map( ( item ) => ( {
                    value: item.value,
                    label: item.label,
                } ) );
                setTaxClassOptions( mappedTaxClasses );

                const defaults = {};
                gwList.forEach( ( gw ) => {
                    const s = feesData[ gw.id ] || {};
                    defaults[ gw.id ] = {
                        enabled : !! s.enabled,
                        fee_1   : { ...FEE_DEFAULTS,     ...( s.fee_1   || {} ) },
                        fee_2   : { ...FEE_DEFAULTS,     ...( s.fee_2   || {} ) },
                        general : { ...GENERAL_DEFAULTS, ...( s.general || {} ) },
                    };
                } );
                reset( defaults );
            } catch ( err ) {
                console.error( '[PGBF] ProductMetabox load error:', err );
            } finally {
                setIsLoading( false );
            }
        } )();
    }, [ productId ] ); // eslint-disable-line

    // ── Save ──────────────────────────────────────────────────────────────────

    const doSave = useCallback( async () => {
        const data = getValuesRef.current?.();
        if ( ! data ) return;
        try {
            await updateProductFees( productId, data );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Fees saved successfully.', 'checkout-fees-for-woocommerce' ) } );
        } catch ( err ) {
            console.error( '[PGBF] Save failed:', err );
            noticeOperations.createNotice( { status: 'error', content: __( 'Error saving fees. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        }
    }, [ productId ] );

    const onManualSave = async () => {
        setIsSaving( true );
        await doSave();
        setIsSaving( false );
    };

    // ── Fee panel for one gateway ─────────────────────────────────────────────

    const renderGatewayPanel = ( gw ) => {
        const gwId   = gw.id;
        const active = gwId === activeGw;

        // Only BACS is editable in Lite (when present)
        const isEditable = IS_PRO || gwId === 'bacs';

        // Check if BACS exists in the list
        const bacsExists = gateways.some( g => g.id === 'bacs' );

        return (
            <div key={ gwId } style={ { display: active ? 'block' : 'none' } }>

                { /* ── Notice for locked gateways ── */ }
                { ! isEditable && (
                    <div style={ {
                        background: '#fef9ec',
                        border: '1px solid #f0c040',
                        borderRadius: '4px',
                        padding: '12px 16px',
                        marginBottom: '16px',
                        display: 'flex',
                        alignItems: 'center',
                        gap: '12px',
                        flexWrap: 'wrap',
                    } }>
                        <span className="dashicons dashicons-info-outline" style={ { color: '#dba617', fontSize: '20px' } } />
                        <span style={ { fontSize: '13px', color: '#1d2327' } }>
                            { __( 'Only BACS (Direct Bank Transfer) is configurable in the Lite version.', 'checkout-fees-for-woocommerce' ) }
                            &nbsp;
                            <a
                                href="https://www.tychesoftwares.com/products/woocommerce-payment-gateway-based-fees-and-discounts-plugin/?utm_source=pgbflite&utm_medium=notice&utm_campaign=upgrade"
                                target="_blank"
                                rel="noreferrer"
                                style={ { fontWeight: 600, color: '#2271b1', textDecoration: 'underline' } }
                            >
                                { __( 'Upgrade to Pro', 'checkout-fees-for-woocommerce' ) }
                            </a>
                            &nbsp;
                            { __( 'to enable fee overrides for all gateways.', 'checkout-fees-for-woocommerce' ) }
                        </span>
                    </div>
                ) }

                { /* Enable toggle */ }
                <div style={ {
                    marginBottom: '16px',
                    padding     : '10px 16px',
                    background  : '#f9f9f9',
                    border      : '1px solid #e0e0e0',
                    borderRadius: '6px',
                    display     : 'flex',
                    alignItems  : 'center',
                    gap         : '12px',
                    flexWrap    : 'wrap',
                } }>
                    <Text style={ { fontWeight: 600, fontSize: '16px', color: '#1d2327', margin: 0 } }>
                        { gw.title }
                        { ! isEditable && (
                            <span style={ { marginLeft: '8px', fontSize: '13px', color: '#d63638' } }>
                                🔒 { __( 'Pro feature', 'checkout-fees-for-woocommerce' ) }
                            </span>
                        ) }
                    </Text>
                    <Controller name={ `${ gwId }.enabled` } control={ control }
                        defaultValue={ false }
                        render={ ( { field } ) => (
                            <CheckboxControl
                                label={ __( 'Enable fees for this gateway', 'checkout-fees-for-woocommerce' ) }
                                checked={ !! field.value }
                                onChange={ field.onChange }
                                disabled={ ! isEditable }
                            />
                        ) }
                    />
                </div>

                { /* Fee 1 */ }
                <SectionCard
                    heading={ __( 'Fee / Discount', 'checkout-fees-for-woocommerce' ) }
                    description={ __( 'Primary fee or discount applied when this gateway is selected.', 'checkout-fees-for-woocommerce' ) }
                >
                    { renderFeeFields( gwId, 'fee_1', isEditable ) }
                </SectionCard>

                { /* Fee 2 */ }
                <SectionCard
                    heading={ __( 'Secondary Fee (Optional)', 'checkout-fees-for-woocommerce' ) }
                    description={ __( 'Add a second fee — e.g., a fixed handling fee plus a percentage processing fee.', 'checkout-fees-for-woocommerce' ) }
                >
                    { renderFeeFields( gwId, 'fee_2', isEditable ) }
                </SectionCard>

                { /* General */ }
                <SectionCard
                    heading={ __( 'General Options', 'checkout-fees-for-woocommerce' ) }
                    description={ __( 'Cart thresholds and calculation settings for this gateway.', 'checkout-fees-for-woocommerce' ) }
                >
                    <GeneralFields
                        gwId={ gwId }
                        control={ control }
                        taxClassOptions={ taxClassOptions }
                        isEditable={ isEditable }
                    />
                </SectionCard>

            </div>
        );
    };

    // ── Fee fields ────────────────────────────────────────────────────────────

    const renderFeeFields = ( gwId, prefix, isEditable ) => (<>

        <SettingRow label={ __( 'Fee label', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Displayed to customer at checkout.', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.${ prefix }.title` } control={ control }
                render={ ( { field } ) => (
                    <InputControl value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable }
                        placeholder={ __( 'e.g., Payment processing fee', 'checkout-fees-for-woocommerce' ) } />
                ) }
        /></SettingRow>

        <SettingRow label={ __( 'Override global fee', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.${ prefix }.override_global` } control={ control }
                render={ ( { field } ) => (
                    <SelectControl value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable }
                        options={ [
                            { value: 'no',  label: __( 'No',  'checkout-fees-for-woocommerce' ) },
                            { value: 'yes', label: __( 'Yes', 'checkout-fees-for-woocommerce' ) },
                        ] }
                    />
                ) }
        /></SettingRow>

        <SettingRow label={ __( 'Fee type', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.${ prefix }.type` } control={ control }
                render={ ( { field } ) => (
                    <SelectControl value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable }
                        options={ [
                            { value: 'fixed',   label: __( 'Fixed Amount', 'checkout-fees-for-woocommerce' ) },
                            { value: 'percent', label: __( 'Percentage',   'checkout-fees-for-woocommerce' ) },
                        ] }
                    />
                ) }
        /></SettingRow>

        <SettingRow label={ __( 'Fee amount', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.${ prefix }.value` } control={ control }
                render={ ( { field } ) => (
                    <InputControl type="number" value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } placeholder="0.00" />
                ) }
        /></SettingRow>

        <SettingRow label={ __( 'Minimum fee amount', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Set 0 to disable.', 'checkout-fees-for-woocommerce' ) }>
            <Controller name={ `${ gwId }.${ prefix }.min_fee` } control={ control }
                render={ ( { field } ) => (
                    <InputControl type="number" value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } />
                ) }
        /></SettingRow>

        <SettingRow label={ __( 'Maximum fee amount', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Set 0 to disable.', 'checkout-fees-for-woocommerce' ) }
            noBorder>
            <Controller name={ `${ gwId }.${ prefix }.max_fee` } control={ control }
                render={ ( { field } ) => (
                    <InputControl type="number" value={ field.value } onChange={ field.onChange }
                        disabled={ ! isEditable } />
                ) }
        /></SettingRow>

        <SettingRow
            label={ __( 'Coupons Rule', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Control whether the fee applies when coupons are used', 'checkout-fees-for-woocommerce' ) }
        >
            <Controller name={ `${ gwId }.${ prefix }.coupons_rule` } control={ control }
                render={ ( { field } ) => (
                    <div style={ { maxWidth: '280px' } }>
                        <SelectControl value={ field.value } onChange={ field.onChange }
                            disabled={ ! isEditable }
                            options={ [
                                { value: 'disabled',           label: __( 'Disabled',                    'checkout-fees-for-woocommerce' ) },
                                { value: 'only_if_no_coupons', label: __( 'Only if no coupons applied',   'checkout-fees-for-woocommerce' ) },
                                { value: 'only_if_coupons',    label: __( 'Only if coupons are applied',  'checkout-fees-for-woocommerce' ) },
                            ] }
                        />
                    </div>
                ) }
        /></SettingRow>

    </>);

    // ── Render ────────────────────────────────────────────────────────────────

    if ( isLoading ) return (
        <div style={ { padding: '20px', textAlign: 'center' } }><Spinner /></div>
    );

    if ( ! gateways.length ) return (
        <div style={ { padding: '16px' } }>
            <Text style={ { color: '#646970' } }>
                { __( 'No active payment gateways found.', 'checkout-fees-for-woocommerce' ) }
            </Text>
        </div>
    );

    return (
        <div style={ { padding: '16px', fontFamily: '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif' } }>

            { /* ── Tab bar ── */ }
            <div style={ {
                display     : 'flex',
                flexWrap    : 'wrap',
                borderBottom: '1px solid #ddd',
                marginBottom: '16px',
            } }>
                { gateways.map( ( gw ) => {
                    const active = activeGw === gw.id;
                    const isEditable = IS_PRO || gw.id === 'bacs';

                    return (
                        <button
                            key={ gw.id }
                            type="button"
                            onClick={ () => setActiveGw( gw.id ) }
                            className={ `pgbf-dashboard-tab${ active ? ' is-active' : '' }` }
                            style={ {
                                padding     : '8px 14px',
                                cursor      : isEditable ? 'pointer' : 'default',
                                background  : 'none',
                                borderTop   : 'none',
                                borderRight : 'none',
                                borderLeft  : 'none',
                                fontWeight  : active ? 600 : 400,
                                fontSize    : '13px',
                                marginBottom: '-1px',
                                whiteSpace  : 'nowrap',
                                opacity     : isEditable ? 1 : 0.6,
                            } }
                        >
                            { gw.title }
                            { ! isEditable && (
                                <span style={ { marginLeft: '4px', fontSize: '12px', color: '#d63638' } }>
                                    🔒
                                </span>
                            ) }
                        </button>
                    );
                } ) }
            </div>

            { /* ── All gateway panels ── */ }
            { gateways.map( ( gw ) => renderGatewayPanel( gw ) ) }

            { /* ── Save button ── */ }
            <div style={ { display: 'flex', alignItems: 'center', gap: '12px', marginTop: '8px' } }>
                <Button
                    variant="primary"
                    type="button"
                    onClick={ onManualSave }
                    isBusy={ isSaving }
                    disabled={ isSaving }
                    style={ { width: 'fit-content' } }
                >
                    { __( 'Save Fees', 'checkout-fees-for-woocommerce' ) }
                </Button>
                { noticeUI }
            </div>

        </div>
    );
}

export default withNotices( ProductMetabox );