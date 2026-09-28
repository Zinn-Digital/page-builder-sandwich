/**
 * pbs/container editor: the shared layout editor as a container.
 */
import LayoutEdit from '../layout/edit';

export default function ContainerEdit( props ) {
	return <LayoutEdit { ...props } kind="container" />;
}
