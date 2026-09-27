<?php
/**
 * The background migration: legacy content converted to blocks on upgrade, in batches.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schedules and runs migration batches through Action Scheduler (group `pbsw-migrate`).
 *
 * ⭐ HOW A RUN STARTS. When the plugin's version differs from the version the last run was made
 * for, a run is scheduled — on `admin_init` AND from `plugins_loaded`, so a site nobody logs into
 * still migrates on its next request (a visitor, WP-Cron, WP-CLI). Neither hook converts
 * anything inline: they only enqueue the first action, and every action enqueues the next until
 * the population is exhausted (Run::step()). There is no total cap (CLAUDE.md §2.10).
 *
 * ⭐ CONCURRENCY. One lock row (INSERT IGNORE into the options table, with an expiry) guards
 * both starting a run and running a step, so two queue runners, a second request or a CLI run
 * cannot process the same posts twice or overwrite each other's state. Each action carries its
 * run id; an action left over from a superseded run does nothing.
 *
 * ⭐ WHICH POSTS. Every post type that supports the editor (so custom types too, but never
 * revisions or attachments), in every status except `auto-draft`, whose content mentions `pbs`
 * (every legacy marker does: `pbs-` classes, `[pbs_…]` and `[pbsandwich_column]`) and that has
 * not been converted already. The converter decides per post; this only narrows the scan.
 */
final class Batch {

	/** State option (see Run::fresh()). */
	public const OPTION = 'pbsw_migration';

	/**
	 * Autoloaded `<version>:<status>` of the state, so the per-request check costs no query.
	 * The full state is not autoloaded: it holds up to a hundred failure rows.
	 */
	public const MARK = 'pbsw_migration_mark';

	/** Action Scheduler hook and group. */
	public const HOOK  = 'pbsw_migration_step';
	public const GROUP = 'pbsw-migrate';

	/** Lock row in the options table. */
	public const LOCK     = 'pbsw_migration_lock';
	public const LOCK_TTL = 300;

	/** Post meta. */
	public const ERROR_META     = '_pbsw_migration_error';
	public const OPTOUT_META    = '_pbsw_migration_optout';
	public const BACKUP_META    = '_pbsw_legacy_backup';
	public const CONVERTED_META = '_pbsw_converted';

	/** Posts per action; filterable with `pbsw_migration_batch_size`. */
	public const DEFAULT_SIZE = 25;

