/**
 * The "Tooltip" text format (rich-text): select words in any paragraph, heading or list and give
 * them a tooltip. Saved as `<abbr title="…" data-tooltip="1">words</abbr>` — no class, no footprint,
 * and with the plugin off the browser still shows the title on hover. With the plugin on it is
 * rewritten into the accessible tooltip markup (Interactive\Tooltip::inline).
 *
 * ⛔ Not a bare `<span>`: WordPress registers its own format for bare span, and a second claim on
 * it breaks one of the two (measured: the editor logged "already registered to handle bare tag
 * name span" on every load).
 */
import { __ } from '@wordpress/i18n';
import { RichTextToolbarButton } from '@wordpress/block-editor';
import { Button, Popover, TextareaControl } from '@wordpress/components';
import {
	applyFormat,
	getActiveFormat,
	registerFormatType,
	removeFormat,
	useAnchor,
} from '@wordpress/rich-text';
import { useState } from '@wordpress/element';
import { info } from '@wordpress/icons';

export const NAME = 'pbs/tooltip';

const settings = {
	title: __( 'Tooltip', 'page-builder-sandwich' ),
	tagName: 'abbr',
	className: null,
	attributes: { tip: 'title', marker: 'data-tooltip' },
	edit: TooltipEdit,
};

/**
 * Toolbar button + popover.
 *
 * @param {Object}                  props            rich-text format edit props.
 * @param {boolean}                 props.isActive   Whether the selection has the format.
 * @param {Object}                  props.value      Rich-text value.
 * @param {(value: Object) => void} props.onChange   Change handler.
 * @param {Object}                  props.contentRef The editable element's ref.
 * @return {Element} UI.
 */
function TooltipEdit( { isActive, value, onChange, contentRef } ) {
	const [ open, setOpen ] = useState( false );
	const active = getActiveFormat( value, NAME );
	const [ tip, setTip ] = useState( '' );
	const anchor = useAnchor( {
		editableContentElement: contentRef.current,
		settings,
	} );
	const start = () => {
		setTip( active?.attributes?.tip || '' );
		setOpen( true );
	};
	return (
		<>
			<RichTextToolbarButton
				icon={ info }
				title={ __( 'Tooltip', 'page-builder-sandwich' ) }
				onClick={ start }
				isActive={ isActive }
			/>
			{ open && (
				<Popover
					anchor={ anchor }
					placement="bottom"
					onClose={ () => setOpen( false ) }
					focusOnMount="firstElement"
				>
					<form
						style={ { padding: '12px', minWidth: '260px' } }
						onSubmit={ ( event ) => {
							event.preventDefault();
							const text = tip.trim();
							onChange(
								text
									? applyFormat( value, {
											type: NAME,
											attributes: {
												tip: text,
												marker: '1',
											},
										} )
									: removeFormat( value, NAME )
							);
							setOpen( false );
						} }
					>
						<TextareaControl
							label={ __(
								'Tooltip text',
								'page-builder-sandwich'
							) }
							value={ tip }
							onChange={ setTip }
						/>
						<Button variant="primary" type="submit">
							{ __( 'Apply', 'page-builder-sandwich' ) }
						</Button>{ ' ' }
						{ isActive && (
							<Button
								variant="tertiary"
								onClick={ () => {
									onChange( removeFormat( value, NAME ) );
									setOpen( false );
								} }
							>
								{ __( 'Remove', 'page-builder-sandwich' ) }
							</Button>
						) }
					</form>
				</Popover>
			) }
		</>
	);
}

registerFormatType( NAME, settings );
