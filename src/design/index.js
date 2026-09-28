/**
 * The design system in the editor (webpack entry `design`, handle pbsw-design-editor, pbs-p4).
 * Loaded in the block editor and in Sandwich Studio, in the head:
 *
 * - every block type gets the `pbs` attribute;
 * - the block inspector gets the Style tab;
 * - the canvas previews each styled block for the breakpoint being edited;
 * - element ids stay unique after a duplicate or paste;
 * - the breakpoint follows the editor's device preview and back.
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { getBlockType } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

import './attribute';
import './variations';
import { STORE, config } from './store';
import DesignPanel, { StylePanel } from './editor/panel';
import DesignPreview from './editor/preview';
import { watchIds } from './editor/ids-watch';
import { syncDevice } from './editor/device-sync';
import './style.scss';
import './canvas.scss';

/**
 * Does this block type carry the attribute (so a value written to it is saved)?
 *
 * @param {string} name Block name.
 * @return {boolean} Supported.
 */
export const supports = ( name ) => !! getBlockType( name )?.attributes?.pbs;

const withDesign = createHigherOrderComponent(
	( BlockEdit ) =>
		function DesignBlockEdit( props ) {
			if ( ! supports( props.name ) ) {
				return <BlockEdit { ...props } />;
			}
			return (
				<>
					<BlockEdit { ...props } />
					{ props.isSelected && (
						<DesignPanel
							clientId={ props.clientId }
							name={ props.name }
						/>
					) }
					<DesignPreview
						clientId={ props.clientId }
						name={ props.name }
						attributes={ props.attributes }
						isSelected={ props.isSelected }
					/>
				</>
			);
		},
	'withPbswDesign'
);
addFilter( 'editor.BlockEdit', 'pbsw/design/panel', withDesign, 20 );

const withClasses = createHigherOrderComponent(
	( BlockListBlock ) =>
		function DesignBlockListBlock( props ) {
			const id = props.attributes?.pbs?.id;
			const bp = useSelect(
				( select ) => select( STORE ).getBreakpoint(),
				[]
			);
			if ( ! id ) {
				return <BlockListBlock { ...props } />;
			}
			const hidden = ( props.attributes.pbs.hide || [] ).includes( bp );
			const extra = `${ config().prefix }-s-${ id }${
				hidden ? ' pbsw-design-hidden-here' : ''
			}`;
			return (
				<BlockListBlock
					{ ...props }
					className={
						props.className
							? `${ props.className } ${ extra }`
							: extra
					}
				/>
			);
		},
	'withPbswDesignClasses'
);
addFilter( 'editor.BlockListBlock', 'pbsw/design/classes', withClasses );

// The premium global-class editor edits a `pbs.s` map with this same panel (d5).
addFilter( 'pbsw.design.stylePanel', 'pbsw/engine', () => StylePanel );

domReady( () => {
	watchIds();
	syncDevice();
} );
