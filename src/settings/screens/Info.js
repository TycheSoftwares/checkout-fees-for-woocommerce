/**
 * src/settings/screens/Info.js
 *
 * Info on Single Product — layout matches the screenshot:
 *
 *  One SectionCard with a shortcodes reference block at top, then four
 *  SettingRow entries:
 *    1. "Info on single product page"          → grouped fields on right
 *    2. "Lowest price info on single product page" → grouped fields on right
 *    3. "Hide info when Out of Stock"          → checkbox on right
 *    4. "Variable products info"               → select on right
 *
 *  Save Changes + Reset Settings buttons below the card.
 */

import { useState, useEffect } from '@wordpress/element';
import { __ }                  from '@wordpress/i18n';
import {
    Button,
    CheckboxControl,
    SelectControl,
    TextareaControl,
    __experimentalInputControl  as InputControl,
    __experimentalVStack        as VStack,
    __experimentalHStack        as HStack,
    __experimentalText          as Text,
    __experimentalHeading       as Heading,
    __experimentalConfirmDialog as ConfirmDialog,
    Spinner,
    withNotices,
} from '@wordpress/components';
import { useForm, Controller } from 'react-hook-form';

import { updateSettings, resetSection } from '../api';
import { useSettings } from '../context/SettingsContext';
import HelpTip from '../components/HelpTip';
import { ProInlineNotice } from '../components/ProNotice';

// ─── Toggle this constant for Lite / Pro ───────────────────────────────────
const IS_PRO = false; // Set to true for Pro version

// ─── Constants ────────────────────────────────────────────────────────────────

const DEFAULTS = {
    product_page: {
        enabled   : false,
        start_html: '<table>',
        row_html  : '<tr><td><strong>%gateway_title%</strong></td><td>%product_original_price%</td><td>%product_gateway_price%</td><td>%product_price_diff%</td></tr>',
        end_html  : '</table>',
        position  : 'woocommerce_single_product_summary',
        priority  : 20,
    },
    lowest_price: {
        enabled      : false,
        template_html: '<p><strong>%gateway_title%</strong> %product_gateway_price% (%product_price_diff%)</p>',
        position     : 'woocommerce_single_product_summary',
        priority     : 20,
    },
    hide_on_out_of_stock  : false,
    variable_info_display : 'for_each_variation',
};

const POSITION_OPTIONS = [
    { value: 'woocommerce_single_product_summary',        label: __( 'Inside product summary',       'checkout-fees-for-woocommerce' ) },
    { value: 'woocommerce_before_single_product_summary', label: __( 'Before product summary',       'checkout-fees-for-woocommerce' ) },
    { value: 'woocommerce_after_single_product_summary',  label: __( 'After product summary',        'checkout-fees-for-woocommerce' ) },
    { value: 'woocommerce_before_add_to_cart_button',     label: __( 'Before add to cart button',    'checkout-fees-for-woocommerce' ) },
    { value: 'woocommerce_after_add_to_cart_button',      label: __( 'After add to cart button',     'checkout-fees-for-woocommerce' ) },
];

// ─── Layout helpers (same pattern as General / GlobalExtraFee) ────────────────

function SectionCard( { heading, children } ) {
    return (
        <div style={ {
            background  : '#fff',
            border      : '1px solid #e0e0e0',
            borderRadius: '8px',
            padding     : '24px',
        } }>
            { heading && (
                <Heading level={ 4 } style={ { margin: '0 0 20px' } }>
                    { heading }
                </Heading>
            ) }
            { children }
        </div>
    );
}

/**
 * SettingRow — label + description on the left (280px), content on the right.
 * Used for the four main rows in this screen.
 */
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
                <Text style={ {
                    fontWeight  : 600,
                    fontSize    : '14px',
                    color       : '#1d2327',
                    display     : 'block',
                    marginBottom: '4px',
                } }>
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

/**
 * SubLabel — small bold label above a field within the right column,
 * matching the pattern used in GlobalExtraFee for grouped fields.
 */
function SubLabel( { children } ) {
    return (
        <Text style={ {
            fontSize    : '13px',
            fontWeight  : 500,
            display     : 'block',
            marginBottom: '6px',
            marginTop   : '14px',
            color       : '#1d2327',
        } }>
            { children }
        </Text>
    );
}

// ─── Component ────────────────────────────────────────────────────────────────

