/**
 * pbs/user-profile editor: the server's own render, the person and what to show in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	Placeholder,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();
	const users = useSelect(
		( select ) =>
			select( coreStore ).getUsers( { who: 'authors', per_page: 100 } ) ||
			[],
		[]
	);
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Profile', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Whose profile', 'page-builder-sandwich' ) }
						value={
							attributes.source === 'user'
								? String( attributes.userId )
								: 'author'
						}
						options={ [
							{
								value: 'author',
								label: __(
									'The post’s author',
									'page-builder-sandwich'
								),
							},
							...users.map( ( u ) => ( {
								value: String( u.id ),
								label: u.name,
							} ) ),
						] }
						onChange={ ( v ) =>
							setAttributes(
								v === 'author'
									? { source: 'author', userId: 0 }
									: { source: 'user', userId: Number( v ) }
							)
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the profile picture',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.showAvatar }
						onChange={ ( showAvatar ) =>
							setAttributes( { showAvatar } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show the biography',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.showBio }
						onChange={ ( showBio ) => setAttributes( { showBio } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show links to posts and website',
							'page-builder-sandwich'
						) }
						checked={ !! attributes.showLinks }
						onChange={ ( showLinks ) =>
							setAttributes( { showLinks } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="pbs/user-profile"
				attributes={ attributes }
				urlQueryArgs={
					context?.postId ? { post_id: context.postId } : undefined
				}
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						label={ __(
							'Author profile',
							'page-builder-sandwich'
						) }
						instructions={ __(
							'Choose whose profile to show.',
							'page-builder-sandwich'
						) }
					/>
				) }
			/>
		</div>
	);
}
