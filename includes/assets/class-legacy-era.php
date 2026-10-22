<?php
/**
 * When this site jumped from the legacy plugin (4.x/5.x) to 6.x, and which posts predate it.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Assets;

use ZinnDigital\PBS\Migrate\Batch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The legacy era: posts last saved while 4.x/5.x was the active plugin.
 *
 * ⭐ WHY. 4.x/5.x wrapped EVERY post's content in its main wrapper and loaded its stylesheet on
 * every page, so a post with no legacy markup at all still rendered through the wrapper rules
 * (images centred at phone width, `hr`, `p` spacing). Markup cannot tell such a post apart from a
 * 6.x one, so the moment of the jump does: a post whose `post_modified_gmt` is not after it was
 * last saved under the legacy plugin (COMPAT1, 2026-10-20: site 75061 lost the wrapper on all 318
 * posts of its home page and moved by up to 20% of its pixels at 390px).
 *
 * ⭐ WHEN IT IS RECORDED. Once per site, never changed:
 * - the first migration run 6.x ever starts (Batch::start() with no state yet) is the jump — an
 *   in-place update (WP-CLI `plugin install --force`, wp-admin's update, a replaced zip) keeps the
 *   plugin active and fires no activation hook, so this is the first 6.x code that writes;
 * - a site where 6.x already ran (a migration state exists) gets NONE: its plain posts have been
 *   rendered without the wrapper since that jump, so wrapping them now would be a new change;
 * - a deliberate activation (Plugin::activate()) gets NONE too, unless the legacy plugin was
 *   deactivated within WordPress's `recently_activated` window (a hand replacement of 5.x).
 * A site that never ran the legacy plugin (none of its options) always gets NONE.
 *
 * ⛔ PERFORMANCE: the option is autoloaded; a request reads it from memory and compares two
 * strings per post it renders. Nothing here queries on the front end.
 */
final class Legacy_Era {

	/** The cutoff: a GMT `Y-m-d H:i:s`, or NONE. Autoloaded. */
	public const OPTION = 'pbsw_legacy_era';

	/** No legacy era on this site. */
	public const NONE = '0';

	/** The legacy plugin's own options: any one of them means it ran here. */
	private const LEGACY_OPTIONS = array( 'pbs_install_date', 'pbs_options', 'pbs_first_load_intro_v4', 'pbs_no_migration_notice' );

	/** The basename the legacy plugin and 6.x share (class-plugin.php). */
	private const BASENAME = 'page-builder-sandwich/class-plugin.php';

	/**
	 * The recorded cutoff, or NONE (also while nothing is recorded yet).
	 *
	 * @return string
	 */
	public static function cutoff(): string {
		$value = get_option( self::OPTION, self::NONE );
		return is_string( $value ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : self::NONE;
	}

	/**
	 * Was this post last saved before the jump? Pure on the post and the cutoff.
	 *
	 * @param \WP_Post $post   Post.
	 * @param string   $cutoff self::cutoff().
	 * @return bool
	 */
	public static function predates( \WP_Post $post, string $cutoff ): bool {
		return self::saved_before( (string) $post->post_modified_gmt, $cutoff );
	}

	/**
	 * Pure: a `post_modified_gmt` against the cutoff.
	 *
	 * @param string $modified_gmt `Y-m-d H:i:s`.
	 * @param string $cutoff       self::cutoff().
	 * @return bool
	 */
	public static function saved_before( string $modified_gmt, string $cutoff ): bool {
		if ( self::NONE === $cutoff ) {
			return false;
		}
		// MySQL datetimes compare as strings; an unset date (all zeros) is older than any cutoff.
		return $modified_gmt <= $cutoff;
	}

	/**
	 * Record the era from a migration run about to start (Batch::start()).
	 *
	 * @param bool $first_run Whether 6.x never ran a migration here before.
	 * @return void
	 */
	public static function record_from_migration( bool $first_run ): void {
		self::record( $first_run && self::legacy_ran() );
	}

	/**
	 * Record the era on a deliberate activation (Plugin::activate()).
	 *
	 * @return void
	 */
	public static function record_on_activation(): void {
		$recent = get_option( 'recently_activated', array() );
		self::record(
			array() === Batch::state()
			&& is_array( $recent ) && isset( $recent[ self::BASENAME ] )
			&& self::legacy_ran()
		);
	}

	/**
	 * Store the cutoff once (add_option() never overwrites, so two racing requests agree).
	 *
	 * @param bool $jump Whether this moment is the jump from the legacy plugin.
	 * @return void
	 */
	private static function record( bool $jump ): void {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		add_option( self::OPTION, $jump ? current_time( 'mysql', true ) : self::NONE, '', true );
	}

	/**
	 * Did the legacy plugin ever run on this site?
	 *
	 * @return bool
	 */
	private static function legacy_ran(): bool {
		foreach ( self::LEGACY_OPTIONS as $name ) {
			if ( false !== get_option( $name, false ) ) {
				return true;
			}
		}
		return false;
	}
}
