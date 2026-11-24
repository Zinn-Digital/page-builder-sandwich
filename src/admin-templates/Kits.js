/**
 * The website-kit library: browse, preview and import complete sites in one click.
 *
 * Lists the kits this site can import; the server leaves out any others.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	CheckboxControl,
	ExternalLink,
	Modal,
	Notice,
	SearchControl,
	SelectControl,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

/**
 * The palette as swatches (the kit's preview on the card).
 *
 * @param {Object} props
 * @param {Object} props.palette Kit palette.
 * @return {Element} Swatches.
 */
function Swatches( { palette } ) {
	const names = [ 'contrast', 'primary', 'secondary', 'muted' ];
	return (
		<div className="pbs-kit__swatches" aria-hidden="true">
			{ names
				.filter( ( n ) => palette && palette[ n ] )
				.map( ( n ) => (
					<span
						key={ n }
						className="pbs-kit__swatch"
						style={ { backgroundColor: palette[ n ] } }
					/>
				) ) }
		</div>
	);
}

/**
 * The import dialog.
 *
 * @param {Object}   props
 * @param {Object}   props.kit     Catalogue entry.
 * @param {Object}   props.can     Site-wide options this user may choose ({ front, menu }).
 * @param {Function} props.onClose Close.
 * @return {Element} Modal.
 */
function ImportDialog( { kit, can, onClose } ) {
	const [ pages, setPages ] = useState( kit.pages.map( ( p ) => p.slug ) );
	const [ publish, setPublish ] = useState( false );
	const [ front, setFront ] = useState( false );
	const [ menu, setMenu ] = useState( !! can.menu );
	const [ busy, setBusy ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( '' );

	const run = () => {
		setBusy( true );
		setError( '' );
		apiFetch( {
			path: '/pbsw/v1/kits/import',
			method: 'POST',
			data: {
				slug: kit.slug,
				pages,
				publish,
				front: !! can.front && front,
				menu: !! can.menu && menu,
			},
		} )
			.then( setResult )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setBusy( false ) );
	};

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: kit name */
				__( 'Import “%s”', 'page-builder-sandwich' ),
				kit.name
			) }
			onRequestClose={ onClose }
			className="pbs-kit-import"
		>
			{ result ? (
				<>
					<Notice status="success" isDismissible={ false }>
						{ sprintf(
							/* translators: %d: number of pages */
							_n(
								'%d page imported.',
								'%d pages imported.',
								result.pages.length,
								'page-builder-sandwich'
							),
							result.pages.length
						) }
					</Notice>
					<ul className="pbs-kit-import__done">
						{ result.pages.map( ( p ) => (
							<li key={ p.id }>
								<a
									href={ p.url }
									target="_blank"
									rel="noreferrer"
								>
									{ p.slug }
								</a>{ ' ' }
								(
								<a
									href={ `post.php?post=${ p.id }&action=edit` }
								>
									{ __( 'edit', 'page-builder-sandwich' ) }
								</a>
								)
							</li>
						) ) }
					</ul>
					<Button variant="primary" onClick={ onClose }>
						{ __( 'Done', 'page-builder-sandwich' ) }
					</Button>
				</>
			) : (
				<>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }
					<p>{ kit.description }</p>
					<fieldset className="pbs-kit-import__pages">
						<legend>
							{ __( 'Pages to import', 'page-builder-sandwich' ) }
						</legend>
						{ kit.pages.map( ( p ) => (
							<CheckboxControl
								key={ p.slug }
								label={ p.title }
								checked={ pages.includes( p.slug ) }
								onChange={ ( on ) =>
									setPages(
										on
											? [ ...pages, p.slug ]
											: pages.filter(
													( s ) => s !== p.slug
												)
									)
								}
							/>
						) ) }
					</fieldset>
					<ToggleControl
						label={ __(
							'Publish the pages now (otherwise they are saved as drafts)',
							'page-builder-sandwich'
						) }
						checked={ publish }
						onChange={ setPublish }
					/>
					{ can.front && (
						<ToggleControl
							label={ __(
								'Use the kit’s home page as the site’s front page',
								'page-builder-sandwich'
							) }
							checked={ front }
							onChange={ setFront }
						/>
					) }
					{ can.menu && (
						<ToggleControl
							label={ __(
								'Create a menu with the imported pages',
								'page-builder-sandwich'
							) }
							checked={ menu }
							onChange={ setMenu }
						/>
					) }
					<p className="pbs-kit-import__note">
						{ __(
							'Importing never changes your existing pages. Importing the same kit again updates the pages it created before.',
							'page-builder-sandwich'
						) }
					</p>
					<Button
						variant="primary"
						isBusy={ busy }
						disabled={ busy || 0 === pages.length }
						onClick={ run }
					>
						{ __( 'Import', 'page-builder-sandwich' ) }
					</Button>
				</>
			) }
		</Modal>
	);
}

/**
 * The library.
 *
 * @return {Element} Kits.
 */
