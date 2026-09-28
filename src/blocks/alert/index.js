/**
 * pbs/alert — the REFERENCE block of the design system (lane L09): copy this folder's shape.
 * Exports what src/blocks/index.js registers and what the test bar registers: metadata +
 * settings, nothing else.
 */
import metadata from '../../../blocks/alert/block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';

export { metadata };

export const settings = { edit, save, deprecated };
