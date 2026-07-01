/**
 * src/settings/screens/Dashboard.js
 */

import { useMemo } from '@wordpress/element';
import { __ }      from '@wordpress/i18n';
import {
    __experimentalText    as Text,
    __experimentalHStack  as HStack,
    __experimentalVStack  as VStack,
    __experimentalHeading as Heading,
} from '@wordpress/components';
import { useNavigate } from 'react-router-dom';
import { useSettings }              from '../context/SettingsContext';
import ProductMigrationNotice from '../components/ProductMigrationNotice';

// ─── SVG icons (from the shared snippet) ─────────────────────────────────────

const IconPower = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M12 2v10" />
        <path d="M18.4 6.6a9 9 0 1 1-12.77.04" />
    </svg>
);

const IconFileText = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z" />
        <path d="M14 2v4a2 2 0 0 0 2 2h4" />
        <path d="M10 9H8" /><path d="M16 13H8" /><path d="M16 17H8" />
    </svg>
);

const IconDollarSign = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <line x1="12" x2="12" y1="2" y2="22" />
        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
    </svg>
);

const IconScan = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <path d="M3 7V5a2 2 0 0 1 2-2h2" />
        <path d="M17 3h2a2 2 0 0 1 2 2v2" />
        <path d="M21 17v2a2 2 0 0 1-2 2h-2" />
        <path d="M7 21H5a2 2 0 0 1-2-2v-2" />
    </svg>
);

const IconCreditCard = () => (
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
        fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect width="20" height="14" x="2" y="5" rx="2" />
        <line x1="2" x2="22" y1="10" y2="10" />
    </svg>
);

const IconSupport = () => (
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
            d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
);

// ─── Progress bar ─────────────────────────────────────────────────────────────

function ProgressBar( { percent } ) {
    return (
        <div style={ { background: '#e0e0e0', borderRadius: '4px', height: '8px', width: '100%', overflow: 'hidden' } }>
            <div className="pgbf-active-progress" style={ { height: '100%', width: `${ percent }%`, borderRadius: '4px', transition: 'width 0.4s ease' } } />
        </div>
    );
}

// ─── Checklist item ───────────────────────────────────────────────────────────

function CheckItem( { done, label } ) {
    return (
        <div style={ { display: 'flex', alignItems: 'center', gap: '10px' } }>
            <span className={ done ? 'pgbf-steps-done' : '' } style={ {
                width: '22px', height: '22px', borderRadius: '50%',
                border: done ? 'none' : '2px solid #c3c4c7',
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                flexShrink: 0, color: '#fff', fontSize: '12px', fontWeight: 700,
            } }>
                { done ? '✓' : '' }
            </span>
            <Text style={ { color: done ? '#646970' : '#1d2327', fontWeight: done ? 400 : 500, margin: 0 } }>
                { label }
            </Text>
        </div>
    );
}

// ─── Summary tile ─────────────────────────────────────────────────────────────

function SummaryTile( { iconBg, iconColor, icon: Icon, label, value, valueColor } ) {
    return (
        <div style={ {
            background: '#f9f9f9', border: '1px solid #efefef',
            borderRadius: '8px', padding: '16px 20px',
            display: 'flex', flexDirection: 'column', gap: '10px',
        } }>
            { /* Icon + label row — left aligned */ }
            <div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
                <div style={ {
                    padding: '6px', borderRadius: '6px',
                    background: iconBg, color: iconColor,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    flexShrink: 0,
                } }>
                    <Icon />
                </div>
                <Text style={ { fontSize: '13px', color: '#646970', margin: 0, textAlign: 'left' } }>
                    { label }
                </Text>
            </div>
            { /* Value — left aligned, large */ }
            <Text style={ {
                fontSize: '22px', fontWeight: 700,
                color: valueColor, lineHeight: 1.2,
                margin: 0, textAlign: 'left',
            } }>
                { value }
            </Text>
        </div>
    );
}

// ─── Shortcut card ────────────────────────────────────────────────────────────