	/**
	 * The token of the lock this request holds, if any.
	 *
	 * @var string
	 */
	private static string $lock_token = '';

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'maybe_start' ), 20 );
		add_action( 'admin_init', array( self::class, 'maybe_start' ) );
		add_action( self::HOOK, array( self::class, 'run_action' ) );
		// Uninstall goes through the licensing SDK (Freemius refuses an uninstall.php).
		if ( function_exists( 'pbsw_fs' ) ) {
			pbsw_fs()->add_action( 'after_uninstall', array( self::class, 'uninstall' ) );
		}
	}

	/**
	 * Uninstall: drop the run state, the lock and queued steps. Post backups stay: they are the
	 * site owner's legacy content, and deleting them would make the conversion irreversible.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, null, self::GROUP );
		}
		delete_option( self::OPTION );
		delete_option( self::MARK );
		delete_option( self::LOCK );
	}

	/**
	 * Start a run when the version changed, and re-arm a running one whose chain was lost.
	 *
	 * Cheap on every request: one autoloaded-or-cached option read and a string comparison.
	 * Anything that touches Action Scheduler waits for `action_scheduler_init`, because its
	 * data store is not ready before `init`.
	 *
	 * @return void
	 */
	public static function maybe_start(): void {
		$mark = (string) get_option( self::MARK, '' );
		if ( PBSW_VERSION . ':done' === $mark ) {
			return;
		}
		if ( did_action( 'action_scheduler_init' ) ) {
			self::deferred_start();
		} elseif ( ! has_action( 'action_scheduler_init', array( self::class, 'deferred_start' ) ) ) {
			add_action( 'action_scheduler_init', array( self::class, 'deferred_start' ) );
		}
	}

	/**
	 * The `action_scheduler_init` half of maybe_start().
	 *
	 * @return void
	 */
	public static function deferred_start(): void {
		$state = self::state();
		if ( PBSW_VERSION !== ( $state['version'] ?? null ) ) {
			self::start( Run::CONVERT, 'auto' );
		} elseif ( 'running' === ( $state['status'] ?? '' ) ) {
			self::rearm();
		}
	}

	/**
	 * Start a run. Returns the new state, or null when another run holds the lock.
	 *
	 * @param string $mode    Run::CONVERT or Run::UNDO.
	 * @param string $trigger `auto` (version change: posts a person undid stay undone) or `manual`.
	 * @return array<string, mixed>|null
	 */
	public static function start( string $mode, string $trigger = 'manual' ): ?array {
		if ( ! self::lock() ) {
			return null;
		}
		try {
			if ( 'auto' === $trigger && PBSW_VERSION === ( self::state()['version'] ?? null ) ) {
				return self::state(); // Another request started it between our read and our lock.
			}
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::HOOK, null, self::GROUP );
			}
			$state            = Run::fresh( $mode, PBSW_VERSION, self::count( $mode, $trigger ), time() );
			$state['trigger'] = 'auto' === $trigger ? 'auto' : 'manual';
			$state['run']     = wp_generate_uuid4();
			if ( 0 === $state['total'] ) {
				$state['status']      = 'done';
				$state['finished_at'] = time();
			}
			self::save( $state );
			if ( 'running' === $state['status'] ) {
				self::enqueue( $state );
			}
			return $state;
		} finally {
			self::unlock();
		}
	}

	/**
	 * Undo every conversion that still has a backup, in the background.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function undo_all(): ?array {
		return self::start( Run::UNDO, 'manual' );
	}

	/**
	 * The Action Scheduler callback.
	 *
	 * @param string $run The run id the action was scheduled for.
	 * @return void
	 */
	public static function run_action( $run = '' ): void {
		self::step( (string) $run );
	}

	/**
	 * Run one batch of the current run; schedule the next when work remains.
	 *
	 * @param string $run Run id; an action for any other run does nothing.
	 * @return array<string, mixed>|null The state after the step, or null when nothing ran.
	 */
	public static function step( string $run = '' ): ?array {
		if ( ! self::lock() ) {
			return null; // Another step is running; it schedules the next one.
		}
		try {
			$state = self::state();
			if ( 'running' !== ( $state['status'] ?? '' ) || ( '' !== $run && ( $state['run'] ?? '' ) !== $run ) ) {
				return null;
			}
			$trigger = (string) ( $state['trigger'] ?? 'auto' );
			$size    = max( 1, (int) apply_filters( 'pbsw_migration_batch_size', self::DEFAULT_SIZE ) );
			$state   = Run::step(
				$state,
				static fn( int $after, int $limit, string $mode ): array => self::fetch( $after, $limit, $mode, $trigger ),
				static fn( int $id, string $mode ): array => Run::UNDO === $mode ? self::undo_post( $id ) : self::convert_post( $id, $trigger ),
				array( self::class, 'save' ),
				$size,
				time()
			);
			if ( 'running' === $state['status'] ) {
				self::enqueue( $state );
			}
			return $state;
		} finally {
			self::unlock();
		}
	}

	/**
	 * Convert one post now, recording the outcome on it.
	 *
	 * @param int    $post_id Post id.
	 * @param string $trigger `manual` clears a previous per-post undo.
	 * @return array{outcome:string, message?:string}
	 */
	public static function convert_post( int $post_id, string $trigger = 'manual' ): array {
		try {
			$result  = Converter::convert_post( $post_id );
			$status  = strtolower( (string) ( $result['status'] ?? '' ) );
			$message = (string) ( $result['message'] ?? '' );
			// Converter statuses (W2): converted | already-converted | not-legacy | missing | write-failed.
			if ( 'failed' === $status || 'error' === $status || str_ends_with( $status, '-failed' ) ) {
				$outcome = 'failed';
				$message = '' === $message ? $status : $message;
			} else {
				$outcome = 'converted' === $status ? 'converted' : 'skipped';
			}
		} catch ( \Throwable $e ) {
			$outcome = 'failed';
			$message = $e->getMessage();
		}

		if ( 'failed' === $outcome ) {
			update_post_meta( $post_id, self::ERROR_META, wp_slash( $message ) );
			return array(
				'outcome' => 'failed',
				'message' => $message,
			);
		}
		delete_post_meta( $post_id, self::ERROR_META );
		if ( 'converted' === $outcome && 'manual' === $trigger ) {
			delete_post_meta( $post_id, self::OPTOUT_META );
		}
		return array( 'outcome' => $outcome );
	}

	/**
	 * Restore one post from its backup and keep it out of automatic conversion.
	 *
	 * @param int $post_id Post id.
	 * @return array{outcome:string, message?:string}
	 */
	public static function undo_post( int $post_id ): array {
		try {
			$restored = Backup::restore( $post_id );
		} catch ( \Throwable $e ) {
			return array(
				'outcome' => 'failed',
				'message' => $e->getMessage(),
			);
		}
		if ( ! $restored ) {
			return array(
				'outcome' => 'failed',
				'message' => 'no-backup',
			);
		}
		// A person undid this post: an automatic run or the editor opening it must not convert it
		// again behind their back. A manual convert (REST, CLI, the admin button) clears this.
		update_post_meta( $post_id, self::OPTOUT_META, '1' );
		delete_post_meta( $post_id, self::ERROR_META );
		return array( 'outcome' => 'restored' );
	}

	/**
	 * The state option, normalised.
	 *
	 * @return array<string, mixed>
	 */
	public static function state(): array {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist the state (never autoloaded: it is read only by migration code and the admin screen).
	 *
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	public static function save( array $state ): void {
		update_option( self::OPTION, $state, false );
		$status = 'done' === ( $state['status'] ?? '' ) ? 'done' : 'running';
		update_option( self::MARK, (string) ( $state['version'] ?? '' ) . ':' . $status, true );
	}

	/**
	 * Status for the admin screen, REST and CLI.
	 *
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$state = self::state();
		global $wpdb;

		return array(
			'state'     => $state,
			'pending'   => function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::HOOK, null, self::GROUP ),
			'remaining' => self::count( Run::CONVERT, 'manual' ),
			'backups'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::BACKUP_META ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an admin-only count, cached by nothing on purpose.
			'failing'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::ERROR_META ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
		);
	}

	/**
	 * Post types a migration scans.
	 *
	 * @return list<string>
	 */
	public static function post_types(): array {
		$types = array();
		foreach ( get_post_types( array(), 'names' ) as $type ) {
			if ( in_array( $type, array( 'revision', 'attachment', 'nav_menu_item' ), true ) ) {
				continue;
			}
			if ( post_type_supports( $type, 'editor' ) ) {
				$types[] = (string) $type;
			}
		}
		/**
		 * Filters the post types the legacy migration converts.
		 *
		 * @param list<string> $types Post type names.
		 */
		return array_values( array_map( 'strval', (array) apply_filters( 'pbsw_migration_post_types', $types ) ) );
	}

	/**
	 * The next ids after the cursor.
	 *
	 * @param int    $after   Cursor.
	 * @param int    $limit   Batch size.
	 * @param string $mode    Run mode.
	 * @param string $trigger auto|manual.
	 * @return list<int>
	 */
	public static function fetch( int $after, int $limit, string $mode, string $trigger ): array {
		global $wpdb;
		if ( Run::UNDO === $mode ) {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id > %d GROUP BY post_id ORDER BY post_id ASC LIMIT %d", self::BACKUP_META, $after, $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a cursor scan; the object cache has nothing to offer it.
			return array_map( 'intval', (array) $ids );
		}
		[ $sql, $args ] = self::candidates_sql( $trigger );
		$args[]         = $after;
		$args[]         = $limit;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is built from fixed fragments and placeholders only; every value goes through prepare() in $args.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql . ' AND p.ID > %d ORDER BY p.ID ASC LIMIT %d', $args ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * How many posts a run in this mode would visit.
	 *
	 * @param string $mode    Run mode.
	 * @param string $trigger auto|manual.
	 * @return int
	 */
	public static function count( string $mode, string $trigger ): int {
		global $wpdb;
		if ( Run::UNDO === $mode ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s", self::BACKUP_META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see fetch().
		}
		[ $sql, $args ] = self::candidates_sql( $trigger );
		$sql            = preg_replace( '/^SELECT p\.ID /', 'SELECT COUNT(*) ', $sql );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- see fetch().
		return (int) $wpdb->get_var( $wpdb->prepare( (string) $sql, $args ) );
	}

	/**
	 * The candidate query without cursor or order.
	 *
	 * @param string $trigger auto|manual (auto leaves out posts a person undid).
	 * @return array{0: string, 1: list<int|string>}
	 */
	private static function candidates_sql( string $trigger ): array {
		global $wpdb;
		$types = self::post_types();
		if ( array() === $types ) {
			$types = array( 'post' );
		}
		$in   = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
		$sql  = "SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} c ON c.post_id = p.ID AND c.meta_key = %s";
		$args = array( self::CONVERTED_META );
		if ( 'auto' === $trigger ) {
			$sql   .= " LEFT JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = %s";
			$args[] = self::OPTOUT_META;
		}
		$sql .= " WHERE p.post_type IN ({$in}) AND p.post_status <> 'auto-draft' AND p.post_content LIKE %s AND c.meta_id IS NULL";
		$args = array_merge( $args, $types, array( '%' . $wpdb->esc_like( 'pbs' ) . '%' ) );
		if ( 'auto' === $trigger ) {
			$sql .= ' AND o.meta_id IS NULL';
		}
		return array( $sql, $args );
	}

	/**
	 * Queue the next action for this run (unique, so a double call queues one).
	 *
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	private static function enqueue( array $state ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( (string) ( $state['run'] ?? '' ) ), self::GROUP, true );
		}
	}

	/**
	 * A running run with no queued action lost its chain (an action failed hard, or the queue
	 * was cleared): queue the next step. Nothing is re-done — the cursor is persisted per post.
	 *
	 * @return void
	 */
	private static function rearm(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || as_has_scheduled_action( self::HOOK, null, self::GROUP ) ) {
			return;
		}
		if ( self::lock_held_by_other() ) {
			return;
		}
		self::enqueue( self::state() );
	}

	/**
	 * Take the lock. Not re-entrant: a request that already holds it gets false.
	 *
	 * @return bool
	 */
	private static function lock(): bool {
		global $wpdb;
		if ( '' !== self::$lock_token ) {
			return false;
		}
		$token = wp_generate_uuid4();
		$value = ( time() + self::LOCK_TTL ) . '|' . $token;
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			// ⛔ Not add_option(): it is INSERT … ON DUPLICATE KEY UPDATE, so it cannot fail on a
			// held lock. INSERT IGNORE affects one row only when the name was free.
			$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::LOCK, $value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- an atomic lock must bypass the options cache.
			if ( 1 === $inserted ) {
				self::$lock_token = $token;
				return true;
			}
			$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above.
			if ( (int) strtok( $held, '|' ) >= time() ) {
				return false;
			}
			// Expired: remove exactly the row we read, so two requests cannot both clear it.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::LOCK, $held ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above.
		}
		return false;
	}

	/**
	 * Is a live lock held (by anyone)?
	 *
	 * @return bool
	 */
	private static function lock_held_by_other(): bool {
		global $wpdb;
		$held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see lock().
		return '' !== $held && (int) strtok( $held, '|' ) >= time();
	}

	/**
	 * Release the lock this request holds.
	 *
	 * @return void
	 */
	private static function unlock(): void {
		global $wpdb;
		if ( '' === self::$lock_token ) {
			return;
		}
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value LIKE %s", self::LOCK, '%|' . $wpdb->esc_like( self::$lock_token ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see lock().
		self::$lock_token = '';
	}
}
