/**
 * pbs/star-rating editor: the same stars the page shows (Blocks\Data::render_star_rating()).
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';

import { cls } from '../shared/cls';

const STAR =
	'M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z';
const HALF = 'M12 2.5v14.8l-5.9 3.3 1.3-6.6-4.9-4.6 6.6-.8z';

/**
 * Clamp and round a rating the way the PHP does.
 *
 * @param {number} rating Rating.
 * @param {number} max    Highest.
 * @return {number} Rating in half steps.
 */
export function halfSteps( rating, max ) {
	return (
		Math.round(
			Math.max( 0, Math.min( max, Number( rating ) || 0 ) ) * 2
		) / 2
	);
}

export default function Edit( { attributes, setAttributes } ) {
	const max = Math.max( 1, Math.min( 10, attributes.max || 5 ) );
	const rating = halfSteps( attributes.rating, max );
	const shown = String( rating );
	const blockProps = useBlockProps( {
		className: cls( 'star-rating' ),
		role: 'img',
		'aria-label': sprintf(
			/* translators: 1: the rating, e.g. 4.5; 2: the highest possible rating, e.g. 5. */
			__( 'Rated %1$s out of %2$s', 'page-builder-sandwich' ),
			shown,
			max
		),
	} );
	const stars = [];
	for ( let i = 1; i <= max; i++ ) {
		let state = 'empty';
		if ( rating >= i ) {
			state = 'full';
		} else if ( rating >= i - 0.5 ) {
			state = 'half';
		}
		stars.push(
			<svg
				key={ i }
				className={ cls(
					'star-rating__star',
					`star-rating__star--${ state }`
				) }
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="24"
				height="24"
				focusable="false"
			>
				{ 'half' === state ? (
					<>
						<path
							className={ cls( 'star-rating__empty' ) }
							d={ STAR }
						/>
						<path
							className={ cls( 'star-rating__fill' ) }
							d={ HALF }
						/>
					</>
				) : (
					<path
						className={ cls(
							'full' === state
								? 'star-rating__fill'
								: 'star-rating__empty'
						) }
						d={ STAR }
					/>
				) }
			</svg>
		);
	}
	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Star rating', 'page-builder-sandwich' ) }
				>
					<RangeControl
						__next40pxDefaultSize
						label={ __( 'Rating', 'page-builder-sandwich' ) }
						value={ rating }
						min={ 0 }
						max={ max }
						step={ 0.5 }
						onChange={ ( value ) =>
							setAttributes( { rating: halfSteps( value, max ) } )
						}
					/>
					<RangeControl
						__next40pxDefaultSize
						label={ __(
							'Number of stars',
							'page-builder-sandwich'
						) }
						value={ max }
						min={ 1 }
						max={ 10 }
						onChange={ ( value ) =>
							setAttributes( {
								max: value,
								rating: halfSteps( attributes.rating, value ),
							} )
						}
					/>
					<ToggleControl
						label={ __(
							'Show the number',
							'page-builder-sandwich'
						) }
						checked={ attributes.showValue }
						onChange={ ( value ) =>
							setAttributes( { showValue: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<span className={ cls( 'star-rating__stars' ) }>{ stars }</span>
				{ attributes.showValue && (
					<span className={ cls( 'star-rating__value' ) }>
						{ `${ shown } / ${ max }` }
					</span>
				) }
			</div>
		</>
	);
}
