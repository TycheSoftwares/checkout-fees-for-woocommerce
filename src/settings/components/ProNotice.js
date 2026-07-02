// src/components/ProNotice.js
import { Notice, Button, ExternalLink } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const UPGRADE_URL = 'https://www.tychesoftwares.com/products/woocommerce-payment-gateway-based-fees-and-discounts-plugin/?utm_source=pgbflite&utm_medium=notice&utm_campaign=upgrade';

// Full‑width notice (used above cards)
export default function ProNotice({ feature }) {
    return (
        <div style={{ display: 'inline-block', maxWidth: '100%', marginBottom: '16px' }}>
            <Notice status="warning" isDismissible={false}>
                <div style={{ display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '12px' }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <span className="dashicons dashicons-info-outline" style={{ fontSize: '20px', color: '#dba617' }} />
                        <span>
                            {feature && <strong style={{ marginRight: '4px' }}>{feature}</strong>}
                            {__('is only available in the Pro version.', 'checkout-fees-for-woocommerce')}
                        </span>
                    </div>
                    <Button variant="primary" href={UPGRADE_URL} target="_blank" rel="noreferrer">
                        {__('Upgrade to Pro', 'checkout-fees-for-woocommerce')}
                        <span className="dashicons dashicons-external" style={{ fontSize: '16px', marginLeft: '6px', verticalAlign: 'middle' }} />
                    </Button>
                </div>
            </Notice>
        </div>
    );
}

// Inline notice: just a link, no extra text
export function ProInlineNotice({ className = '' }) {
    return (
        <span className={className} style={{ display: 'inline-flex', alignItems: 'center', marginLeft: '8px', flexShrink: 0, whiteSpace: 'nowrap' }}>
            <ExternalLink
                href={UPGRADE_URL}
                style={{ textDecoration: 'none', fontWeight: 600, whiteSpace: 'nowrap' }}
            >
                <span style={{ textDecoration: 'underline' }}>
                    {__('Upgrade to Pro', 'checkout-fees-for-woocommerce')}
                </span>
            </ExternalLink>
        </span>
    );
}