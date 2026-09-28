/**
 * pbs/progress-bar saved markup: its label and value as text — what a reader gets with the
 * plugin off. No classes.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Attributes.
 * @return {Element} Saved markup.
 */
export default function save( { attributes } ) {
	const max = Math.max( 1, attributes.max || 100 );
	const value = Math.max( 0, Math.min( max, attributes.value || 0 ) );
	const label = ( attributes.label || '' ).replace( /<[^>]*>/g, '' );
	return <p>{ `${ label ? label + ': ' : '' }${ value }/${ max }` }</p>;
}
