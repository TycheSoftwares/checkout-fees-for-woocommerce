/**
 * src/settings/components/ProductMigrationNotice.js
 *
 * Admin notice that shows migration progress for the product-level
 * postmeta consolidation (old _alg_checkout_fees_* → _pgbf_pro_product_fees).
 *
 * Renders inside the settings SPA (Dashboard screen) and as a standalone
 * WP admin notice via the PHP admin_notices hook (see PGBF_Admin_Page).
 *
 * States:
 *   idle      — migration hasn't been started yet, show "Start Migration" button
 *   running   — progress bar + live polling every 3 s
 *   complete  — success message, dismiss button
 *   partial   — completed with errors, warning + dismiss
 *   none      — nothing to migrate, render nothing
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ }   from '@wordpress/i18n';
import {
    Button,
    __experimentalText as Text,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const POLL_INTERVAL_MS = 3000;

// ─── Progress bar ─────────────────────────────────────────────────────────────

function ProgressBar( { percent } ) {
    return (
        <div style={ {
            background  : '#e0e0e0',
            borderRadius: '4px',
            height      : '10px',
            width       : '100%',
            overflow    : 'hidden',
            margin      : '8px 0',
        } }>
            <div style={ {
                background  : '#2271b1',
                height      : '100%',
                width       : `${ percent }%`,
                borderRadius: '4px',
                transition  : 'width 0.5s ease',
            } } />
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function ProductMigrationNotice() {
    const [ status,  setStatus  ] = useState( null );  // null = not yet loaded
    const [ loading, setLoading ] = useState( false );
    const [ dismissed, setDismissed ] = useState( false );

    // ── Fetch current status ──────────────────────────────────────────────────

    const fetchStatus = useCallback( async () => {
        try {
            const res = await apiFetch( { path: '/pgbf-pro/v1/product-migration/status' } );
            setStatus( res?.data ?? null );
        } catch ( err ) {
            console.error( '[PGBF Pro] Migration status fetch failed:', err );
        }
    }, [] );

    // Initial load.
    useEffect( () => { fetchStatus(); }, [ fetchStatus ] );

    // Poll while running.
    useEffect( () => {
        if ( status?.status !== 'running' ) return;
        const timer = setInterval( fetchStatus, POLL_INTERVAL_MS );
        return () => clearInterval( timer );
    }, [ status?.status, fetchStatus ] );

    // ── Actions ───────────────────────────────────────────────────────────────

    const handleStart = async () => {
        setLoading( true );
        try {
            const res = await apiFetch( {
                path  : '/pgbf-pro/v1/product-migration/start',
                method: 'POST',
                data  : {},
            } );
            setStatus( res?.data ?? null );
        } catch ( err ) {
            console.error( '[PGBF Pro] Migration start failed:', err );
        } finally {
            setLoading( false );
        }
    };

    const handleDismiss = async () => {
        setDismissed( true );
        try {
            await apiFetch( {
                path  : '/pgbf-pro/v1/product-migration/dismiss',
                method: 'POST',
                data  : {},
            } );
        } catch { /* ignore */ }
    };

    // ── Render conditions ─────────────────────────────────────────────────────

    if ( dismissed )                  return null;
    if ( status === null )            return null;   // still loading
    if ( status.status === 'none' )   return null;   // nothing detected
    if ( status.total === 0 && status.status !== 'running' ) return null;

    const { status: migStatus, total, done, failed, pending, percent } = status;

    // ── Layout ────────────────────────────────────────────────────────────────

    const noticeBase = {
        padding     : '16px 20px',
        borderRadius: '6px',
        border      : '1px solid',
        marginBottom: '16px',
        position    : 'relative',
    };

    const colours = {
        running : { bg: '#f0f6fc', border: '#72aee6', accent: '#2271b1' },
        complete: { bg: '#f0fdf4', border: '#86efac', accent: '#16a34a' },
        partial : { bg: '#fffbeb', border: '#fcd34d', accent: '#d97706' },
        pending : { bg: '#f6f7f7', border: '#c3c4c7', accent: '#646970' },
    };

    const theme = colours[ migStatus ] ?? colours.pending;

    return (
        <div style={ { ...noticeBase, background: theme.bg, borderColor: theme.border } }>

            { /* Dismiss button (top-right) */ }
            { ( migStatus === 'complete' || migStatus === 'partial' ) && (
                <button
                    type="button"
                    onClick={ handleDismiss }
                    aria-label={ __( 'Dismiss', 'checkout-fees-for-woocommerce' ) }
                    style={ {
                        position  : 'absolute',
                        top       : '12px',
                        right     : '14px',
                        background: 'none',
                        border    : 'none',
                        cursor    : 'pointer',
                        fontSize  : '18px',
                        color     : '#646970',
                        lineHeight: 1,
                    } }
                >
                    ×
                </button>
            ) }

            { /* ── Running state ── */ }
            { migStatus === 'running' && (
                <div>
                    <Text style={ { fontWeight: 700, color: theme.accent, display: 'block', marginBottom: '6px' } }>
                        🔄 { __( 'Payment Gateway Fees — Product Data Migration In Progress', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Text style={ { fontSize: '13px', color: '#1d2327', display: 'block', marginBottom: '8px' } }>
                        { __( 'Migrating product fee settings to a more efficient storage format. This runs in the background and will not affect your store.', 'checkout-fees-for-woocommerce' ) }
                    </Text>

                    <ProgressBar percent={ percent } />

                    <div style={ { display: 'flex', gap: '24px', fontSize: '13px', color: '#646970', marginTop: '6px', flexWrap: 'wrap' } }>
                        <span>{ __( 'Total:', 'checkout-fees-for-woocommerce' ) } <strong style={ { color: '#1d2327' } }>{ total }</strong></span>
                        <span>{ __( 'Completed:', 'checkout-fees-for-woocommerce' ) } <strong style={ { color: '#16a34a' } }>{ done }</strong></span>
                        <span>{ __( 'Pending:', 'checkout-fees-for-woocommerce' ) } <strong style={ { color: '#2271b1' } }>{ pending }</strong></span>
                        { failed > 0 && (
                            <span>{ __( 'Failed:', 'checkout-fees-for-woocommerce' ) } <strong style={ { color: '#d63638' } }>{ failed }</strong></span>
                        ) }
                        <strong style={ { color: theme.accent } }>{ percent }%</strong>
                    </div>
                </div>
            ) }

            { /* ── Complete state ── */ }
            { migStatus === 'complete' && (
                <div>
                    <Text style={ { fontWeight: 700, color: theme.accent, display: 'block', marginBottom: '4px' } }>
                        ✅ { __( 'Product Data Migration Complete', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Text style={ { fontSize: '13px', color: '#1d2327' } }>
                        { total } { __( 'products migrated successfully. Product fee settings are now stored in the optimised format.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                </div>
            ) }

            { /* ── Partial (completed with errors) state ── */ }
            { migStatus === 'partial' && (
                <div>
                    <Text style={ { fontWeight: 700, color: theme.accent, display: 'block', marginBottom: '4px' } }>
                        ⚠️ { __( 'Product Data Migration Completed With Errors', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Text style={ { fontSize: '13px', color: '#1d2327', display: 'block', marginBottom: '6px' } }>
                        { done } { __( 'of', 'checkout-fees-for-woocommerce' ) } { total } { __( 'products migrated.', 'checkout-fees-for-woocommerce' ) }
                        { ' ' }{ failed } { __( 'product(s) could not be migrated — their original data is preserved and fees will continue to work as before.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Text style={ { fontSize: '13px', color: '#646970' } }>
                        { __( 'Check', 'checkout-fees-for-woocommerce' ) }{ ' ' }
                        <a href={ `${ window?.pgbfProData?.adminUrl ?? '/wp-admin/' }admin.php?page=wc-status&tab=logs` }
                            target="_blank" rel="noreferrer" style={ { color: theme.accent } }>
                            { __( 'WooCommerce → Status → Logs (channel: pgbf-migration)', 'checkout-fees-for-woocommerce' ) }
                        </a>
                        { ' ' }{ __( 'for details. If fees are not applying correctly, consider switching to the previous plugin version and contacting support.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                </div>
            ) }

            { /* ── Pending / idle state — show start button ── */ }
            { ( migStatus === 'pending' || migStatus === 'none' || ! migStatus ) && total > 0 && (
                <div>
                    <Text style={ { fontWeight: 700, color: '#1d2327', display: 'block', marginBottom: '4px' } }>
                        📦 { __( 'Product Fee Data Migration Available', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Text style={ { fontSize: '13px', color: '#646970', display: 'block', marginBottom: '12px' } }>
                        { total } { __( 'product(s) have fee settings stored in the old format. Migrating them to the new format improves performance by reducing database queries.', 'checkout-fees-for-woocommerce' ) }
                        { ' ' }{ __( 'The migration runs in the background and your store will not be affected. Original data is never deleted.', 'checkout-fees-for-woocommerce' ) }
                    </Text>
                    <Button
                        variant="primary"
                        onClick={ handleStart }
                        isBusy={ loading }
                        disabled={ loading }
                        style={ { width: 'fit-content' } }
                    >
                        { __( 'Start Background Migration', 'checkout-fees-for-woocommerce' ) }
                    </Button>
                </div>
            ) }

        </div>
    );
}
