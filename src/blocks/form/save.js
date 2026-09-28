/**
 * pbs/form saved markup: the form plugin's own shortcode, as plain text in a paragraph. With this
 * plugin switched off WordPress still runs the shortcode, so the visitor still gets the working
 * form (unstyled) — the footprint-free fallback. The live, styled HTML comes from
 * Marketing::render_form().
 */
export const SHORTCODES = {
	cf7: ( id ) => `[contact-form-7 id="${ id }"]`,
	wpforms: ( id ) => `[wpforms id="${ id }" title="false"]`,
	gravityforms: ( id ) =>
		`[gravityform id="${ id }" title="false" description="false" ajax="true"]`,
	fluentforms: ( id ) => `[fluentform id="${ id }"]`,
};

export default function save( { attributes } ) {
	const make = SHORTCODES[ attributes.provider ];
	if ( ! make || ! /^[a-z0-9]{1,40}$/.test( attributes.formId || '' ) ) {
		return null;
	}
	return <p>{ make( attributes.formId ) }</p>;
}