function ShortcutCard( { iconBg, iconHoverBg, iconColor, icon: Icon, title, description, onClick, href } ) {
    const inner = (
        <div style={ { display: 'flex', flexDirection: 'column', gap: '8px' } }>
            <div style={ { display: 'flex', alignItems: 'center', gap: '10px' } }>
                <div className="pgbf-shortcut-icon" style={ {
                    padding: '8px', borderRadius: '6px',
                    background: iconBg, color: iconColor,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    flexShrink: 0, transition: 'background 0.15s',
                } }>
                    <Icon />
                </div>
                <Text style={ { fontWeight: 600, fontSize: '14px', color: '#1d2327', margin: 0 } }>
                    { title }
                </Text>
            </div>
            <Text style={ { color: '#646970', fontSize: '13px', margin: 0 } }>
                { description }
            </Text>
        </div>
    );

    const cardStyle = {
        background: '#fff', border: '1px solid #e0e0e0',
        borderRadius: '8px', padding: '20px', flex: 1,
        cursor: 'pointer', textDecoration: 'none', display: 'block',
    };

    if ( href ) {
        return <a href={ href } target="_blank" rel="noreferrer" style={ cardStyle }>{ inner }</a>;
    }
    return (
        <div style={ cardStyle } onClick={ onClick } role="button" tabIndex={ 0 }
            onKeyDown={ ( e ) => e.key === 'Enter' && onClick?.() }>
            { inner }
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function Dashboard() {
    const navigate = useNavigate();
    const { settings, gateways } = useSettings();

    const general   = settings?.general          || {};
    const globalFee = settings?.global_extra_fee  || {};
    const binApis   = settings?.bin_apis          || {};

    const steps = useMemo( () => [
        {
            label: __( 'Enable the plugin',             'checkout-fees-for-woocommerce' ),
            done : !! general.enabled,
        },
        {
            label: __( 'Set up a payment gateway fee','checkout-fees-for-woocommerce' ),
            done : Array.isArray( gateways ) && gateways.length > 0
                   && ( settings?._pgbf_gateway_configured === true ),
        },
        {
            label: __( 'Set up global extra fee',        'checkout-fees-for-woocommerce' ),
            done : !! globalFee.enabled,
        },
        {
            label: __( 'Configure BIN APIs',             'checkout-fees-for-woocommerce' ),
            done : !! binApis.enabled,
        },
    ], [ general, globalFee, binApis, gateways, settings ] );

    const completedCount     = steps.filter( ( s ) => s.done ).length;
    const percent            = Math.round( ( completedCount / steps.length ) * 100 );
    const allDone            = completedCount === steps.length;
    const activeGatewayCount = Array.isArray( gateways ) ? gateways.length : 0;

    const cardStyle = {
        background: '#fff', border: '1px solid #e0e0e0',
        borderRadius: '8px', padding: '24px',
    };

    return (
        <VStack spacing={ 4 } style={ { padding: '8px 0' } }>

            { /* ── Product meta migration notice ── */ }
            <ProductMigrationNotice />

            { /* ── Page heading ── */ }
            <VStack spacing={ 1 }>
                <Heading level={ 3 } style={ { margin: 0 } }>
                    { __( 'Dashboard', 'checkout-fees-for-woocommerce' ) }
                </Heading>
                <Text style={ { color: '#646970' } }>
                    { __( 'Get started with Payment Gateway Based Fees and Discounts', 'checkout-fees-for-woocommerce' ) }
                </Text>
            </VStack>

            { /* ── Top row ── */ }
            <div style={ { display: 'flex', gap: '16px', flexWrap: 'wrap', alignItems: 'flex-start' } }>

                { /* Getting Started card — hidden when all done */ }
                { ! allDone && (
                    <div style={ { ...cardStyle, flex: '1 1 320px', minWidth: '280px' } }>

                        <Text style={ { fontWeight: 700, fontSize: '16px', color: '#1d2327', display: 'block', marginBottom: '4px' } }>
                            { __( 'Getting Started', 'checkout-fees-for-woocommerce' ) }
                        </Text>

                        { /* Progress row */ }
                        <div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '10px' } }>
                            <Text style={ { color: '#646970', fontSize: '13px', margin: 0 } }>
                                { completedCount } { __( 'of', 'checkout-fees-for-woocommerce' ) } { steps.length } { __( 'completed', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                            <Text style={ { fontWeight: 700, fontSize: '13px', margin: 0 } }>
                                { percent }%
                            </Text>
                        </div>

                        <div style={ { marginBottom: '20px' } }>
                            <ProgressBar percent={ percent } />
                        </div>

                        { /* Checklist — left aligned */ }
                        <div style={ { display: 'flex', flexDirection: 'column', gap: '12px' } }>
                            { steps.map( ( step, i ) => (
                                <CheckItem key={ i } done={ step.done } label={ step.label } />
                            ) ) }
                        </div>
                    </div>
                ) }

                { /* Plugin Summary card */ }
                <div style={ {
                    ...cardStyle,
                    flex    : allDone ? '1 1 100%' : '1 1 320px',
                    minWidth: '280px',
                } }>
                    <Text style={ { fontWeight: 700, fontSize: '16px', color: '#1d2327', display: 'block', marginBottom: '16px' } }>
                        { __( 'Plugin Summary', 'checkout-fees-for-woocommerce' ) }
                    </Text>

                    <div style={ {
                        display: 'grid',
                        gridTemplateColumns: 'repeat(2, 1fr)',
                        gap: '12px',
                    } }>
                        <SummaryTile
                            icon={ IconPower }
                            iconBg="#dcfce7" iconColor="#16a34a"
                            label={ __( 'Plugin Status', 'checkout-fees-for-woocommerce' ) }
                            value={ general.enabled
                                ? __( 'Enabled', 'checkout-fees-for-woocommerce' )
                                : __( 'Disabled', 'checkout-fees-for-woocommerce' ) }
                            valueColor={ general.enabled ? '#16a34a' : '#dc2626' }
                        />
                        <SummaryTile
                            icon={ IconFileText }
                            iconBg="#dbeafe" iconColor="#2271b1"
                            label={ __( 'Active Gateways', 'checkout-fees-for-woocommerce' ) }
                            value={ String( activeGatewayCount ) }
                            valueColor="#1d2327"
                        />
                        <SummaryTile
                            icon={ IconDollarSign }
                            iconBg="#f3e8ff" iconColor="#9333ea"
                            label={ __( 'Global Fee', 'checkout-fees-for-woocommerce' ) }
                            value={ globalFee.enabled
                                ? __( 'Active', 'checkout-fees-for-woocommerce' )
                                : __( 'Inactive', 'checkout-fees-for-woocommerce' ) }
                            valueColor={ globalFee.enabled ? '#16a34a' : '#646970' }
                        />
                        <SummaryTile
                            icon={ IconScan }
                            iconBg="#ffedd5" iconColor="#ea580c"
                            label={ __( 'Card-Based Fee Detection', 'checkout-fees-for-woocommerce' ) }
                            value={ binApis.enabled
                                ? __( 'Enabled', 'checkout-fees-for-woocommerce' )
                                : __( 'Disabled', 'checkout-fees-for-woocommerce' ) }
                            valueColor={ binApis.enabled ? '#16a34a' : '#646970' }
                        />
                    </div>

                    { allDone && (
                        <div className="pgbf-configure-success-border" style={ {
                            marginTop: '16px', background: '#f0f6fc',
                            borderRadius: '6px', padding: '12px 16px',
                        } }>
                            <Text className="pgbf-configure-success-msg" style={ { fontWeight: 600, margin: 0 } }>
                                🎉 { __( 'All steps complete! Your plugin is fully configured.', 'checkout-fees-for-woocommerce' ) }
                            </Text>
                        </div>
                    ) }
                </div>
            </div>

            { /* ── Quick Tip ── */ }
            <div style={ {
                background: '#f6f7f7', border: '1px solid #e0e0e0',
                borderRadius: '8px', padding: '16px 20px',
            } }>
                <div style={ { display: 'flex', gap: '12px', alignItems: 'flex-start' } }>
                    <div className="pgbf-dashboard-quick-tip" style={ {
                        borderRadius: '50%',
                        width: '28px', height: '28px', flexShrink: 0,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        color: '#fff', fontSize: '14px',
                    } }>💡</div>
                    <div>
                        <Text style={ { fontWeight: 700, color: '#2271b1', display: 'block', marginBottom: '4px' } }>
                            { __( 'Quick Tip', 'checkout-fees-for-woocommerce' ) }
                        </Text>
                        <Text style={ { color: '#1d2327', fontSize: '13px', lineHeight: 1.6, margin: 0 } }>
                            { __( 'Visit the ', 'checkout-fees-for-woocommerce' ) }
                            <button type="button" onClick={ () => navigate( '/general' ) }
                                style={ { background: 'none', border: 'none', padding: 0, color: '#2271b1', cursor: 'pointer', textDecoration: 'underline', fontSize: 'inherit' } }>
                                { __( 'General tab', 'checkout-fees-for-woocommerce' ) }
                            </button>
                            { __( ' to configure global settings. Configure fees per gateway under ', 'checkout-fees-for-woocommerce' ) }
                            <button type="button" onClick={ () => navigate( '/gateways' ) }
                                style={ { background: 'none', border: 'none', padding: 0, color: '#2271b1', cursor: 'pointer', textDecoration: 'underline', fontSize: 'inherit' } }>
                                { __( 'Payment Gateways', 'checkout-fees-for-woocommerce' ) }
                            </button>
                            { __( ' or a fee for all gateways under ', 'checkout-fees-for-woocommerce' ) }
                            <button type="button" onClick={ () => navigate( '/global-extra-fee' ) }
                                style={ { background: 'none', border: 'none', padding: 0, color: '#2271b1', cursor: 'pointer', textDecoration: 'underline', fontSize: 'inherit' } }>
                                { __( 'Global Extra Fee.', 'checkout-fees-for-woocommerce' ) }
                            </button>
                            { __( ' Enable ', 'checkout-fees-for-woocommerce' ) }
                            <button type="button" onClick={ () => navigate( '/bin-apis' ) }
                                style={ { background: 'none', border: 'none', padding: 0, color: '#2271b1', cursor: 'pointer', textDecoration: 'underline', fontSize: 'inherit' } }>
                                { __( 'BIN APIs', 'checkout-fees-for-woocommerce' ) }
                            </button>
                            { __( ' to apply fees based on card issuing country and bank.', 'checkout-fees-for-woocommerce' ) }
                        </Text>
                    </div>
                </div>
            </div>

            { /* ── Shortcut cards ── */ }
            <div style={ { display: 'flex', gap: '16px', flexWrap: 'wrap' } }>
                <ShortcutCard
                    icon={ IconFileText }
                    iconBg="#dbeafe" iconHoverBg="#bfdbfe" iconColor="#2271b1"
                    title={ __( 'Documentation', 'checkout-fees-for-woocommerce' ) }
                    description={ __( 'Learn how to configure fees and discounts for your store', 'checkout-fees-for-woocommerce' ) }
                    href="https://woocommerce.com/document/payment-gateway-fees-discounts/"
                />
                <ShortcutCard
                    icon={ IconCreditCard }
                    iconBg="#f3e8ff" iconHoverBg="#e9d5ff" iconColor="#9333ea"
                    title={ __( 'Payment Gateways', 'checkout-fees-for-woocommerce' ) }
                    description={ __(
                        'Configure fees for ' +
                        ( activeGatewayCount > 0
                            ? gateways.slice( 0, 3 ).map( ( g ) => g.title ).join( ', ' ) +
                              ( activeGatewayCount > 3 ? ', and more' : '' )
                            : 'Direct bank transfer, Check payments, and more' ),
                        'checkout-fees-for-woocommerce'
                    ) }
                    onClick={ () => navigate( '/gateways' ) }
                />
                <ShortcutCard
                    icon={ IconSupport }
                    iconBg="#dcfce7" iconHoverBg="#bbf7d0" iconColor="#16a34a"
                    title={ __( 'Get Support', 'checkout-fees-for-woocommerce' ) }
                    description={ __( 'Need help? Contact our support team for assistance', 'checkout-fees-for-woocommerce' ) }
                    href="https://www.tychesoftwares.com/contact-us/"
                />
            </div>

        </VStack>
    );
}
