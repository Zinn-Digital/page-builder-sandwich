/**
 * Entry `site-design-page` (not "-admin": WP-CLI make-json mangles a bundle name ending "admin.js", so its catalogue would never load): the Site design page in the plugin's admin menu.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import SiteDesign from './SiteDesign';
import './panel.scss';

domReady( () => {
	const root = document.getElementById( 'pbsw-site-design-root' );
	if ( root ) {
		createRoot( root ).render(
			<div className="pbsw-sd-page">
				<SiteDesign />
			</div>
		);
	}
} );
