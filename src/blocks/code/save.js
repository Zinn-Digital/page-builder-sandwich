/**
 * pbs/code saved markup: with the plugin off, the code in a plain <pre><code> (no classes, no
 * data-wp-*). The live figure is rebuilt by Data::render_code().
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Attributes.
 * @return {Element|null} Saved markup.
 */
export default function save( { attributes } ) {
	if ( ! ( attributes.code || '' ).trim() ) {
		return null;
	}
	return (
		<pre>
			<code>{ attributes.code }</code>
		</pre>
	);
}
