/**
 * src/edit.js
 *
 * Block editor Edit component for the Payment Gateway Fees block.
 * Renders nothing visible — the block exists only to load frontend.js
 * on the checkout page. Locked so editors cannot remove or move it.
 */
import { useBlockProps } from '@wordpress/block-editor';

export default function Edit() {
	const blockProps = useBlockProps( { style: { display: 'none' } } );
	return <div { ...blockProps } />;
}
