/**
 * src/settings/components/CardRulesEditor.js
 *
 * Bank field uses a fixed list from alg_checkout_fees_bank_names() served via
 * GET /pgbf-pro/v1/options → bank_names, identical pattern to Scheme / Country.
 *
 * All three multi-selects (Scheme, Country, Bank) share the same approach:
 *   - options[]  from props
 *   - value[]    filtered from options by stored string values
 *   - onChange   maps selected items back to string[]
 *   - menuPortalTarget={document.body} + menuPosition="fixed" so the dropdown
 *     is never clipped by the table's overflow container
 *   - key={rule._id} on every RuleRow so react-select never reuses stale state
 */

import { useRef }  from '@wordpress/element';
import { __ }      from '@wordpress/i18n';
import {
    Button,
    SelectControl,
    __experimentalInputControl as InputControl,
    __experimentalText         as Text,
} from '@wordpress/components';
import Select from 'react-select';

// ─── Option sets ──────────────────────────────────────────────────────────────

const TYPE_OPTIONS = [
    { value: 'any',    label: __( 'Any',    'checkout-fees-for-woocommerce' ) },
    { value: 'credit', label: __( 'Credit', 'checkout-fees-for-woocommerce' ) },
    { value: 'debit',  label: __( 'Debit',  'checkout-fees-for-woocommerce' ) },
];

const LOCATION_OPTIONS = [
    { value: 'any',           label: __( 'Any',           'checkout-fees-for-woocommerce' ) },
    { value: 'domestic',      label: __( 'Domestic',      'checkout-fees-for-woocommerce' ) },
    { value: 'international', label: __( 'International', 'checkout-fees-for-woocommerce' ) },
];

const FEE_TYPE_OPTIONS = [
    { value: 'fixed',   label: __( 'Fixed',   'checkout-fees-for-woocommerce' ) },
    { value: 'percent', label: __( 'Percent', 'checkout-fees-for-woocommerce' ) },
];

// ─── Shared react-select style factory ───────────────────────────────────────

const makeStyles = ( minW = 160 ) => ( {
    container : ( b ) => ( { ...b, minWidth: minW } ),
    control   : ( b ) => ( { ...b, minHeight: 34, fontSize: 13 } ),
    menu      : ( b ) => ( { ...b, fontSize: 13, zIndex: 9999 } ),
    multiValue: ( b ) => ( { ...b, fontSize: 12 } ),
    option    : ( b ) => ( { ...b, fontSize: 13 } ),
} );

// Portal props — prevent menu clipping inside overflow:auto table container
const PORTAL = {
    menuPortalTarget : typeof document !== 'undefined' ? document.body : null,
    menuPosition     : 'fixed',
    closeMenuOnScroll: false,
};

// ─── Stable ID for rule rows ──────────────────────────────────────────────────

let _ctr = Date.now();
const uid = () => String( ++_ctr );

const newRule = () => ( {
    _id     : uid(),
    type    : 'any',
    scheme  : [ 'any' ],
    country : [ 'any' ],
    location: 'any',
    bank    : [ 'Any' ],   // matches PHP default: array('Any')
    fee     : 0,
    fee_type: 'fixed',
} );

// ─── Single rule row ──────────────────────────────────────────────────────────

