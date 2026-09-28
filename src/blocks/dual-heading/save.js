/**
 * pbs/dual-heading saved markup: the footprint-free fallback (one heading, the second part
 * emphasised). The live HTML is rebuilt by Blocks\Content::render_dual_heading().
 */
import { RichText } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { first, second, level } = attributes;
	if ( ! first && ! second ) {
		return null;
	}
	const Tag = `h${ Math.max( 1, Math.min( 6, level || 2 ) ) }`;
	return (
		<Tag>
			{ first && <RichText.Content value={ first } /> }
			{ first && second && ' ' }
			{ second && (
				<strong>
					<RichText.Content value={ second } />
				</strong>
			) }
		</Tag>
	);
}