function Info( { noticeOperations, noticeUI } ) {
    const [ showLoader,  setShowLoader  ] = useState( false );
    const [ isResetOpen, setIsResetOpen ] = useState( false );

    const {
        settings,
        isLoading: globalLoading,
        loadedSections,
        fetchSection,
        updateSettingsData,
    } = useSettings();

    const { control, handleSubmit, reset } = useForm( {
        defaultValues: DEFAULTS,
    } );

    useEffect( () => {
        if ( settings?.info && loadedSections.settings ) {
            reset( { ...DEFAULTS, ...settings.info } );
        }
    }, [ settings, loadedSections.settings, reset ] );

    useEffect( () => {
        if ( ! loadedSections.settings ) fetchSection( 'settings' );
    }, [] ); // eslint-disable-line

    // ── Handlers ──────────────────────────────────────────────────────────────

    const onSubmit = async ( data ) => {
        setShowLoader( true );
        try {
            const merged = { ...( settings || {} ), info: data };
            await updateSettings( merged );
            updateSettingsData( 'settings', merged );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( {
                status : 'success',
                content: __( 'Settings saved successfully.', 'checkout-fees-for-woocommerce' ),
            } );
        } catch {
            noticeOperations.createNotice( {
                status : 'error',
                content: __( 'Error saving settings.', 'checkout-fees-for-woocommerce' ),
            } );
        } finally { setShowLoader( false ); }
    };

    const onReset = async () => {
        setIsResetOpen( false );
        setShowLoader( true );
        try {
            const defaults = await resetSection( 'info' );
            reset( { ...DEFAULTS, ...defaults } );
            updateSettingsData( 'settings', { ...( settings || {} ), info: defaults } );
            noticeOperations.removeAllNotices();
            noticeOperations.createNotice( {
                status : 'success',
                content: __( 'Settings reset to defaults.', 'checkout-fees-for-woocommerce' ),
            } );
        } catch {
            noticeOperations.createNotice( {
                status : 'error',
                content: __( 'Reset failed.', 'checkout-fees-for-woocommerce' ),
            } );
        } finally { setShowLoader( false ); }
    };

    if ( globalLoading && ! loadedSections.settings ) return <Spinner />;

    // ── Shortcodes reference block ─────────────────────────────────────────────

    const ShortcodesHelp = () => (
        <div style={ {
            padding      : '14px 16px',
            background   : '#f6f7f7',
            border       : '1px solid #e0e0e0',
            borderRadius : '6px',
            marginBottom : '20px',
            fontSize     : '13px',
            lineHeight   : 1.7,
            color        : '#1d2327',
        } }>
            <Text style={ { display: 'block', marginBottom: '6px', color: '#1d2327', fontSize: '13px' } }>
                { __( 'Values that will be replaced in templates below are:', 'checkout-fees-for-woocommerce' ) }{ ' ' }
                { [
                    '%gateway_title%', '%gateway_description%', '%gateway_icon%',
                    '%product_title%', '%product_gateway_price%', '%product_variation_atts%',
                    '%product_original_price%', '%product_price_diff%', '%product_price_diff_percent%',
                ].map( ( ph, i, arr ) => (
                    <span key={ ph }>
                        <code style={ {
                            background  : '#e8f0fe',
                            color       : '#1a56db',
                            padding     : '1px 5px',
                            borderRadius: '3px',
                            fontSize    : '12px',
                        } }>{ ph }</code>
                        { i < arr.length - 1 ? ', ' : '.' }
                    </span>
                ) ) }
            </Text>
            <Text style={ { display: 'block', color: '#1d2327', fontSize: '13px' } }>
                { __( 'You can also use', 'checkout-fees-for-woocommerce' ) }{ ' ' }
                { [ '[alg_show_checkout_fees_full_info]', '[alg_show_checkout_fees_lowest_price_info]' ].map( ( sc, i, arr ) => (
                    <span key={ sc }>
                        <code style={ {
                            background  : '#e8f0fe',
                            color       : '#1a56db',
                            padding     : '1px 5px',
                            borderRadius: '3px',
                            fontSize    : '12px',
                        } }>{ sc }</code>
                        { i < arr.length - 1 ? ` ${ __( 'and', 'checkout-fees-for-woocommerce' ) } ` : '' }
                    </span>
                ) ) }
                { ' ' }{ __( 'shortcodes. Or', 'checkout-fees-for-woocommerce' ) }{ ' ' }
                <code style={ { background: '#e8f0fe', color: '#1a56db', padding: '1px 5px', borderRadius: '3px', fontSize: '12px' } }>
                    do_shortcode('[alg_show_checkout_fees_full_info]');
                </code>
                { ' ' }{ __( 'and', 'checkout-fees-for-woocommerce' ) }{ ' ' }
                <code style={ { background: '#e8f0fe', color: '#1a56db', padding: '1px 5px', borderRadius: '3px', fontSize: '12px' } }>
                    do_shortcode('[alg_show_checkout_fees_lowest_price_info]');
                </code>
                { ' ' }{ __( 'functions.', 'checkout-fees-for-woocommerce' ) }
            </Text>
        </div>
    );

    return (
        <VStack spacing={ 4 }>

            <form onSubmit={ handleSubmit( onSubmit ) }>
                <VStack spacing={ 4 }>

                    <SectionCard
                        heading={ __( 'Info on single product page', 'checkout-fees-for-woocommerce' ) }
                        >

                        { /* Shortcodes reference — full width above the rows */ }
                        <ShortcodesHelp />

                        { /* ── Row 1: Info on single product page ── */ }
                        <SettingRow
                            label={ __( 'Info on single product page', 'checkout-fees-for-woocommerce' ) }
                        >
                            <VStack spacing={ 0 }>
                                { /* Show checkbox */ }
                                <Controller name="product_page.enabled" control={ control }
                                    render={ ( { field } ) => (
                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            <HelpTip message={ __( 'This option displays gateway fees on each product page.', 'checkout-fees-for-woocommerce' ) } />

                                            <CheckboxControl
                                                checked={ !! field.value }
                                                onChange={ field.onChange }
                                            />
                                        </div>
                                    ) }
                                />

                                <div style={ { marginLeft: '32px', maxWidth: '560px' } }>

                                    <SubLabel>{ __( 'Start HTML', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <Controller name="product_page.start_html" control={ control }
                                        render={ ( { field } ) => (
                                            <TextareaControl value={ field.value } onChange={ field.onChange } rows={ 2 }
                                                style={ { fontFamily: 'monospace', fontSize: '13px' } } />
                                        ) }
                                    />

                                    <SubLabel>{ __( 'Fee Row Layout (HTML Template)', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <Controller name="product_page.row_html" control={ control }
                                        render={ ( { field } ) => (
                                            <TextareaControl value={ field.value } onChange={ field.onChange } rows={ 3 }
                                                style={ { fontFamily: 'monospace', fontSize: '13px' } } />
                                        ) }
                                    />
                                    <Text style={ { fontSize: '12px', color: '#646970', marginTop: '4px' } }>
                                        { __( 'Use template variables above to customise each fee row\'s display.', 'checkout-fees-for-woocommerce' ) }
                                    </Text>

                                    <SubLabel>{ __( 'End HTML', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <Controller name="product_page.end_html" control={ control }
                                        render={ ( { field } ) => (
                                            <TextareaControl value={ field.value } onChange={ field.onChange } rows={ 2 }
                                                style={ { fontFamily: 'monospace', fontSize: '13px' } } />
                                        ) }
                                    />

                                    <SubLabel>{ __( 'Position', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <div style={ { maxWidth: '360px' } }>
                                        <Controller name="product_page.position" control={ control }
                                            render={ ( { field } ) => (
                                                <SelectControl value={ field.value } onChange={ field.onChange }
                                                    options={ POSITION_OPTIONS } />
                                            ) }
                                        />
                                    </div>

                                    <SubLabel>{ __( 'Position priority (i.e. order)', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <Controller name="product_page.priority" control={ control }
                                        render={ ( { field } ) => (
                                            <InputControl type="number" value={ String( field.value ) }
                                                onChange={ field.onChange } min={ 1 }
                                                style={ { maxWidth: '360px' } } />
                                        ) }
                                    />
                                    <Text style={ { fontSize: '12px', color: '#646970', marginTop: '4px' } }>
                                        { __( 'Position priority (i.e. order)', 'checkout-fees-for-woocommerce' ) }
                                    </Text>
                                </div>
                            </VStack>
                        </SettingRow>

                        { /* ── Row 2: Lowest price info on single product page ── */ }
                        <SettingRow
                            label={ __( 'Lowest price info on single product page', 'checkout-fees-for-woocommerce' ) }
                        >
                            <VStack spacing={ 0 }>
                                { /* Show checkbox */ }
                                <Controller name="lowest_price.enabled" control={ control }
                                    render={ ( { field } ) => (
                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            <HelpTip message={ __( 'Highlights the gateway with the lowest total price on each product page.', 'checkout-fees-for-woocommerce' ) } />
                                            <CheckboxControl
                                                checked={ !! field.value }
                                                onChange={ field.onChange }
                                            />
                                        </div>
                                    ) }
                                />

                                <div style={ { marginLeft: '32px', maxWidth: '560px' } }>

                                    <SubLabel>{ __( 'Template HTML', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <Controller name="lowest_price.template_html" control={ control }
                                        render={ ( { field } ) => (
                                            <TextareaControl value={ field.value } onChange={ field.onChange } rows={ 3 }
                                                style={ { fontFamily: 'monospace', fontSize: '13px' } } />
                                        ) }
                                    />

                                    <SubLabel>{ __( 'Position', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <div style={ { maxWidth: '360px' } }>
                                        <Controller name="lowest_price.position" control={ control }
                                            render={ ( { field } ) => (
                                                <SelectControl value={ field.value } onChange={ field.onChange }
                                                style={ { maxWidth: '360px', width: '360px' } }
                                                    options={ POSITION_OPTIONS } />
                                            ) }
                                        />
                                    </div>

                                    <SubLabel>{ __( 'Position priority (i.e. order)', 'checkout-fees-for-woocommerce' ) }</SubLabel>
                                    <Controller name="lowest_price.priority" control={ control }
                                        render={ ( { field } ) => (
                                            <InputControl type="number" value={ String( field.value ) }
                                                onChange={ field.onChange } min={ 1 }
                                                style={ { maxWidth: '360px', width: '360px' } } />
                                        ) }
                                    />
                                    <Text style={ { fontSize: '12px', color: '#646970', marginTop: '4px' } }>
                                        { __( 'Position priority (i.e. order)', 'checkout-fees-for-woocommerce' ) }
                                    </Text>
                                </div>
                            </VStack>
                        </SettingRow>

                        { /* ── Row 3: Hide info when Out of Stock (Pro-only in Lite) ── */ }
                        <SettingRow
                            label={ __( 'Hide Fee Info for Out-of-Stock Products', 'checkout-fees-for-woocommerce' ) }
                        >
                            <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                                <Controller name="hide_on_out_of_stock" control={ control }
                                    render={ ( { field } ) => (
                                        <div style={{ display: 'flex', gap: '8px' }}>
                                            <HelpTip message={ __( 'When the Product is Out of stock, it will hide gateway fee/discount info on a single product frontend page.', 'checkout-fees-for-woocommerce' ) } />
                                            <CheckboxControl
                                                checked={ !! field.value }
                                                onChange={ field.onChange }
                                                disabled={ ! IS_PRO }
                                            />
                                        </div>
                                    ) }
                                />
                                <ProInlineNotice />
                            </div>
                        </SettingRow>

                        { /* ── Row 4: Variable products info ── */ }
                        <SettingRow
                            label={ __( 'Variable Product Fee Display', 'checkout-fees-for-woocommerce' ) }
                            noBorder
                        >
                            <div style={ { marginLeft: '32px',  maxWidth: '360px' } }>
                                <Controller name="variable_info_display" control={ control }
                                    render={ ( { field } ) => (
                                        <div>
                                            <SelectControl
                                                value={ field.value }
                                                onChange={ field.onChange }
                                                options={ [
                                                    { value: 'for_each_variation', label: __( 'For each variation', 'checkout-fees-for-woocommerce' ) },
                                                    { value: 'ranges',             label: __( 'Ranges',             'checkout-fees-for-woocommerce' ) },
                                                ] }
                                            />
                                        </div>
                                    ) }
                                />
                            </div>
                        </SettingRow>

                    </SectionCard>

                    { /* ── Action buttons ── */ }
                    <HStack style={ { paddingTop: '4px', justifyContent: 'left' } }>
                        <Button variant="primary" type="submit"
                            isBusy={ showLoader } disabled={ showLoader }
                            style={ { width: 'fit-content' } }>
                            { __( 'Save Changes', 'checkout-fees-for-woocommerce' ) }
                        </Button>
                        <Button variant="secondary" type="button"
                            disabled={ showLoader }
                            onClick={ () => setIsResetOpen( true ) }
                            style={ { width: 'fit-content' } }>
                            { __( 'Reset Settings', 'checkout-fees-for-woocommerce' ) }
                        </Button>
                    </HStack>
                    { noticeUI }
                </VStack>
            </form>

            { isResetOpen && (
                <ConfirmDialog
                    onConfirm={ onReset }
                    onCancel={ () => setIsResetOpen( false ) }
                >
                    { __( 'Reset Info settings to defaults?', 'checkout-fees-for-woocommerce' ) }
                </ConfirmDialog>
            ) }
        </VStack>
    );
}

export default withNotices( Info );
