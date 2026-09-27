/**
 * "Edit in Sandwich Studio" inside the normal block editor (webpack entry `studio-button`,
 * handle pbsw-studio-button): a button in the post summary, an item in the ⋮ menu, and a
 * command. Unsaved changes are saved first, so switching editors never loses work.
 */
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginMoreMenuItem,
	PluginPostStatusInfo,
	store as editorStore,
} from '@wordpress/editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useCommand } from '@wordpress/commands';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { layout } from '@wordpress/icons';

function StudioButton() {
	const { postId, isDirty } = useSelect( ( select ) => {
		const s = select( editorStore );
		return {
			postId: s.getCurrentPostId(),
			isDirty: s.isEditedPostDirty(),
		};
	}, [] );
	const { savePost } = useDispatch( editorStore );
	const go = async () => {
		if ( isDirty ) {
			await savePost();
		}
		window.location.href = `${ window.pbswStudioButton.studioUrl }&post=${ postId }`;
	};
	const label = __( 'Edit in Sandwich Studio', 'page-builder-sandwich' );
	useCommand( {
		name: 'pbsw/open-studio',
		label,
		icon: layout,
		callback: ( { close } ) => {
			close();
			go();
		},
	} );
	return (
		<>
			<PluginPostStatusInfo className="pbsw-studio-button">
				<Button
					variant="secondary"
					onClick={ go }
					__next40pxDefaultSize
				>
					{ label }
				</Button>
			</PluginPostStatusInfo>
			<PluginMoreMenuItem icon={ layout } onClick={ go }>
				{ label }
			</PluginMoreMenuItem>
		</>
	);
}

if ( window.pbswStudioButton?.studioUrl ) {
	registerPlugin( 'pbsw-studio-button', { render: StudioButton } );
}
