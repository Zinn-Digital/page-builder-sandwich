/**
 * AI inside the builder (P12): the AI menu on text blocks (pbs-ai1) and the AI sidebar (pbs-ai2,
 * pbs-ai8, and the Pro panels), in the block editor and in Sandwich Studio.
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';

import TextMenu, { textAttribute } from './TextMenu';
import Sidebar from './Sidebar';

const ICON = 'superhero';

addFilter(
	'editor.BlockEdit',
	'pbs/ai/text-menu',
	createHigherOrderComponent(
		( BlockEdit ) =>
			function WithAiTextMenu( props ) {
				return (
					<>
						<BlockEdit { ...props } />
						{ props.isSelected && textAttribute( props.name ) && (
							<TextMenu { ...props } />
						) }
					</>
				);
			},
		'withAiTextMenu'
	)
);

if ( window.pbswStudio ) {
	addFilter( 'pbs.studio.sidebars', 'pbs/ai/sidebar', ( list ) => [
		...list,
		{
			name: 'ai',
			title: __( 'AI', 'page-builder-sandwich' ),
			icon: ICON,
			Component: () => <Sidebar postId={ window.pbswStudio.postId } />,
		},
	] );
} else {
	registerPlugin( 'pbsw-ai', {
		icon: ICON,
		render: function AiSidebar() {
			const postId = useSelect(
				( select ) => select( 'core/editor' )?.getCurrentPostId(),
				[]
			);
			return (
				<>
					<PluginSidebarMoreMenuItem target="pbsw-ai">
						{ __( 'AI', 'page-builder-sandwich' ) }
					</PluginSidebarMoreMenuItem>
					<PluginSidebar
						name="pbsw-ai"
						title={ __( 'AI', 'page-builder-sandwich' ) }
					>
						<Sidebar postId={ postId } />
					</PluginSidebar>
				</>
			);
		},
	} );
}