function RuleRow( {
    rule, index, onChange, onRemove,
    countryOptions, schemeOptions, bankOptions,
} ) {
    const set  = ( key, val ) => onChange( index, { ...rule, [ key ]: val } );
    const cell = { padding: '8px 6px', verticalAlign: 'top' };

    // ── Scheme ────────────────────────────────────────────────────────────────
    const selectedSchemes = schemeOptions.filter( ( o ) =>
        ( rule.scheme || [ 'any' ] ).includes( o.value )
    );

    // ── Country ───────────────────────────────────────────────────────────────
    const selectedCountries = countryOptions.filter( ( o ) =>
        ( rule.country || [ 'any' ] ).includes( o.value )
    );

    // ── Bank ──────────────────────────────────────────────────────────────────
    // PHP stores bank as string array where 'Any' (capital A) means all banks,
    // matching alg_checkout_fees_bank_names() prepended with array('Any').
    const selectedBanks = bankOptions.filter( ( o ) =>
        ( rule.bank || [ 'Any' ] ).includes( o.value )
    );

    return (
        <tr style={ { borderBottom: '1px solid #f0f0f0' } }>

            { /* Card Type */ }
            <td style={ cell }>
                <SelectControl
                    value={ rule.type || 'any' }
                    onChange={ ( v ) => set( 'type', v ) }
                    options={ TYPE_OPTIONS }
                    style={ { minWidth: 110 } }
                />
            </td>

            { /* Scheme — multi */ }
            <td style={ { ...cell, minWidth: 170 } }>
                <Select
                    isMulti
                    instanceId={ `scheme-${ rule._id }` }
                    options={ schemeOptions }
                    value={ selectedSchemes }
                    onChange={ ( sel ) =>
                        set( 'scheme', sel?.length ? sel.map( ( s ) => s.value ) : [ 'any' ] )
                    }
                    placeholder={ __( 'Select…', 'checkout-fees-for-woocommerce' ) }
                    styles={ makeStyles( 160 ) }
                    { ...PORTAL }
                />
            </td>

            { /* Country — multi */ }
            <td style={ { ...cell, minWidth: 190 } }>
                <Select
                    isMulti
                    instanceId={ `country-${ rule._id }` }
                    options={ countryOptions }
                    value={ selectedCountries }
                    onChange={ ( sel ) =>
                        set( 'country', sel?.length ? sel.map( ( s ) => s.value ) : [ 'any' ] )
                    }
                    placeholder={ __( 'Select…', 'checkout-fees-for-woocommerce' ) }
                    styles={ makeStyles( 180 ) }
                    { ...PORTAL }
                />
            </td>

            { /* Location */ }
            <td style={ cell }>
                <SelectControl
                    value={ rule.location || 'any' }
                    onChange={ ( v ) => set( 'location', v ) }
                    options={ LOCATION_OPTIONS }
                    style={ { minWidth: 130 } }
                />
            </td>

            { /* Bank — multi, identical pattern to Scheme / Country */ }
            <td style={ { ...cell, minWidth: 170 } }>
                <Select
                    isMulti
                    instanceId={ `bank-${ rule._id }` }
                    options={ bankOptions }
                    value={ selectedBanks }
                    onChange={ ( sel ) =>
                        set( 'bank', sel?.length ? sel.map( ( s ) => s.value ) : [ 'Any' ] )
                    }
                    placeholder={ __( 'Select…', 'checkout-fees-for-woocommerce' ) }
                    styles={ makeStyles( 160 ) }
                    { ...PORTAL }
                />
            </td>

            { /* Fee */ }
            <td style={ { ...cell, minWidth: 90 } }>
                <InputControl
                    type="number"
                    value={ String( rule.fee ?? 0 ) }
                    onChange={ ( v ) => set( 'fee', v ) }
                    placeholder="0.00"
                    style={ { minWidth: 90 } }
                />
            </td>

            { /* Fee Type */ }
            <td style={ cell }>
                <SelectControl
                    value={ rule.fee_type || 'fixed' }
                    onChange={ ( v ) => set( 'fee_type', v ) }
                    options={ FEE_TYPE_OPTIONS }
                    style={ { minWidth: 100 } }
                />
            </td>

            { /* Remove */ }
            <td style={ { ...cell, textAlign: 'center' } }>
                <button
                    type="button"
                    onClick={ () => onRemove( index ) }
                    title={ __( 'Remove rule', 'checkout-fees-for-woocommerce' ) }
                    aria-label={ __( 'Remove rule', 'checkout-fees-for-woocommerce' ) }
                    style={ {
                        background: 'none', border: 'none',
                        cursor: 'pointer', color: '#cc1818',
                        fontSize: 18, padding: 4, lineHeight: 1,
                    } }
                >🗑</button>
            </td>
        </tr>
    );
}

// ─── Editor ───────────────────────────────────────────────────────────────────

