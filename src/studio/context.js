/**
 * The boot data PHP printed (window.pbswStudio), plus the crash-recovery storage, shared with
 * every Studio component.
 */
import { createContext, useContext } from '@wordpress/element';

export const BootContext = createContext( null );

/**
 * @return {Object} Boot data.
 */
export function useBoot() {
	return useContext( BootContext );
}

/** Panels and dialogs the header, commands and shortcuts all toggle. */
export const UiContext = createContext( null );

/**
 * @return {Object} UI state and setters.
 */
export function useUi() {
	return useContext( UiContext );
}
