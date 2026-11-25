<?php
/**
 * The administrator's half of the legacy conversion: the pages the background run left waiting.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A background run (WP-Cron, a visitor's request) has no unfiltered_html, so kses filters what it
 * writes. It therefore writes only posts kses leaves unchanged and leaves the rest waiting
 * (Batch::WAITING_META). This notice offers an administrator who can post unfiltered HTML to
 * convert them now, in their own request: no user is ever switched to or logged in.
 */
final class Waiting {

	/** The admin-post action and nonce action. */
	public const ACTION = 'pbsw_convert_waiting';

	/** Posts converted per chunk, and the seconds one request spends before it continues. */
	private const CHUNK  = 25;
	private const BUDGET = 20;

	/**
	 * Hook the notice and the handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
		add_action( 'admin_post_' . self::DISMISS, array( self::class, 'dismiss' ) );
	}

	/** The per-user dismiss action, and the user meta holding the count dismissed at. */
	public const DISMISS        = 'pbsw_convert_waiting_dismiss';
	public const DISMISSED_META = 'pbsw_waiting_dismissed';

	/**
	 * Whether the notice is limited to the plugin's own screens and the Pages list and can be
	 * dismissed per user (the WordPress.org build, guideline 11). Self-hosted builds show it on
	 * every admin screen until the pages are converted.
	 *
	 * @return bool
	 */
	public static function scoped(): bool {
		// dotorg-omit:begin
		return false;
		// phpcs:disable Squiz.PHP.NonExecutableCode.Unreachable -- the return below is reached in the WordPress.org build, where this dotorg-omit block is removed.
		// dotorg-omit:end
		return true;
	}
	// phpcs:enable Squiz.PHP.NonExecutableCode.Unreachable

	/**
	 * Whether a screen id is one the scoped notice may show on: the Pages list or a screen of
	 * the plugin's own menu.
	 *
	 * @param string $screen_id The admin screen id.
	 * @return bool
	 */
	public static function screen_allowed( string $screen_id ): bool {
		return 'edit-page' === $screen_id || str_contains( $screen_id, 'page-builder-sandwich' );
	}

	/**
	 * Whether this user dismissed the notice at (or above) the current waiting count. More pages
	 * waiting than when they dismissed it shows it again.
	 *
	 * @param int $user_id User id.
	 * @param int $count   Pages waiting now.
	 * @return bool
	 */
	public static function dismissed( int $user_id, int $count ): bool {
		$at = (int) get_user_meta( $user_id, self::DISMISSED_META, true );
		return $at > 0 && $count <= $at;
	}

	/**
	 * The Dismiss link's request: nonce, capability, then remember the count for this user.
	 *
	 * @return void
	 */
	public static function dismiss(): void {
		check_admin_referer( self::DISMISS );
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You are not allowed to convert these pages.', 'page-builder-sandwich' ), '', array( 'response' => 403 ) );
		}
		update_user_meta( get_current_user_id(), self::DISMISSED_META, max( 1, Batch::waiting_count() ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=page' ) );
		exit;
	}

	/**
	 * Whether the current user may convert the waiting pages.
	 *
	 * @return bool
	 */
	public static function allowed(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * The notice, with a Convert now button, while any page waits.
	 *
	 * @return void
	 */
	public static function notice(): void {
		if ( ! self::allowed() ) {
			return;
		}
		$scoped = self::scoped();
		if ( $scoped ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( null === $screen || ! self::screen_allowed( (string) $screen->id ) ) {
				return;
			}
		}
		$count = Batch::waiting_count();
		if ( $count < 1 || ( $scoped && self::dismissed( get_current_user_id(), $count ) ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of pages. */
					_n(
						'Page Builder Sandwich: %s page in the older format is waiting to be converted to blocks. It contains markup that only an administrator can save unchanged, so it was not converted in the background.',
						'Page Builder Sandwich: %s pages in the older format are waiting to be converted to blocks. They contain markup that only an administrator can save unchanged, so they were not converted in the background.',
						$count,
						'page-builder-sandwich'
					),
					number_format_i18n( $count )
				)
			);
			?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<?php wp_nonce_field( self::ACTION ); ?>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Convert now', 'page-builder-sandwich' ); ?></button>
					<?php if ( $scoped ) : ?>
						<a class="button-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS ) ); ?>"><?php esc_html_e( 'Dismiss', 'page-builder-sandwich' ); ?></a>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * The button's request: nonce, then capability, then convert in chunks. A request that runs
	 * out of its time budget continues in the next one (same nonce, a cursor), so there is no cap.
	 *
	 * @return void
	 */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'You are not allowed to convert these pages.', 'page-builder-sandwich' ), '', array( 'response' => 403 ) );
		}
		// The cursor only ever arrives on the continuation redirect below (a GET); the button's own
		// POST starts at 0.
		$after = isset( $_GET['after'] ) ? absint( wp_unslash( $_GET['after'] ) ) : 0;
		$until = time() + self::BUDGET;
		do {
			$ids = Batch::waiting_ids( $after, self::CHUNK );
			Batch::convert_waiting( $ids );
			$found = count( $ids );
			if ( array() !== $ids ) {
				$after = (int) max( $ids );
			}
		} while ( self::CHUNK === $found && time() < $until );

		if ( count( $ids ) === self::CHUNK ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'action'   => self::ACTION,
						'after'    => $after,
						'_wpnonce' => wp_create_nonce( self::ACTION ),
					),
					admin_url( 'admin-post.php' )
				)
			);
			exit;
		}
		wp_safe_redirect( (string) ( wp_get_referer() ? wp_get_referer() : admin_url() ) );
		exit;
	}
}