export default function Kits() {
	const [ catalogue, setCatalogue ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ category, setCategory ] = useState( '' );
	const [ tier, setTier ] = useState( '' );
	const [ importing, setImporting ] = useState( null );
	const [ switching, setSwitching ] = useState( false );

	const load = () =>
		apiFetch( { path: '/pbsw/v1/kits' } )
			.then( setCatalogue )
			.catch( ( e ) => setError( e.message ) );

	useEffect( () => {
		load();
	}, [] );

	const setOptIn = ( enabled ) => {
		setSwitching( true );
		setError( '' );
		apiFetch( {
			path: '/pbsw/v1/kits/opt-in',
			method: 'POST',
			data: { enabled },
		} )
			.then( load )
			.catch( ( e ) => setError( e.message ) )
			.finally( () => setSwitching( false ) );
	};

	const kits = useMemo(
		() => ( catalogue ? catalogue.kits : [] ),
		[ catalogue ]
	);
	const categories = useMemo(
		() => [ ...new Set( kits.map( ( k ) => k.category ) ) ].sort(),
		[ kits ]
	);
	const shown = kits.filter(
		( k ) =>
			( ! category || k.category === category ) &&
			( ! tier || k.tier === tier ) &&
			( ! search ||
				`${ k.name } ${ k.description } ${ k.niche }`
					.toLowerCase()
					.includes( search.toLowerCase() ) )
	);
	const mixedTiers =
		kits.some( ( k ) => 'pro' === k.tier ) &&
		kits.some( ( k ) => 'pro' !== k.tier );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error }
			</Notice>
		);
	}
	if ( ! catalogue ) {
		return <Spinner />;
	}
	if ( ! catalogue.optIn ) {
		return (
			<div className="pbs-kits">
				<h2>
					{ __(
						'Connect to the kit library?',
						'page-builder-sandwich'
					) }
				</h2>
				<p>
					{ __(
						'Website kits are stored on the Zinn Digital service. This site has not contacted it. If you switch the library on, this site requests the kit list and the kits you import from api.zinndigital.com. Each request sends your language and the plugin name, and your server address is visible to the service as with any web request. Your site address and content are not sent.',
						'page-builder-sandwich'
					) }
				</p>
				<p>
					<ExternalLink href="https://zinndigital.com/legal/terms">
						{ __( 'Terms of Service', 'page-builder-sandwich' ) }
					</ExternalLink>{ ' ' }
					<ExternalLink href="https://zinndigital.com/legal/privacy">
						{ __( 'Privacy Policy', 'page-builder-sandwich' ) }
					</ExternalLink>
				</p>
				{ catalogue.canOptIn ? (
					<Button
						variant="primary"
						isBusy={ switching }
						disabled={ switching }
						onClick={ () => setOptIn( true ) }
					>
						{ __(
							'Switch on the kit library',
							'page-builder-sandwich'
						) }
					</Button>
				) : (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'An administrator needs to switch on the kit library.',
							'page-builder-sandwich'
						) }
					</Notice>
				) }
			</div>
		);
	}

	return (
		<div className="pbs-kits">
			<p className="pbs-kits__intro">
				{ __(
					'Complete websites you can import in one click: pages, sections and a menu, ready for your own words and pictures. Kits come from Zinn Digital® and new ones are added every month.',
					'page-builder-sandwich'
				) }
			</p>
			<div className="pbs-kits__filters">
				<SearchControl
					__nextHasNoMarginBottom
					label={ __( 'Search kits', 'page-builder-sandwich' ) }
					value={ search }
					onChange={ setSearch }
				/>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Category', 'page-builder-sandwich' ) }
					value={ category }
					options={ [
						{
							label: __(
								'All categories',
								'page-builder-sandwich'
							),
							value: '',
						},
						...categories.map( ( c ) => ( {
							label: c,
							value: c,
						} ) ),
					] }
					onChange={ setCategory }
				/>
				{ mixedTiers && (
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Edition', 'page-builder-sandwich' ) }
						value={ tier }
						options={ [
							{
								label: __(
									'Free and Pro',
									'page-builder-sandwich'
								),
								value: '',
							},
							{
								label: __( 'Free', 'page-builder-sandwich' ),
								value: 'free',
							},
							{
								label: __( 'Pro', 'page-builder-sandwich' ),
								value: 'pro',
							},
						] }
						onChange={ setTier }
					/>
				) }
			</div>
			<ul className="pbs-kits__grid">
				{ shown.map( ( kit ) => (
					<li key={ kit.slug } className="pbs-kit">
						{ kit.preview && kit.preview.image ? (
							<img
								className="pbs-kit__preview"
								src={ kit.preview.image }
								alt=""
								loading="lazy"
								width="400"
								height="250"
							/>
						) : (
							<Swatches palette={ kit.palette } />
						) }
						<div className="pbs-kit__body">
							<h3 className="pbs-kit__name">{ kit.name }</h3>
							<p className="pbs-kit__category">
								{ kit.category }
							</p>
							<p className="pbs-kit__description">
								{ kit.description }
							</p>
							<p className="pbs-kit__pages">
								{ kit.pages
									.map( ( p ) => p.title )
									.join( ' · ' ) }
							</p>
							<Button
								variant="secondary"
								onClick={ () => setImporting( kit ) }
							>
								{ __( 'Import', 'page-builder-sandwich' ) }
							</Button>
						</div>
					</li>
				) ) }
			</ul>
			{ 0 === shown.length && (
				<p>
					{ __(
						'No kit matches these filters.',
						'page-builder-sandwich'
					) }
				</p>
			) }
			{ importing && (
				<ImportDialog
					kit={ importing }
					can={ ( catalogue && catalogue.can ) || {} }
					onClose={ () => setImporting( null ) }
				/>
			) }
		</div>
	);
}
