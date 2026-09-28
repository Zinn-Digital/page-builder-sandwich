/**
 * Entry `site-design`: the Site design sidebar in the block editor and in Sandwich Studio.
 *
 * - Block editor: a PluginSidebar (toolbar button + "Site design" in the options menu).
 * - Studio: a `pbs-studio`-scoped plugin that fills Studio's header tools and right-hand panel
 *   slots (`PbswStudioHeaderTools`, `PbswStudioSidebar`), so Studio shows the same panel.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { Button, Fill } from '@wordpress/components';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { closeSmall, styles } from '@wordpress/icons';
import SiteDesign from './SiteDesign';
import './panel.scss';

const NAME = 'pbsw-site-design';

function EditorSidebar() {
	return (
		<>
			<PluginSidebarMoreMenuItem target={ NAME } icon={ styles }>
				{ __( 'Site design', 'page-builder-sandwich' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={ NAME }
				icon={ styles }
				title={ __( 'Site design', 'page-builder-sandwich' ) }
			>
				<div className="pbsw-sd-sidebar">
					<SiteDesign hasPost />
				</div>
			</PluginSidebar>
		</>
	);
}

function StudioSidebar() {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<Fill name="PbswStudioHeaderTools">
				<Button
					icon={ styles }
					label={ __( 'Site design', 'page-builder-sandwich' ) }
					isPressed={ open }
					onClick={ () => setOpen( ( v ) => ! v ) }
					size="compact"
				/>
			</Fill>
			{ open && (
				<Fill name="PbswStudioSidebar">
					<div className="pbsw-sd-studio">
						<div className="pbsw-studio-panel__head">
							<h2>
								{ __( 'Site design', 'page-builder-sandwich' ) }
							</h2>
							<Button
								icon={ closeSmall }
								label={ __(
									'Close panel',
									'page-builder-sandwich'
								) }
								onClick={ () => setOpen( false ) }
								size="small"
							/>
						</div>
						<SiteDesign hasPost />
					</div>
				</Fill>
			) }
		</>
	);
}

if ( window.pbswStudio ) {
	registerPlugin( `${ NAME }-studio`, {
		scope: 'pbs-studio',
		render: StudioSidebar,
	} );
} else {
	registerPlugin( NAME, { render: EditorSidebar } );
}
