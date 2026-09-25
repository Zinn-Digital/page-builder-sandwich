import { __, sprintf } from '@wordpress/i18n';
import { ExternalLink, PanelBody } from '@wordpress/components';

/**
 * About and credits.
 *
 * @param {Object} props
 * @param {string} props.version    Plugin version.
 * @param {string} props.productUrl The product site.
 * @param {string} props.companyUrl The company site.
 * @return {Element} The panel.
 */
export default function About( { version, productUrl, companyUrl } ) {
	return (
		<PanelBody title={ __( 'About', 'page-builder-sandwich' ) } initialOpen>
			<p className="pbs-admin__credit">
				{ __(
					'Built by Neil Lock, CEO of Zinn Digital® Ltd',
					'page-builder-sandwich'
				) }
			</p>
			<p>
				{ sprintf(
					/* translators: %s: plugin version number. */
					__( 'Version %s', 'page-builder-sandwich' ),
					version
				) }
			</p>
			<ul className="pbs-admin__links">
				<li>
					<ExternalLink href={ productUrl }>
						{ __(
							'Page Builder Sandwich website',
							'page-builder-sandwich'
						) }
					</ExternalLink>
				</li>
				<li>
					<ExternalLink href={ companyUrl }>
						{ __(
							'Zinn Digital® website',
							'page-builder-sandwich'
						) }
					</ExternalLink>
				</li>
			</ul>
		</PanelBody>
	);
}
