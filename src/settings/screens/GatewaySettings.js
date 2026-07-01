/**
 * src/settings/screens/GatewaySettings.js
 *
 * Payment Gateway settings screen.
 *
 * Layout:
 *  - Horizontal gateway tab bar (Direct bank transfer | Check payments | Cash on delivery …)
 *  - Below tabs: three separate SectionCards matching the General/GlobalExtraFee pattern
 *      1. Gateway Fee Configuration   (fee_1)
 *      2. Additional Fee (Optional)   (fee_2)
 *      3. General Options             (general)
 *  - Save Changes + per-section Reset buttons at the bottom
 */

import { useState, useEffect } from '@wordpress/element';
import { __ }                  from '@wordpress/i18n';
import {
    Button,
    CheckboxControl,
    SelectControl,
    __experimentalInputControl  as InputControl,
    __experimentalText          as Text,
    __experimentalHeading       as Heading,
    __experimentalVStack        as VStack,
    __experimentalHStack        as HStack,
    __experimentalConfirmDialog as ConfirmDialog,
    Spinner,
    withNotices,
} from '@wordpress/components';
import ProNotice, { ProInlineNotice } from '../components/ProNotice';
import Select from 'react-select';
import { useForm, Controller } from 'react-hook-form';
import HelpTip from '../components/HelpTip';
import { getGatewaySettings, updateGatewaySettings, resetGatewaySection, resetGatewayAll } from '../api';
import CardRulesEditor from '../components/CardRulesEditor';
import { useSettings } from '../context/SettingsContext';

// ─── Toggle this constant for Lite / Pro ───────────────────────────────────
const IS_PRO = false; // Set to true for Pro version

// ─── Defaults ─────────────────────────────────────────────────────────────────

const FEE_DEFAULTS = {
    enabled          : false,
    title            : '',
    type             : 'fixed',
    value            : 0,
    min_fee          : 0,
    max_fee          : 0,
    coupons_rule     : 'disabled',
    countries_include: [],
    countries_exclude: [],
    states_include   : [],
    states_exclude   : [],
    cats_include     : [],
    cats_exclude     : [],
    shipping_include : [],
    shipping_exclude : [],
};

const GATEWAY_DEFAULTS = {
    fee_1: { ...FEE_DEFAULTS },
    fee_2: { ...FEE_DEFAULTS },
    general: {
        min_cart_amount       : 0,
        max_cart_amount       : 0,
        rounding_enabled      : false,
        rounding_precision    : 0,
        tax_enabled           : false,
        tax_class             : '',
        exclude_shipping      : false,
        add_taxes             : false,
        countries_include     : [],
        countries_exclude     : [],
        cats_include_calc_type: '',
        cats_exclude_calc_type: '',
    },
    card_rules: {
        enabled                  : false,
        show_card_payment_display: false,
        rules                    : [],
    },
};

// ─── Layout helpers ──────────────────────────────────────────────────────────

