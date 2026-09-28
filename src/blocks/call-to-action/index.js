/**
 * pbs/call-to-action — editor module: metadata + settings, registered by src/blocks/index.js and
 * by the test bar.
 */
import metadata from '../../../blocks/call-to-action/block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';

export { metadata };

export const settings = { edit, save, deprecated };
