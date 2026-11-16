import { _n, __, sprintf } from '@wordpress/i18n';
import { ExternalLink, PanelBody } from '@wordpress/components';

/**
 * The graceful-lapse notice for the Pro "Your own tabs" feature (§2.62).
 *
 * When Pro is not active the site's own tabs are kept in the database but
 * hidden. This panel says how many are kept, that nothing was deleted, and
 * where to renew. It renders nothing when no tab is paused.
 *
 * @param {Object} props
 * @param {number} props.count      How many own tabs are kept but hidden.
 * @param {string} props.upgradeUrl The Pro plans page, if known.
 * @return {Element|null} The panel, or null.
 */
export default function PausedTabs( { count, upgradeUrl } ) {
	if ( ! ( count > 0 ) ) {
		return null;
	}
	return (
		<PanelBody
			title={ __( 'Your own tabs', 'page-builder-sandwich' ) }
			initialOpen
		>
			<p>
				{ sprintf(
					/* translators: %d: how many tabs the site added. */
					_n(
						'Your %d own tab is kept, but hidden while Page Builder Sandwich Pro is not active. Renew Pro to show it again; nothing was deleted.',
						'Your %d own tabs are kept, but hidden while Page Builder Sandwich Pro is not active. Renew Pro to show them again; nothing was deleted.',
						count,
						'page-builder-sandwich'
					),
					count
				) }
			</p>
			{ upgradeUrl && (
				<p>
					<ExternalLink href={ upgradeUrl }>
						{ __(
							'See the Pro plans and pricing',
							'page-builder-sandwich'
						) }
					</ExternalLink>
				</p>
			) }
		</PanelBody>
	);
}