function SectionCard( { heading, description, children } ) {
    return (
        <div style={ {
            background: '#fff', border: '1px solid #e0e0e0',
            borderRadius: '8px', padding: '24px',
        } }>
            { heading && <Heading level={ 4 } style={ { margin: '0 0 4px' } }>{ heading }</Heading> }
            { description && (
                <Text style={ { color: '#646970', fontSize: '13px', display: 'block', marginBottom: '16px' } }>
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

// ─── Component ────────────────────────────────────────────────────────────────

function GatewaySettings( { noticeOperations, noticeUI } ) {
    const [ selectedGateway, setSelectedGateway ] = useState( null );
    const [ loadingGateway,  setLoadingGateway  ] = useState( false );
    const [ showLoader,      setShowLoader      ] = useState( false );
    const [ isResetOpen,     setIsResetOpen     ] = useState( false );

    const { gateways, options, isLoading: globalLoading, loadedSections, fetchSection, refreshSection } = useSettings();

    const { control, handleSubmit, reset, watch } = useForm( { defaultValues: GATEWAY_DEFAULTS } );

    const taxEnabled      = watch( 'general.tax_enabled' );
    const roundingEnabled = watch( 'general.rounding_enabled' );
    const cardRulesEnabled = watch( 'card_rules.enabled' );
    const gwTitle         = selectedGateway?.title || '';
    const supportsCard    = gateways?.find( ( g ) => g.id === selectedGateway?.id )?.supports_card ?? false;

    // Option lists from context
    const countryOptions  = ( options?.countries         || [] ).map( ( c ) => ( { value: c.value, label: c.label } ) );
    const stateOptions    = ( options?.states            || [] ).map( ( c ) => ( { value: c.value, label: c.label } ) );
    const shippingOptions = ( options?.shipping_methods  || [] ).map( ( s ) => ( { value: s.value, label: s.label } ) );
    const catOptions      = ( options?.product_categories || [] ).map( ( c ) => ( { value: c.value, label: c.label } ) );
    const bankNameOptions = ( options?.bank_names        || [] ).map( ( b ) => ( { value: b.value, label: b.label } ) );
    const cardSchemeOptions = ( options?.card_schemes    || [] ).map( ( b ) => ( { value: b.value, label: b.label } ) );

    const taxClassOptions = options?.tax_classes || [];

    useEffect( () => {
        if ( gateways?.length && ! selectedGateway ) handleSelectGateway( gateways[ 0 ] );
    }, [ gateways ] );

    useEffect( () => {
        if ( ! loadedSections.gateways ) fetchSection( 'gateways' );
        if ( ! loadedSections.options  ) fetchSection( 'options'  );
    }, [] );

    // ── Gateway selection ─────────────────────────────────────────────────────

    const handleSelectGateway = async ( gw ) => {
        setSelectedGateway( gw );
        setLoadingGateway( true );
        try {
            const data = await getGatewaySettings( gw.id );
            reset( { ...GATEWAY_DEFAULTS, ...data } );
        } catch {
            reset( GATEWAY_DEFAULTS );
        } finally {
            setLoadingGateway( false );
        }
    };

    // ── Save ──────────────────────────────────────────────────────────────────

    const onSubmit = async ( data ) => {
        if ( ! selectedGateway ) return;
        setShowLoader( true );
        try {
            await updateGatewaySettings( selectedGateway.id, data );
            await refreshSection( 'gateways' );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Gateway settings saved successfully.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Error saving settings. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        } finally {
            setShowLoader( false );
        }
    };

    // ── Reset all gateway settings ────────────────────────────────────────────

    const handleResetAll = async () => {
        setIsResetOpen( false );
        setShowLoader( true );
        try {
            const defaults = await resetGatewayAll( selectedGateway.id );
            reset( { ...GATEWAY_DEFAULTS, ...defaults } );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( { status: 'success', content: __( 'Gateway settings reset to defaults.', 'checkout-fees-for-woocommerce' ) } );
        } catch {
            noticeOperations.createNotice( { status: 'error', content: __( 'Reset failed. Please try again.', 'checkout-fees-for-woocommerce' ) } );
        } finally {
            setShowLoader( false );
        }
    };

    // ── Multi-select helper (now accepts `disabled` prop) ──────────────────

    const MultiSelect = ( { name, options: opts, placeholder, disabled = false } ) => (
        <Controller name={ name } control={ control }
            render={ ( { field } ) => (
                <Select isMulti options={ opts }
                    isDisabled={ disabled }
                    value={ opts.filter( ( o ) => ( field.value || [] ).map( String ).includes( String( o.value ) ) ) }
                    onChange={ ( sel ) => field.onChange( sel ? sel.map( ( s ) => s.value ) : [] ) }
                    placeholder={ placeholder }
                    styles={ { container: ( b ) => ( { ...b, maxWidth: '100%', width: '360px' } ) } }
                />
            ) }
        />
    );

    // ── Fee card renderer (reused for fee_1 and fee_2) ────────────────────────

    const renderFeeCard = ( prefix, heading, description, showEnable = true, showHeading = true ) => (
        <SectionCard heading={ showHeading ? heading : undefined } description={ showHeading ? description : undefined }>

            { showEnable && (
                <SettingRow
                    label={ `${ __( 'Enable fees for', 'checkout-fees-for-woocommerce' ) } "${ gwTitle }"` }
                >
                    <Controller name={ `${ prefix }.enabled` } control={ control }
                        render={ ( { field } ) => (
                            <div style={{ display: 'flex', marginLeft: '32px' }}>
                                <CheckboxControl
                                    checked={ !! field.value }
                                    onChange={ field.onChange }
                                />
                            </div>
                        ) }
                    />
                </SettingRow>
            ) }

            <SettingRow
                label={ __( 'Fee Label (shown at checkout)', 'checkout-fees-for-woocommerce' ) }
            >
                <Controller name={ `${ prefix }.title` } control={ control }
                    render={ ( { field } ) => (
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Name shown to customers at checkout. Example: \'Card Processing Fee\'.', 'checkout-fees-for-woocommerce' ) } />
                            <InputControl value={ field.value } onChange={ field.onChange }
                                placeholder={ __( 'e.g. Payment processing fee', 'checkout-fees-for-woocommerce' ) }
                                style={ { maxWidth: '360px', width: '360px' } }
                            />
                        </div>
                    ) }
                />
            </SettingRow>

            <SettingRow
                label={ __( 'Fee Configuration', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Set the fee type and amount', 'checkout-fees-for-woocommerce' ) }
            >
                <VStack spacing={ 3 }>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <HelpTip message={ __( 'Fee or discount type. Percentage or fixed value', 'checkout-fees-for-woocommerce' ) } />
                        <div>
                            <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                { __( 'Fee Type', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                            <Controller name={ `${ prefix }.type` } control={ control }
                                render={ ( { field } ) => (
                                    <SelectControl value={ field.value } onChange={ field.onChange }
                                        options={ [
                                            { value: 'fixed',   label: __( 'Fixed Amount', 'checkout-fees-for-woocommerce' ) },
                                            { value: 'percent', label: __( 'Percentage',   'checkout-fees-for-woocommerce' ) },
                                        ] }
                                        style={ { maxWidth: '360px', width: '360px' } }
                                    />
                                ) }
                            />
                        </div>
                    </div>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <HelpTip message={ __( 'Fee or discount amount. For discount, enter negative value.', 'checkout-fees-for-woocommerce' ) } />
                        <div>
                            <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                { __( 'Fee Amount', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                            <Controller name={ `${ prefix }.value` } control={ control }
                                render={ ( { field } ) => (
                                    <InputControl type="number" value={ field.value } onChange={ field.onChange }
                                        style={ { maxWidth: '360px', width: '360px' } }
                                    />
                                ) }
                            />
                        </div>
                    </div>
                </VStack>
            </SettingRow>

            <SettingRow
                label={ __( 'Minimum & Maximum Fees Amount', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Useful for Percentage fees. Enter 0 for no limit.', 'checkout-fees-for-woocommerce' ) }
            >
                <VStack spacing={ 3 }>
                    <div style={{ marginLeft: '32px' }}>
                        <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                            { __( 'Minimum', 'checkout-fees-for-woocommerce' ) }
                        </Text>
                        <Controller name={ `${ prefix }.min_fee` } control={ control }
                            render={ ( { field } ) => (
                                <InputControl type="number" value={ field.value } onChange={ field.onChange }
                                    style={ { maxWidth: '360px' } }
                                />
                            ) }
                        />
                        <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                            { __( 'Maximum', 'checkout-fees-for-woocommerce' ) }
                        </Text>
                        <Controller name={ `${ prefix }.max_fee` } control={ control }
                            render={ ( { field } ) => (
                                <InputControl type="number" value={ field.value } onChange={ field.onChange }
                                    style={ { maxWidth: '360px' } }
                                />
                            ) }
                        />
                    </div>
                </VStack>
            </SettingRow>

            { /* Coupons Rule – with ProInlineNotice and disabled */ }
            <SettingRow
                label={ __( 'Coupons Rule', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Control whether the fee applies when coupons are used', 'checkout-fees-for-woocommerce' ) }
            >
                <Controller name={ `${ prefix }.coupons_rule` } control={ control }
                    render={ ( { field } ) => (
                        <div className="pgbf-select-div" style={{ marginLeft: '32px', maxWidth: '360px' }}>
                            <ProInlineNotice />
                            <div style={{ marginTop: '8px' }}>
                                <SelectControl value={ field.value } onChange={ field.onChange }
                                    disabled={ ! IS_PRO }
                                    options={ [
                                        { value: 'disabled',           label: __( 'Disabled',      'checkout-fees-for-woocommerce' ) },
                                        { value: 'only_if_no_coupons', label: __( 'Only if no coupons applied',  'checkout-fees-for-woocommerce' ) },
                                        { value: 'only_if_coupons',    label: __( 'Only if coupons are applied', 'checkout-fees-for-woocommerce' ) },
                                    ] }
                                    style={ { maxWidth: '360px', width: '360px' } }
                                />
                            </div>
                        </div>
                    ) }
                />
            </SettingRow>

            { /* Customer Billing Countries – with notice and disabled */ }
            <SettingRow
                label={ __( 'Customer Billing Countries', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Based on billing address. Leave blank to apply to all countries.', 'checkout-fees-for-woocommerce' ) }
            >
                <div style={{ marginLeft: '32px', maxWidth: '360px', width: '100%' }}>
                    <ProInlineNotice />
                    <div style={{ marginTop: '12px' }}>
                        <div style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if customer\'s billing country is in the list. Leave empty to apply for all countries.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect className="pgbf-multiselect" name={ `${ prefix }.countries_include` } options={ countryOptions }
                                placeholder={ __( 'Countries to include…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if customer\'s billing country is NOT in the list. Ignored if empty.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect className="pgbf-multiselect" name={ `${ prefix }.countries_exclude` } options={ countryOptions }
                                placeholder={ __( 'Countries to exclude…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                    </div>
                </div>
            </SettingRow>

            { /* Customer Billing States – with notice and disabled */ }
            <SettingRow
                label={ __( 'Customer Billing States', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Comma‑separated list of state codes (e.g., MH, UP, CA). Leave blank to apply to all states.', 'checkout-fees-for-woocommerce' ) }
            >
                <div style={{ marginLeft: '32px', maxWidth: '360px', width: '100%' }}>
                    <ProInlineNotice />
                    <div style={{ marginTop: '12px' }}>
                        <div style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if customer\'s billing state is in the list. Leave empty to apply for all states.', 'checkout-fees-for-woocommerce' ) } />
                            <Controller name={ `${ prefix }.states_include` } control={ control }
                                render={ ( { field } ) => (
                                    <InputControl
                                        disabled={ ! IS_PRO }
                                        value={ Array.isArray( field.value ) ? field.value.join( ',' ) : field.value || '' }
                                        onChange={ ( val ) => field.onChange( val ? val.split( ',' ).map( s => s.trim() ) : [] ) }
                                        placeholder={ __( 'e.g., MH, UP, CA', 'checkout-fees-for-woocommerce' ) }
                                        style={ { maxWidth: '360px', width: '360px' } }
                                    />
                                ) }
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if customer\'s billing state is NOT in the list. Ignored if empty.', 'checkout-fees-for-woocommerce' ) } />
                            <Controller name={ `${ prefix }.states_exclude` } control={ control }
                                render={ ( { field } ) => (
                                    <InputControl
                                        disabled={ ! IS_PRO }
                                        value={ Array.isArray( field.value ) ? field.value.join( ',' ) : field.value || '' }
                                        onChange={ ( val ) => field.onChange( val ? val.split( ',' ).map( s => s.trim() ) : [] ) }
                                        placeholder={ __( 'e.g., RJ, DL, NY', 'checkout-fees-for-woocommerce' ) }
                                        style={ { maxWidth: '360px', width: '360px' } }
                                    />
                                ) }
                            />
                        </div>
                    </div>
                </div>
            </SettingRow>

            { /* Product Categories – with notice and disabled */ }
            <SettingRow
                label={ __( 'Product Categories (Fee Condition)', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Apply fee only when cart contains items from selected categories.', 'checkout-fees-for-woocommerce' ) }
            >
                <div style={{ marginLeft: '32px', maxWidth: '360px', width: '100%' }}>
                    <ProInlineNotice />
                    <div style={{ marginTop: '12px' }}>
                        <div style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if product of selected category(-ies) is in the cart. Leave empty to apply for all categories..', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect name={ `${ prefix }.cats_include` } options={ catOptions }
                                placeholder={ __( 'Categories to include…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if NO product of selected category(-ies) is in the cart. Ignored if empty.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect name={ `${ prefix }.cats_exclude` } options={ catOptions }
                                placeholder={ __( 'Categories to exclude…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                    </div>
                </div>
            </SettingRow>

            { /* Shipping Methods – with notice and disabled */ }
            <SettingRow
                label={ __( 'Shipping Methods to Include / Exclude', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Apply this fee only when a specific shipping method is selected. Leave blank to apply to all methods.', 'checkout-fees-for-woocommerce' ) }
                noBorder
            >
                <div style={{ marginLeft: '32px', maxWidth: '360px', width: '100%' }}>
                    <ProInlineNotice />
                    <div style={{ marginTop: '12px' }}>
                        <div style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if any shipping method selected here will be selected on checkout. Leave empty to apply for all shipping methods.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect name={ `${ prefix }.shipping_include` } options={ shippingOptions }
                                placeholder={ __( 'Shipping methods to include…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if NO shipping method selected here is selected on the checkout page. Ignored if empty.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect name={ `${ prefix }.shipping_exclude` } options={ shippingOptions }
                                placeholder={ __( 'Shipping methods to exclude…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                    </div>
                </div>
            </SettingRow>
        </SectionCard>
    );

    // ── Card Rules card ───────────────────────────────────────────────────────

    const renderCardRulesCard = () => (
        <SectionCard
            heading={ __( 'Card Rules', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Set specific fees based on card type, scheme, issuing country, and bank. Requires BIN API to be configured. Rules are evaluated in order — first match wins.', 'checkout-fees-for-woocommerce' ) }
        >
            <ProNotice /> {/* Top-level notice for the whole section */}

            <SettingRow
                label={ __( 'Enable Card-Based Fee Rules', 'checkout-fees-for-woocommerce' ) }
            >
                <Controller name="card_rules.enabled" control={ control }
                    render={ ( { field } ) => (
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Apply fees or discounts based on the customer\'s card details for this payment gateway. Once enabled, the fees will be automatically applied at checkout according to the rules you configure below.', 'checkout-fees-for-woocommerce' ) } />
                            <CheckboxControl
                                checked={ !! field.value } onChange={ field.onChange }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                    ) }
                />
            </SettingRow>

            { cardRulesEnabled && (
                <div style={ { padding: '40px 0px', borderBottom: '1px solid #eee' } }>
                    <Text style={ { fontWeight: 600, fontSize: '14px', color: '#1d2327', display: 'block', marginBottom: '4px' } }>
                        { __( 'Card Rules', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Text style={ { fontSize: '13px', color: '#646970', lineHeight: 1.5, display: 'block', marginBottom: '16px' } }>
                        { __( 'Configure fees based on card properties. Each rule can match on Card Type, Scheme, Country, Location, and Bank.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Controller name="card_rules.rules" control={ control }
                        render={ ( { field } ) => (
                            <CardRulesEditor
                                rules={ field.value || [] }
                                onChange={ field.onChange }
                                countryOptions={ countryOptions }
                                cardSchemeOptions={ cardSchemeOptions }
                                bankNameOptions={ bankNameOptions }
                                isDisabled={ ! IS_PRO }
                            />
                        ) }
                    />
                </div>
            ) }

            { cardRulesEnabled && (
                <SettingRow
                    label={ __( 'Show Card Details Field at Checkout', 'checkout-fees-for-woocommerce' ) }
                >
                    <Controller name="card_rules.show_card_payment_display" control={ control }
                        render={ ( { field } ) => (
                            <div style={{ display: 'flex', gap: '8px' }}>
                                <HelpTip message={ `${ __( 'Adds card fields to checkout so BIN-based fees can be calculated when using', 'checkout-fees-for-woocommerce' ) } ${ gwTitle }. ${ __( 'Useful for gateways that do not directly capture card details (e.g. PayPal, Braintree, Stripe), allowing you to manually collect the customer\'s card BIN details.', 'checkout-fees-for-woocommerce' ) }` } />
                                <CheckboxControl
                                    checked={ !! field.value } onChange={ field.onChange }
                                    disabled={ ! IS_PRO }
                                />
                            </div>
                        ) }
                    />
                </SettingRow>
            ) }
        </SectionCard>
    );

    // ── General Options card ──────────────────────────────────────────────────

    const renderGeneralCard = () => (
        <SectionCard
            heading={ __( 'General Options', 'checkout-fees-for-woocommerce' ) }
            description={ __( 'Configure cart thresholds, tax handling, and fee calculation behaviour for this gateway', 'checkout-fees-for-woocommerce' ) }
        >
            <SettingRow
                label={ __( 'Cart Amount Requirements', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Apply fees only when cart total is within these amounts (leave 0 for no limit)', 'checkout-fees-for-woocommerce' ) }
            >
                <VStack spacing={ 3 }>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <HelpTip message={ __( 'Minimum cart amount for adding the fee (or discount). Ignored if set to zero.', 'checkout-fees-for-woocommerce' ) } />
                        <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                            { __( 'Minimum cart amount', 'checkout-fees-for-woocommerce' ) }
                        </Text>
                    </div>
                    <div style={{ marginLeft: '32px' }}>
                        <Controller name="general.min_cart_amount" control={ control }
                            render={ ( { field } ) => (
                                <InputControl type="number" min="0" step="any" value={ field.value } onChange={ field.onChange }
                                    style={ { maxWidth: '360px' } } />
                            ) }
                        />
                    </div>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <HelpTip message={ __( 'Maximum cart amount for adding the fee (or discount). Ignored if set to zero.', 'checkout-fees-for-woocommerce' ) } />
                        <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                            { __( 'Maximum cart amount', 'checkout-fees-for-woocommerce' ) }
                        </Text>
                    </div>
                    <div style={{ marginLeft: '32px' }}>
                        <Controller name="general.max_cart_amount" control={ control }
                            render={ ( { field } ) => (
                                <InputControl type="number" min="0" step="any" value={ field.value } onChange={ field.onChange }
                                    style={ { maxWidth: '360px' } } />
                            ) }
                        />
                    </div>
                </VStack>
            </SettingRow>

            <SettingRow
                label={ __( 'Round Fee to Decimal Places', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Rounds fee to the set decimal places before adding to order total (round half up).', 'checkout-fees-for-woocommerce' ) }
            >
                <VStack spacing={ 3 }>
                    <Controller name="general.rounding_enabled" control={ control }
                        render={ ( { field } ) => (
                            <div style={{ marginLeft: '32px' }}>
                                <CheckboxControl
                                    checked={ !! field.value } onChange={ field.onChange }
                                />
                            </div>
                        ) }
                    />
                    { roundingEnabled && (
                        <div style={{ marginLeft: '32px' }}>
                            <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                { __( 'Rounding precision (decimal places)', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                            <Controller name="general.rounding_precision" control={ control }
                                render={ ( { field } ) => (
                                    <InputControl type="number" value={ field.value } onChange={ field.onChange }
                                        style={ { maxWidth: '360px' } } />
                                ) }
                            />
                        </div>
                    ) }
                </VStack>
            </SettingRow>

            <SettingRow
                label={ __( 'Apply Tax to This Fee', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Enable only if your tax rules require fees to be taxed.', 'checkout-fees-for-woocommerce' ) }
            >
                <VStack spacing={ 3 }>
                    <Controller name="general.tax_enabled" control={ control }
                        render={ ( { field } ) => (
                            <div style={{ marginLeft: '32px' }}>
                                <CheckboxControl
                                    checked={ !! field.value } onChange={ field.onChange }
                                />
                            </div>
                        ) }
                    />
                    { taxEnabled && (
                        <div style={{ marginLeft: '32px', maxWidth: '360px' }}>
                            <Text style={ { fontSize: '13px', fontWeight: 500, display: 'block', marginBottom: '6px' } }>
                                { __( 'Tax class', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                            <Controller name="general.tax_class" control={ control }
                                render={ ( { field } ) => (
                                    <SelectControl value={ field.value } onChange={ field.onChange }
                                        options={ taxClassOptions } style={ { maxWidth: '360px' } } />
                                ) }
                            />
                        </div>
                    ) }
                </VStack>
            </SettingRow>

            <SettingRow
                label={ __( 'Cart Calculation', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Control what is included in the cart total when calculating percentage-based fees', 'checkout-fees-for-woocommerce' ) }
            >
                <VStack spacing={ 2 }>
                    <Controller name="general.exclude_shipping" control={ control }
                        render={ ( { field } ) => (
                            <div style={{ marginLeft: '32px' }}>
                                <CheckboxControl
                                    label={ __( 'Exclude shipping from cart total when calculating fees', 'checkout-fees-for-woocommerce' ) }
                                    checked={ !! field.value } onChange={ field.onChange }
                                />
                            </div>
                        ) }
                    />
                    <Controller name="general.add_taxes" control={ control }
                        render={ ( { field } ) => (
                            <div style={{ marginLeft: '32px' }}>
                                <CheckboxControl
                                    label={ __( 'Include taxes in cart total when calculating fees', 'checkout-fees-for-woocommerce' ) }
                                    checked={ !! field.value } onChange={ field.onChange }
                                />
                            </div>
                        ) }
                    />
                </VStack>
            </SettingRow>

            { /* Customer Countries (general level) – with notice and disabled */ }
            <SettingRow
                label={ __( 'Customer Countries', 'checkout-fees-for-woocommerce' ) }
                description={ __( 'Gateway-level country filter applied to all fees for this gateway. Leave blank for all countries.', 'checkout-fees-for-woocommerce' ) }
                noBorder
            >
                <div style={{ marginLeft: '32px', maxWidth: '360px', width: '100%' }}>
                    <ProInlineNotice />
                    <div style={{ marginTop: '12px' }}>
                        <div style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if customer\'s billing country is in the list. Leave empty to apply for all countries.. This is applied to both main and additional fees. Alternatively you can also set customer countries for each fee individually.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect name="general.countries_include" options={ countryOptions }
                                placeholder={ __( 'Countries to include…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                        <div style={{ display: 'flex', gap: '8px' }}>
                            <HelpTip message={ __( 'Fee (or discount) will only be added if customer\'s billing country is NOT in the list. Ignored if empty. This is applied to both main and additional fees. Alternatively you can also set customer countries for each fee individually.', 'checkout-fees-for-woocommerce' ) } />
                            <MultiSelect name="general.countries_exclude" options={ countryOptions }
                                placeholder={ __( 'Countries to exclude…', 'checkout-fees-for-woocommerce' ) }
                                disabled={ ! IS_PRO }
                            />
                        </div>
                    </div>
                </div>
            </SettingRow>

            <SettingRow
                label={ __( 'Product categories - Calculation type', 'checkout-fees-for-woocommerce' ) }
            >
                <Controller name={ 'general.cats_include_calc_type' } control={ control }
                    render={ ( { field } ) => (
                        <div style={{ marginLeft: '32px', maxWidth: '360px' }}>
                            <SelectControl value={ field.value } onChange={ field.onChange }
                                options={ [
                                    { value: 'for_all_cart',       label: __( 'For all cart',      'checkout-fees-for-woocommerce' ) },
                                    { value: 'only_for_selected_products', label: __( 'Only for selected products',  'checkout-fees-for-woocommerce' ) },
                                ] }
                                style={ { maxWidth: '360px' } }
                            />
                            <Text style={ { fontSize: '12px', color: '#646970', marginTop: '4px' } }>
                                { __( 'Categories to include.', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                        </div>
                    ) }
                />
                <Controller name={ 'general.cats_exclude_calc_type' } control={ control }
                    render={ ( { field } ) => (
                        <div style={{ marginLeft: '32px', maxWidth: '360px' }}>
                            <SelectControl value={ field.value } onChange={ field.onChange }
                                options={ [
                                    { value: 'for_all_cart',       label: __( 'For all cart',      'checkout-fees-for-woocommerce' ) },
                                    { value: 'only_for_selected_products', label: __( 'Only for selected products',  'checkout-fees-for-woocommerce' ) },
                                ] }
                                style={ { maxWidth: '360px' } }
                            />
                            <Text style={ { fontSize: '12px', color: '#646970', marginTop: '4px' } }>
                                { __( 'Categories to exclude.', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                        </div>
                    ) }
                />
            </SettingRow>
        </SectionCard>
    );

    // ── Render ────────────────────────────────────────────────────────────────

    if ( globalLoading && ! loadedSections.gateways ) return <Spinner />;

    if ( ! gateways?.length ) {
        return (
            <div style={ { padding: '24px', background: '#fff', border: '1px solid #e0e0e0', borderRadius: '8px' } }>
                <Text>{ __( 'No active payment gateways found. Please enable at least one payment gateway in WooCommerce → Settings → Payments.', 'checkout-fees-for-woocommerce' ) }</Text>
            </div>
        );
    }

    return (
        <VStack spacing={ 4 }>
            <VStack spacing={ 1 }>
                <Heading level={ 3 } style={ { margin: 0 } }>
                    { __( 'Payment Gateways', 'checkout-fees-for-woocommerce' ) }
                </Heading>
                <Text style={ { color: '#646970' } }>
                    { __( 'Configure fees and discounts for each payment gateway available in your store', 'checkout-fees-for-woocommerce' ) }
                </Text>
            </VStack>

            <div style={ {
                display     : 'flex',
                flexWrap    : 'wrap',
                gap         : '0',
                borderBottom: '1px solid #ddd',
            } }>
                { gateways.map( ( gw ) => {
                    const isActive = selectedGateway?.id === gw.id;
                    return (
                        <button
                            key={ gw.id }
                            type="button"
                            onClick={ () => handleSelectGateway( gw ) }
                            className={ `pgbf-dashboard-tab${ isActive ? ' is-active' : '' }` }
                            style={ {
                                padding      : '10px 20px',
                                cursor       : 'pointer',
                                background   : 'none',
                                borderTop    : 'none',
                                borderRight  : 'none',
                                borderLeft   : 'none',
                                fontWeight   : isActive ? 600 : 400,
                                fontSize     : '14px',
                                transition   : 'color 0.15s, border-color 0.15s',
                                whiteSpace   : 'nowrap',
                                marginBottom : '-1px',
                            } }
                        >
                            { gw.title }
                        </button>
                    );
                } ) }
            </div>

            { loadingGateway ? (
                <Spinner />
            ) : selectedGateway ? (
                <form onSubmit={ handleSubmit( onSubmit ) }>
                    <VStack spacing={ 4 }>
                        <SectionCard>
                            <SettingRow
                                label={ `${ __( 'Enable fees for', 'checkout-fees-for-woocommerce' ) } "${ gwTitle }"` }
                                noBorder
                            >
                                <Controller name="fee_1.enabled" control={ control }
                                    render={ ( { field } ) => (
                                        <div style={{ display: 'flex', marginLeft: '32px' }}>
                                            <CheckboxControl
                                                checked={ !! field.value }
                                                onChange={ field.onChange }
                                            />
                                        </div>
                                    ) }
                                />
                            </SettingRow>
                        </SectionCard>

                        { supportsCard && renderCardRulesCard() }

                        { renderFeeCard( 'fee_1', '', '', false, false ) }
                        { renderFeeCard(
                            'fee_2',
                            __( 'Secondary Fee (Optional)', 'checkout-fees-for-woocommerce' ),
                            __( 'Add a second fee — e.g., a fixed handling fee plus a percentage processing fee. This fee is applied automatically when a fee amount is set.', 'checkout-fees-for-woocommerce' ),
                            false
                        ) }

                        { renderGeneralCard() }

                        <HStack spacing={ 3 } style={ { paddingTop: '4px', flexWrap: 'wrap', justifyContent: 'left' } }>
                            <Button variant="primary" type="submit"
                                isBusy={ showLoader } disabled={ showLoader }
                                style={ { width: 'fit-content' } }>
                                { __( 'Save Changes', 'checkout-fees-for-woocommerce' ) }
                            </Button>
                            <Button variant="secondary" type="button" disabled={ showLoader }
                                onClick={ () => setIsResetOpen( true ) }
                                style={ { width: 'fit-content' } }>
                                { __( 'Reset Settings', 'checkout-fees-for-woocommerce' ) }
                            </Button>
                        </HStack>
                        { noticeUI }
                    </VStack>
                </form>
            ) : null }

            { isResetOpen && (
                <ConfirmDialog
                    onConfirm={ handleResetAll }
                    onCancel={ () => setIsResetOpen( false ) }
                >
                    { __( 'Reset all settings for this gateway to defaults? This cannot be undone.', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
        </VStack>
    );
}

export default withNotices( GatewaySettings );