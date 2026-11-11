import { __, _n, sprintf } from '@wordpress/i18n';
import { ExternalLink, PanelBody } from '@wordpress/components';

/**
 * The grade words, keyed by the grade Zinn® SEO's Agent Ready report returns.
 *
 * @return {Object<string, string>} Grade → label.
 */
function gradeLabels() {
	return {
		ready: __( 'Ready for AI agents', 'page-builder-sandwich' ),
		nearly: __( 'Nearly ready', 'page-builder-sandwich' ),
		not_ready: __( 'Not ready yet', 'page-builder-sandwich' ),
	};
}

/**
 * The "AI agent readiness" panel. Zinn® SEO owns the score and its fix-it actions; this panel shows
 * the score from `data.agentReady` (Zinn_Seo::admin_data()) and links to the screen that fixes it.
 * Without Zinn® SEO it says what the check is and where to get it.
 *
 * @param {Object} props
 * @param {Object} props.report The agentReady boot data: active, score, grade, open and url.
 * @return {Element|null} The panel.
 */
export default function AgentReady( { report } ) {
	if ( ! report || ! report.url ) {
		return null;
	}
	const title = __( 'AI agent readiness', 'page-builder-sandwich' );
	if ( ! report.active ) {
		return (
			<PanelBody title={ title } initialOpen>
				<p>
					{ __(
						'Zinn® SEO scores how ready this site is for AI agents and answer engines (robots access, llms.txt, sitemaps, structured data and MCP) and fixes each gap in one click.',
						'page-builder-sandwich'
					) }
				</p>
				<ExternalLink href={ report.url }>
					{ __( 'Get Zinn® SEO', 'page-builder-sandwich' ) }
				</ExternalLink>
			</PanelBody>
		);
	}
	if ( typeof report.score !== 'number' ) {
		return (
			<PanelBody title={ title } initialOpen>
				<p>
					{ __(
						'Turn on the Agent Ready module in Zinn® SEO to see the score.',
						'page-builder-sandwich'
					) }
				</p>
				<a href={ report.url }>
					{ __( 'Open Agent Ready', 'page-builder-sandwich' ) }
				</a>
			</PanelBody>
		);
	}
	const open = Array.isArray( report.open ) ? report.open : [];
	const grade = gradeLabels()[ report.grade ] || '';

	return (
		<PanelBody title={ title } initialOpen>
			<p>
				<strong>
					{ sprintf(
						/* translators: %d: score from 0 to 100. */
						__( 'Score: %d/100', 'page-builder-sandwich' ),
						report.score
					) }
				</strong>
				{ grade ? ` · ${ grade }` : '' }
			</p>
			{ open.length > 0 && (
				<>
					<p>
						{ sprintf(
							/* translators: %d: number of checks that fail. */
							_n(
								'%d check to fix:',
								'%d checks to fix:',
								open.length,
								'page-builder-sandwich'
							),
							open.length
						) }
					</p>
					<ul>
						{ open.map( ( label ) => (
							<li key={ label }>{ label }</li>
						) ) }
					</ul>
				</>
			) }
			<a href={ report.url }>
				{ open.length > 0
					? __( 'Fix them in Zinn® SEO', 'page-builder-sandwich' )
					: __( 'Open Agent Ready', 'page-builder-sandwich' ) }
			</a>
		</PanelBody>
	);
}
