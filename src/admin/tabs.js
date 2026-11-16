import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';

/**
 * Tabs other code adds from PHP through the `pbsw_admin_tabs` filter (Admin_Tabs). A content
 * tab's markup is printed by PHP into a hidden holder below the app; the tab moves that node
 * into view while it is open and back when it closes, so its forms, nonces and scripts are the
 * ones WordPress printed and keep their state between visits.
 */

/** The id of each tab's content holder starts with this (Admin_Tabs::PANEL_PREFIX). */
export const PANEL_PREFIX = 'pbs-admin-tab-';

/**
 * A content tab: shows the node PHP printed for it.
 *
 * @param {Object} props
 * @param {string} props.id Tab id.
 * @return {Element} The tab.
 */
export function ContentTab( { id } ) {
	const ref = useRef( null );
	// PHP printed every panel before the app mounted, so its absence is known at once.
	const [ missing ] = useState(
		() => ! document.getElementById( PANEL_PREFIX + id )
	);

	useEffect( () => {
		const node = document.getElementById( PANEL_PREFIX + id );
		const container = ref.current;
		if ( ! node || ! container ) {
			return undefined;
		}
		const home = node.parentNode;
		container.appendChild( node );
		return () => {
			// Back to the hidden holder, so the next visit finds it (and its state) again.
			if ( home ) {
				home.appendChild( node );
			}
		};
	}, [ id ] );

	return (
		<div className="pbs-admin-tab" ref={ ref }>
			{ missing && (
				<Notice status="error" isDismissible={ false }>
					{ __(
						'This tab’s content is missing from the page. Reload the page to try again.',
						'page-builder-sandwich'
					) }
				</Notice>
			) }
		</div>
	);
}

/**
 * A link tab: a screen that lives at another address.
 *
 * @param {Object} props
 * @param {string} props.label Tab label.
 * @param {string} props.url   Address.
 * @return {Element} The tab.
 */
export function LinkTab( { label, url } ) {
	return (
		<div className="pbs-admin-tab pbs-admin-tab--link">
			<p>
				{ sprintf(
					/* translators: %s: the tab's label. */
					__(
						'%s opens in its own screen.',
						'page-builder-sandwich'
					),
					label
				) }
			</p>
			<Button variant="primary" href={ url }>
				{ sprintf(
					/* translators: %s: the tab's label. */
					__( 'Open %s', 'page-builder-sandwich' ),
					label
				) }
			</Button>
		</div>
	);
}

/**
 * Add the PHP tabs to the screen's routes. A tab whose id a route already uses is left out,
 * so a tab can never hide a screen.
 *
 * @param {Array<Object>} routes Routes so far: {id, label, render}.
 * @param {Array<Object>} tabs   Boot data `tabs`: {id, label, url}.
 * @return {Array<Object>} Routes.
 */
export function withAddedTabs( routes, tabs ) {
	const taken = new Set( routes.map( ( route ) => route.id ) );
	const added = [];
	( Array.isArray( tabs ) ? tabs : [] ).forEach( ( tab ) => {
		if ( ! tab || ! tab.id || ! tab.label || taken.has( tab.id ) ) {
			return;
		}
		taken.add( tab.id );
		added.push( {
			id: tab.id,
			label: tab.label,
			render: () =>
				tab.url ? (
					<LinkTab label={ tab.label } url={ tab.url } />
				) : (
					<ContentTab id={ tab.id } />
				),
		} );
	} );
	return [ ...routes, ...added ];
}
