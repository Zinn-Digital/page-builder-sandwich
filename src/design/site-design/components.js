/**
 * Small shared controls for the Site design sections. Every section (free and premium) is
 * built from these and from @wordpress/components, so the screen looks like WordPress.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	ColorIndicator,
	ColorPicker,
	Dropdown,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { isColor } from './lib';

/**
 * A colour swatch that opens a picker, plus the value as text (for pasting a brand colour).
 *
 * @param {Object}   props
 * @param {string}   props.label      Accessible name.
 * @param {string}   props.value      Colour.
 * @param {Function} props.onChange   New colour.
 * @param {boolean}  props.allowEmpty Show a clear button.
 */
export function ColorField( { label, value, onChange, allowEmpty = false } ) {
	const valid = ! value || isColor( value );
	return (
		<div className="pbsw-sd-color">
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="pbsw-sd-color__toggle"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						label={ sprintf(
							/* translators: %s: colour name. */
							__(
								'Pick a colour for %s',
								'page-builder-sandwich'
							),
							label
						) }
						showTooltip
					>
						<ColorIndicator colorValue={ value || 'transparent' } />
					</Button>
				) }
				renderContent={ () => (
					<ColorPicker
						color={ isColor( value ) ? value : '#000000' }
						onChange={ onChange }
						enableAlpha
					/>
				) }
			/>
			<TextControl
				__next40pxDefaultSize
				hideLabelFromVision
				label={ label }
				value={ value || '' }
				onChange={ onChange }
				className={ valid ? undefined : 'is-invalid' }
				dir="ltr"
			/>
			{ allowEmpty && value && (
				<Button
					variant="tertiary"
					size="small"
					onClick={ () => onChange( '' ) }
				>
					{ __( 'Clear', 'page-builder-sandwich' ) }
				</Button>
			) }
		</div>
	);
}

/**
 * The save row at the end of a section: button, busy state, result.
 *
 * @param {Object}   props
 * @param {Function} props.onSave Save.
 * @param {boolean}  props.saving Busy.
 * @param {boolean}  props.dirty  Unsaved changes.
 * @param {string}   props.error  Problem to show before saving.
 * @param {string}   props.label  Button label.
 */
export function SaveRow( { onSave, saving, dirty, error, label } ) {
	return (
		<div className="pbsw-sd-save">
			{ error && (
				<p className="pbsw-sd-error" role="alert">
					{ error }
				</p>
			) }
			<Button
				variant="primary"
				onClick={ onSave }
				isBusy={ saving }
				disabled={ saving || ! dirty || !! error }
				__next40pxDefaultSize
			>
				{ label || __( 'Save', 'page-builder-sandwich' ) }
			</Button>
		</div>
	);
}

/**
 * A result notice (success or failure of the last request).
 *
 * @param {Object}   props
 * @param {Object}   props.notice   `{ status, message }` or null.
 * @param {Function} props.onRemove Dismiss.
 */
export function ResultNotice( { notice, onRemove } ) {
	if ( ! notice ) {
		return null;
	}
	return (
		<Notice
			status={ notice.status }
			onRemove={ onRemove }
			className="pbsw-sd-notice"
		>
			{ notice.message }
		</Notice>
	);
}

/**
 * Loading state.
 */
export function Loading() {
	return (
		<div className="pbsw-sd-loading">
			<Spinner />
		</div>
	);
}

/**
 * Run a request with a busy flag and a result notice.
 *
 * @param {Function} setSaving Busy setter.
 * @param {Function} setNotice Notice setter.
 * @param {Function} request   `() => Promise`.
 * @param {string}   success   Message on success.
 * @return {Promise<*>} The response, or null after a failure.
 */
export async function runRequest( setSaving, setNotice, request, success ) {
	setSaving( true );
	setNotice( null );
	try {
		const result = await request();
		setNotice( { status: 'success', message: success } );
		return result;
	} catch ( error ) {
		setNotice( {
			status: 'error',
			message:
				error?.message ||
				__( 'The change could not be saved.', 'page-builder-sandwich' ),
		} );
		return null;
	} finally {
		setSaving( false );
	}
}
