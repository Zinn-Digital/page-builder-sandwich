/**
 * Block settings keyed by name: block.json metadata plus edit and save. Kept apart from
 * index.js so the tests register exactly what the editor registers.
 */
import row from '../../blocks/row/block.json';
import column from '../../blocks/column/block.json';
import button from '../../blocks/button/block.json';
import icon from '../../blocks/icon/block.json';
import widget from '../../blocks/widget/block.json';
import sidebar from '../../blocks/sidebar/block.json';

import { buttonSave, columnSave, dynamicSave, iconSave, rowSave } from './save';

export const BLOCKS = [
	{ metadata: row, save: rowSave, edit: 'RowEdit' },
	{ metadata: column, save: columnSave, edit: 'ColumnEdit' },
	{ metadata: button, save: buttonSave, edit: 'ButtonEdit' },
	{ metadata: icon, save: iconSave, edit: 'IconEdit' },
	{ metadata: widget, save: dynamicSave, edit: 'WidgetEdit' },
	{ metadata: sidebar, save: dynamicSave, edit: 'SidebarEdit' },
];
