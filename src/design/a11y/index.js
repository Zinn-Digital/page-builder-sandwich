/**
 * Entry `a11y-checker` (pbs-a1, free): the Accessibility checker as a block editor sidebar, and
 * as a Studio panel (Studio's `PbswStudioHeaderTools` / `PbswStudioSidebar` slots).
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { Button, Fill } from '@wordpress/components';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { closeSmall, people } from '@wordpress/icons';
import Checker from './Checker';
import './a11y.scss';

const NAME = 'pbsw-a11y';

function EditorSidebar() {
	return (
		<>
			<PluginSidebarMoreMenuItem target={ NAME } icon={ people }>
				{ __( 'Accessibility checker', 'page-builder-sandwich' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={ NAME }
				icon={ people }
				title={ __( 'Accessibility checker', 'page-builder-sandwich' ) }
			>
				<div className="pbsw-a11y-sidebar">
					<Checker />
				</div>
			</PluginSidebar>
		</>
	);
}

function StudioPanel() {
	const [ open, setOpen ] = useState( false );
	return (
		<>
			<Fill name="PbswStudioHeaderTools">
				<Button
					icon={ people }
					label={ __(
						'Accessibility checker',
						'page-builder-sandwich'
					) }
					isPressed={ open }
					onClick={ () => setOpen( ( v ) => ! v ) }
					size="compact"
				/>
			</Fill>
			{ open && (
				<Fill name="PbswStudioSidebar">
					<div className="pbsw-a11y-studio">
						<div className="pbsw-studio-panel__head">
							<h2>
								{ __(
									'Accessibility checker',
									'page-builder-sandwich'
								) }
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
						<Checker />
					</div>
				</Fill>
			) }
		</>
	);
}

if ( window.pbswStudio ) {
	registerPlugin( `${ NAME }-studio`, {
		scope: 'pbs-studio',
		render: StudioPanel,
	} );
} else {
	registerPlugin( NAME, { render: EditorSidebar } );
}
