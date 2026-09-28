/**
 * pbs/star-rating saved markup: the rating as text ("4.5/5") — what a reader gets with the
 * plugin off. No words (a saved string cannot follow the site's language), no classes.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Attributes.
 * @return {Element} Saved markup.
 */
export default function save( { attributes } ) {
	const max = Math.max( 1, Math.min( 10, attributes.max || 5 ) );
	const rating =
		Math.round(
			Math.max( 0, Math.min( max, Number( attributes.rating ) || 0 ) ) * 2
		) / 2;
	return <p>{ `${ rating }/${ max }` }</p>;
}
