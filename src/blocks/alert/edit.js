/**
 * pbs/alert editor: the live look (the front end's own classes and stylesheet), the title and
 * the text edited in place, and the options in the sidebar.
 */
import { __ } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';

/** Icon per variant, the same drawing the PHP renders (Blocks\Content::ALERT_ICONS). */
const ICONS = {
	info: (
		<>
			<circle cx="12" cy="12" r="10" />
			<path d="M12 16v-5M12 8h.01" />
		</>
	),
	success: (
		<>
			<circle cx="12" cy="12" r="10" />
			<path d="m8 12.5 2.5 2.5L16 9.5" />
		</>
	),
	warning: (
		<>
			<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
			<path d="M12 9v4M12 17h.01" />
		</>
	),
	error: (
		<>
			<circle cx="12" cy="12" r="10" />
			<path d="m15 9-6 6M9 9l6 6" />
		</>
	),
};

export default function Edit( { attributes, setAttributes } ) {
	const { variant, title, content, showIcon, dismissible } = attributes;
	const blockProps = useBlockProps( {
		className: cls( 'alert', `alert--${ variant }` ),
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Alert', 'page-builder-sandwich' ) }>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Type', 'page-builder-sandwich' ) }
						value={ variant }
						options={ [
							{
								value: 'info',
								label: __(
									'Information',
									'page-builder-sandwich'
								),
							},
							{
								value: 'success',
								label: __( 'Success', 'page-builder-sandwich' ),
							},
							{
								value: 'warning',
								label: __( 'Warning', 'page-builder-sandwich' ),
							},
							{
								value: 'error',
								label: __( 'Error', 'page-builder-sandwich' ),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { variant: value } )
						}
						help={ __(
							'Warnings and errors are announced to screen reader users straight away; information and success notices are read when they finish what they are doing.',
							'page-builder-sandwich'
						) }
					/>
					<ToggleControl
						label={ __( 'Show icon', 'page-builder-sandwich' ) }
						checked={ showIcon }
						onChange={ ( on ) => setAttributes( { showIcon: on } ) }
					/>
					<ToggleControl
						label={ __(
							'Visitors can close it',
							'page-builder-sandwich'
						) }
						checked={ dismissible }
						onChange={ ( on ) =>
							setAttributes( { dismissible: on } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ showIcon && (
					<span className={ cls( 'alert__icon' ) } aria-hidden="true">
						<svg
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 24 24"
							width="24"
							height="24"
							fill="none"
							stroke="currentColor"
							strokeWidth="2"
							strokeLinecap="round"
							strokeLinejoin="round"
							focusable="false"
						>
							{ ICONS[ variant ] || ICONS.info }
						</svg>
					</span>
				) }
				<div className={ cls( 'alert__body' ) }>
					<RichText
						tagName="p"
						className={ cls( 'alert__title' ) }
						value={ title }
						onChange={ ( value ) =>
							setAttributes( { title: value } )
						}
						placeholder={ __(
							'Title (optional)',
							'page-builder-sandwich'
						) }
						allowedFormats={ [ 'core/italic', 'core/link' ] }
					/>
					<RichText
						tagName="p"
						className={ cls( 'alert__text' ) }
						value={ content }
						onChange={ ( value ) =>
							setAttributes( { content: value } )
						}
						placeholder={ __(
							'Write the message…',
							'page-builder-sandwich'
						) }
					/>
				</div>
			</div>
		</>
	);
}
