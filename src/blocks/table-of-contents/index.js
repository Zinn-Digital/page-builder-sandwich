/**
 * pbs/table-of-contents — editor module: metadata + settings, registered by src/blocks/index.js and
 * by the test bar.
 */
import metadata from '../../../blocks/table-of-contents/block.json';
import edit from './edit';
import save from './save';
import deprecated from './deprecated';

export { metadata };

export const settings = { edit, save, deprecated };
