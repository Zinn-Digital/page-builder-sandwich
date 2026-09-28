/**
 * pbs/section editor: the shared layout editor as a section.
 */
import LayoutEdit from '../layout/edit';

export default function SectionEdit( props ) {
	return <LayoutEdit { ...props } kind="section" />;
}
