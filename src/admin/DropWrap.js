/**
 * "Wrap a block dropped at the top level" on the settings screen: nothing (the default), a Row
 * with one Column, or a Container. Saved through the settings route the moment it changes; the
 * block editor reads it on its next load (src/design/editor/drop-wrap.js).
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
} from '@wordpress/components';

/**
 * @param {Object}   props
 * @param {string}   props.path    The settings REST path.
 * @param {string}   props.value   The saved choice: none, row or container.
 * @param {Function} props.onSaved Receives the settings the route returns.
 * @return {Element} Panel.
 */
export default function DropWrap( { path, value, onSaved } ) {
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const change = ( dropWrap ) => {
		setBusy( true );
		setNotice( null );
		apiFetch( { path, method: 'POST', data: { drop_wrap: dropWrap } } )
			.then( ( result ) => {
				onSaved( result );
				setNotice( {
					status: 'success',
					message: __( 'Settings saved.', 'page-builder-sandwich' ),
				} );
			} )
			.catch( ( e ) =>
				setNotice( { status: 'error', message: e.message } )
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<PanelBody
			title={ __( 'Editing', 'page-builder-sandwich' ) }
			initialOpen
		>
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ __(
					'Wrap a block dropped at the top level of a page in',
					'page-builder-sandwich'
				) }
				help={ __(
					'A block you add straight onto the page, outside any layout, is put inside a Row (with one Column) or a Container, ready to style and lay out. Text you type and layout blocks are never wrapped, and one Undo removes the block.',
					'page-builder-sandwich'
				) }
				value={ value || 'none' }
				disabled={ busy }
				options={ [
					{
						value: 'none',
						label: __( 'Nothing', 'page-builder-sandwich' ),
					},
					{
						value: 'row',
						label: __( 'A Row', 'page-builder-sandwich' ),
					},
					{
						value: 'container',
						label: __( 'A Container', 'page-builder-sandwich' ),
					},
				] }
				onChange={ change }
			/>
			{ busy && <Spinner /> }
		</PanelBody>
	);
}