export default function CardRulesEditor( {
    rules             = [],
    onChange,
    countryOptions    = [],
    cardSchemeOptions = [],
    bankNameOptions   = [],
} ) {
    // ── Stable IDs ────────────────────────────────────────────────────────────
    // Maintained in a ref so they never change on re-render, regardless of
    // what the parent stores. This prevents RuleRow unmount/remount on every
    // keystroke (which was caused by emit() stripping _id → uid() generating
    // new keys on the next render → React treating rows as new elements).
    const idsRef = useRef( [] );

    // Grow the array if rules were added, shrink if removed.
    while ( idsRef.current.length < rules.length ) {
        idsRef.current.push( uid() );
    }
    idsRef.current = idsRef.current.slice( 0, rules.length );

    const stable = rules.map( ( r, i ) => ( { ...r, _id: idsRef.current[ i ] } ) );

    // _id is internal — never persisted to DB.
    const emit = ( arr ) => onChange( arr.map( ( { _id, ...rest } ) => rest ) );

    const handleChange = ( idx, updated ) =>
        emit( stable.map( ( r, i ) => ( i === idx ? updated : r ) ) );

    const handleRemove = ( idx ) =>
        emit( stable.filter( ( _, i ) => i !== idx ) );

    const handleAdd = () =>
        emit( [ ...stable, newRule() ] );

    // Build option lists — each multi-select prepends 'Any' / 'any' at top
    // Scheme: 'any' (lowercase) — matches PHP alg_checkout_fees_card_scheme()
    const schemeOpts = cardSchemeOptions.some( ( o ) => o.value === 'any' )
        ? cardSchemeOptions
        : [ { value: 'any', label: __( 'Any', 'checkout-fees-for-woocommerce' ) }, ...cardSchemeOptions ];

    // Country: 'any' (lowercase) — prepend to WC countries list
    const countryOpts = [
        { value: 'any', label: __( 'Any', 'checkout-fees-for-woocommerce' ) },
        ...countryOptions,
    ];

    // Bank: 'Any' (capital A) — matches PHP array_merge(array('Any'), alg_checkout_fees_bank_names())
    // bankNameOptions already has 'Any' as first item from the REST controller
    const bankOpts = bankNameOptions.length > 0
        ? bankNameOptions
        : [ { value: 'Any', label: __( 'Any', 'checkout-fees-for-woocommerce' ) } ];

    const th = {
        padding     : '8px 6px',
        textAlign   : 'left',
        fontWeight  : 600,
        fontSize    : 12,
        color       : '#646970',
        borderBottom: '2px solid #e0e0e0',
        whiteSpace  : 'nowrap',
        background  : '#f9f9f9',
    };

    return (
        <div style={ { width: '100%' } }>

            { stable.length === 0 ? (
                <div style={ {
                    padding: '20px', background: '#f9f9f9',
                    border: '1px dashed #c3c4c7', borderRadius: 4,
                    textAlign: 'center', marginBottom: 12,
                } }>
                    <Text style={ { color: '#646970', fontSize: 13 } }>
                        { __( 'No card rules configured. Click "Add Card Rule" to get started.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                </div>
            ) : (
                <div style={ { overflowX: 'auto', marginBottom: 12 } }>
                    <table style={ {
                        width: '100%', borderCollapse: 'collapse',
                        fontSize: 13, background: '#fff',
                        border: '1px solid #e0e0e0', borderRadius: 4,
                    } }>
                        <thead>
                            <tr>
                                <th style={ th }>{ __( 'Card Type', 'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ th }>{ __( 'Scheme',    'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ th }>{ __( 'Country',   'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ th }>{ __( 'Location',  'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ th }>{ __( 'Bank',      'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ th }>{ __( 'Fee',       'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ th }>{ __( 'Fee Type',  'checkout-fees-for-woocommerce' ) }</th>
                                <th style={ { ...th, textAlign: 'center' } }>
                                    { __( 'Action', 'checkout-fees-for-woocommerce' ) }
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            { stable.map( ( rule, idx ) => (
                                <RuleRow
                                    key={ rule._id }
                                    index={ idx }
                                    rule={ rule }
                                    onChange={ handleChange }
                                    onRemove={ handleRemove }
                                    countryOptions={ countryOpts }
                                    schemeOptions={ schemeOpts }
                                    bankOptions={ bankOpts }
                                />
                            ) ) }
                        </tbody>
                    </table>
                </div>
            ) }

            <div style={ { display: 'flex', alignItems: 'center', gap: 12, flexWrap: 'wrap' } }>
                <Button
                    variant="secondary"
                    type="button"
                    onClick={ handleAdd }
                    style={ { width: 'fit-content' } }
                >
                    { __( '+ Add Card Rule', 'checkout-fees-for-woocommerce' ) }
                </Button>
                { stable.length > 0 && (
                    <Text style={ { fontSize: 12, color: '#646970' } }>
                        { stable.length }{ ' ' }
                        { stable.length === 1
                            ? __( 'rule configured', 'checkout-fees-for-woocommerce' )
                            : __( 'rules configured', 'checkout-fees-for-woocommerce' ) }
                        { ' — ' }
                        { __( 'evaluated in order, first match wins.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                ) }
            </div>
        </div>
    );
}
