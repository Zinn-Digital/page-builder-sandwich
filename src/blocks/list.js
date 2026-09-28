/**
 * Every free design-system block's editor module, in registry order. The editor registers this
 * list (src/blocks/index.js) and so does the test bar, so both see exactly the same blocks.
 * ⛔ Keep the markers: wp/bin/pbs-new-block.php inserts above them. The vitest leg holds this
 * list to includes/blocks/class-registry.php in both directions.
 */
// pbs-blocks-imports:start
import * as alert from './alert';
import * as section from './section';
import * as container from './container';
// pbs-blocks-imports:end

export const BLOCKS = [
	// pbs-blocks-list:start
	alert,
	section,
	container,
	// pbs-blocks-list:end
];
