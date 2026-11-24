/**
 * The Site design panel: a list of sections, and one section at a time with a way back. The same
 * component is the block editor sidebar, the Studio sidebar and the admin page.
 */
import { __, isRTL } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { Button, ExternalLink } from '@wordpress/components';
import { chevronLeft, chevronRight } from '@wordpress/icons';
import { boot } from './api';
import { getSections } from './sections';

/**
 * @param {Object}  props
 * @param {boolean} props.hasPost A post is being edited (shows per-page sections).
 * @param {string}  props.initial Section to open first.
 */
export default function SiteDesign( { hasPost = false, initial = '' } ) {
	const data = boot();
	const sections = getSections( { hasPost } );
	const [ active, setView ] = useState( initial );
	const navigated = useRef( false );
	const setActive = ( name ) => {
		navigated.current = true;
		setView( name );
	};
	const headingRef = useRef( null );
	const listRef = useRef( null );
	const current = sections.find( ( s ) => s.name === active && ! s.upsell );

	// Move focus with the view, so a keyboard user lands on the section they opened (and back
	// on the list when they leave it).
	useEffect( () => {
		if ( navigated.current ) {
			( current ? headingRef : listRef ).current?.focus();
		}
	}, [ current ] );

	if ( current ) {
		const { Component } = current;
		return (
			<div className="pbsw-sd">
				<div className="pbsw-sd__head">
					<Button
						icon={ isRTL() ? chevronRight : chevronLeft }
						label={ __(
							'Back to Site design',
							'page-builder-sandwich'
						) }
						onClick={ () => setActive( '' ) }
						size="compact"
					/>
					<h2
						className="pbsw-sd__title"
						tabIndex={ -1 }
						ref={ headingRef }
					>
						{ current.title }
					</h2>
				</div>
				{ current.description && (
					<p className="pbsw-sd-help">{ current.description }</p>
				) }
				<Component />
			</div>
		);
	}

	return (
		<div className="pbsw-sd">
			<ul className="pbsw-sd__list" tabIndex={ -1 } ref={ listRef }>
				{ sections.map( ( s ) => (
					<li key={ s.name }>
						<Button
							className="pbsw-sd__item"
							onClick={ () => setActive( s.name ) }
							icon={ isRTL() ? chevronLeft : chevronRight }
							iconPosition="right"
						>
							<span className="pbsw-sd__item-title">
								{ s.title }
							</span>
							{ s.description && (
								<span className="pbsw-sd__item-desc">
									{ s.description }
								</span>
							) }
						</Button>
					</li>
				) ) }
			</ul>
			{ data.siteEditUrl && (
				<p className="pbsw-sd-help">
					<ExternalLink href={ data.siteEditUrl }>
						{ __(
							'Open the Site Editor’s styles',
							'page-builder-sandwich'
						) }
					</ExternalLink>
				</p>
			) }
		</div>
	);
}
