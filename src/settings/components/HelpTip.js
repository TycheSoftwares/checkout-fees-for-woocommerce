/**
 * src/components/HelpTip.js
 * Alternative version using WordPress Dashicons
 */

import { Tooltip } from '@wordpress/components';

const HelpTip = ({ message, text, position = 'bottom', className = '' }) => {
    const tip = message || text;
    if (!tip) {
        return null;
    }

    return (
        <Tooltip text={tip} position={position}>
            <span 
                className={`dashicons dashicons-editor-help cos-help-tip ${className}`}
                style={{ 
                    cursor: 'help',
                    color: '#787c82',
                    fontSize: '1.2em',
                    width: '16px',
                    height: '16px',
                    marginRight: '8px'
                }}
            />
        </Tooltip>
    );
};

export default HelpTip;