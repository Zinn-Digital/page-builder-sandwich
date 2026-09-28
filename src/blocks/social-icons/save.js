/**
 * pbs/social-icons saved markup: the footprint-free fallback (a list of text links, no icons,
 * no classes). The live HTML is rebuilt by Blocks\Marketing::render_social_icons().
 */
import { safeUrl } from '../shared/kit-url';

/** Fallback text of a network link (plain names; no translation in save()). */
const NAMES = {
	facebook: 'Facebook',
	'x-twitter': 'X',
	instagram: 'Instagram',
	linkedin: 'LinkedIn',
	youtube: 'YouTube',
	tiktok: 'TikTok',
	pinterest: 'Pinterest',
	github: 'GitHub',
	whatsapp: 'WhatsApp',
	telegram: 'Telegram',
	reddit: 'Reddit',
	mastodon: 'Mastodon',
	threads: 'Threads',
	bluesky: 'Bluesky',
	discord: 'Discord',
	twitch: 'Twitch',
	snapchat: 'Snapchat',
	tumblr: 'Tumblr',
	vimeo: 'Vimeo',
	dribbble: 'Dribbble',
	behance: 'Behance',
	medium: 'Medium',
	spotify: 'Spotify',
};

export default function save( { attributes } ) {
	const links = ( attributes.items || [] )
		.map( ( item ) => ( {
			url: safeUrl( item && item.url ),
			text:
				( item && item.label ) ||
				NAMES[ item && item.networkSlug ] ||
				String( ( item && item.url ) || '' ).replace(
					/^(mailto:|tel:|https?:\/\/)/,
					''
				),
		} ) )
		.filter( ( l ) => l.url );
	if ( ! links.length ) {
		return null;
	}
	return (
		<ul>
			{ links.map( ( l, i ) => (
				<li key={ i }>
					<a href={ l.url }>{ l.text }</a>
				</li>
			) ) }
		</ul>
	);
}
