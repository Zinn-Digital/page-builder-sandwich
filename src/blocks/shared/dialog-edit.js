/**
 * The editor of pbs/modal and pbs/off-canvas: the trigger button as visitors see it, and below it
 * the window (or panel) itself, open, so its title and blocks can be edited in place.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import { cls } from './cls';

/**
 * Build an edit component for one kind.
 *
 * @param {string} kind `modal` or `off-canvas`.
 * @return {(props: Object) => Element} Edit component.
 */
export function dialogEdit( kind ) {
	return function Edit( { attributes, setAttributes } ) {
		const { trigger, title, backdropClose } = attributes;
		const variant = 'modal' === kind ? attributes.size : attributes.side;
		const blockProps = useBlockProps( { className: cls( kind ) } );
		const innerBlocksProps = useInnerBlocksProps(
			{ className: cls( `${ kind }__body` ) },
			{ template: [ [ 'core/paragraph' ] ] }
		);
		return (
			<>
				<InspectorControls>
					<PanelBody
						title={
							'modal' === kind
								? __( 'Modal', 'page-builder-sandwich' )
								: __(
										'Off-canvas panel',
										'page-builder-sandwich'
									)
						}
					>
						{ 'modal' === kind ? (
							<SelectControl
								__next40pxDefaultSize
								label={ __( 'Size', 'page-builder-sandwich' ) }
								value={ variant }
								options={ [
									{
										value: 'small',
										label: __(
											'Small',
											'page-builder-sandwich'
										),
									},
									{
										value: 'medium',
										label: __(
											'Medium',
											'page-builder-sandwich'
										),
									},
									{
										value: 'large',
										label: __(
											'Large',
											'page-builder-sandwich'
										),
									},
								] }
								onChange={ ( size ) =>
									setAttributes( { size } )
								}
							/>
						) : (
							<SelectControl
								__next40pxDefaultSize
								label={ __(
									'Slides in from',
									'page-builder-sandwich'
								) }
								help={ __(
									'Start is the side where reading begins: the left in English, the right in Arabic.',
									'page-builder-sandwich'
								) }
								value={ variant }
								options={ [
									{
										value: 'start',
										label: __(
											'Start',
											'page-builder-sandwich'
										),
									},
									{
										value: 'end',
										label: __(
											'End',
											'page-builder-sandwich'
										),
									},
								] }
								onChange={ ( side ) =>
									setAttributes( { side } )
								}
							/>
						) }
						<ToggleControl
							label={ __(
								'Close when the page behind is clicked',
								'page-builder-sandwich'
							) }
							checked={ backdropClose }
							onChange={ ( value ) =>
								setAttributes( { backdropClose: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<RichText
						tagName="span"
						className={ cls( `${ kind }__trigger` ) }
						value={ trigger }
						onChange={ ( value ) =>
							setAttributes( { trigger: value } )
						}
						placeholder={ __(
							'Button text',
							'page-builder-sandwich'
						) }
						allowedFormats={ [ 'core/italic', 'core/bold' ] }
					/>
					<div
						className={ cls(
							`${ kind }__preview`,
							`${ kind }__dialog`,
							`${ kind }__dialog--${ variant }`
						) }
					>
						<div className={ cls( `${ kind }__inner` ) }>
							<div className={ cls( `${ kind }__header` ) }>
								<RichText
									tagName="h2"
									className={ cls( `${ kind }__title` ) }
									value={ title }
									onChange={ ( value ) =>
										setAttributes( { title: value } )
									}
									placeholder={ __(
										'Title of the window',
										'page-builder-sandwich'
									) }
									allowedFormats={ [
										'core/italic',
										'core/bold',
									] }
								/>
							</div>
							<div { ...innerBlocksProps } />
						</div>
					</div>
				</div>
			</>
		);
	};
}
