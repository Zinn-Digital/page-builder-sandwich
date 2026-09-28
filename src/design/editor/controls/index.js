/**
 * Control name (a prop's `control`) → component. Every control takes
 * `{ prop, value, placeholder, onChange, blockName }`: `placeholder` is the inherited value, shown
 * greyed; `onChange( undefined )` removes the value at this breakpoint.
 *
 * ⭐ A new prop with a new kind of control (effects, P5) adds its component file and ONE line here.
 */
import {
	ButtonsPropControl,
	NumberPropControl,
	RangePropControl,
	SelectPropControl,
	SpanPropControl,
	TextPropControl,
} from './basic';
import {
	GapControl,
	LengthControl,
	SidesControl,
	cornerNames,
	sideNames,
} from './length';
import { ColorPropControl } from './color';
import {
	AspectRatioControl,
	BackgroundImageControl,
	BorderControl,
	FontFamilyControl,
	GridAreasControl,
	GridTemplateControl,
	ShadowControl,
	TransitionControl,
} from './complex';

const LengthPropControl = ( { prop, ...rest } ) => (
	<LengthControl label={ prop.label } opts={ prop.opts } { ...rest } />
);

export const CONTROLS = {
	select: SelectPropControl,
	buttons: ButtonsPropControl,
	text: TextPropControl,
	number: NumberPropControl,
	range: RangePropControl,
	span: SpanPropControl,
	length: LengthPropControl,
	sides: ( props ) => <SidesControl { ...props } names={ sideNames() } />,
	corners: ( props ) => <SidesControl { ...props } names={ cornerNames() } />,
	gap: GapControl,
	color: ColorPropControl,
	'grid-template': GridTemplateControl,
	'grid-areas': GridAreasControl,
	'aspect-ratio': AspectRatioControl,
	'font-family': FontFamilyControl,
	'background-image': BackgroundImageControl,
	border: BorderControl,
	shadow: ShadowControl,
	transition: TransitionControl,
};
