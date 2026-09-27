<?php
/**
 * One step of a migration run: the pure cursor arithmetic, with no WordPress in it.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advances a migration run by one batch.
 *
 * ⭐ WHY THIS SHAPE (CLAUDE.md §2.10). A run over a site's posts must RESUME, never truncate:
 * each step reads the next `$size` ids strictly after the cursor, handles every one of them, and
 * moves the cursor past each id it REACHED — whatever the outcome. So a post that fails is
 * recorded and never selected again, and the next step starts exactly where this one stopped.
 * There is no total cap: the run ends only when a fetch returns fewer ids than it asked for.
 *
 * ⛔ A post that kills the whole request (a fatal inside a converter) would otherwise be re-read
 * by every retry for ever. So the id is written down as `inflight` BEFORE it is handled; a step
 * that finds `inflight` still set knows the previous step died on that post, records it as
 * failed and moves the cursor past it before doing anything else.
 */
final class Run {

	/** Run modes. */
	public const CONVERT = 'convert';
	public const UNDO    = 'undo';

	/** Failure message recorded for a post whose request died mid-conversion. */
	public const INTERRUPTED = 'interrupted';

	/** Most recent failures kept in the state for the admin screen; every failure is also on its post. */
	public const FAILURES_KEPT = 100;

	/**
	 * A fresh state for a run.
	 *
	 * @param string $mode    self::CONVERT or self::UNDO.
	 * @param string $version Plugin version the run is for.
	 * @param int    $total   Candidate count when the run started (informative only).
	 * @param int    $now     Unix time.
	 * @return array<string, mixed>
	 */
	public static function fresh( string $mode, string $version, int $total, int $now ): array {
		return array(
			'mode'        => self::UNDO === $mode ? self::UNDO : self::CONVERT,
			'status'      => 'running',
			'version'     => $version,
			'cursor'      => 0,
			'inflight'    => 0,
			'total'       => max( 0, $total ),
			'processed'   => 0,
			'converted'   => 0,
			'skipped'     => 0,
			'restored'    => 0,
			'failed'      => 0,
			'failures'    => array(),
			'batches'     => 0,
			'started_at'  => $now,
			'finished_at' => 0,
		);
	}

	/**
	 * Handle one batch.
	 *
	 * `$fetch( int $after, int $limit, string $mode ): int[]` returns ids strictly greater than
	 * `$after`, ascending, at most `$limit`. `$handle( int $id, string $mode ): array` returns
	 * `array( 'outcome' => converted|skipped|restored|failed, 'message' => string )` and may throw.
	 * `$persist( array $state )` saves the state; it is called before and after every id, so a
	 * request that dies resumes exactly.
	 *
	 * @param array<string, mixed> $state   Current state (self::fresh() shape).
	 * @param callable             $fetch   Next ids after the cursor.
	 * @param callable             $handle  Handles one id.
	 * @param callable             $persist Saves the state.
	 * @param int                  $size    Batch size, >= 1.
	 * @param int                  $now     Unix time.
	 * @return array<string, mixed> The new state; `status` is `done` when nothing remains.
	 */
	public static function step( array $state, callable $fetch, callable $handle, callable $persist, int $size, int $now ): array {
		$size = max( 1, $size );
		$mode = (string) ( $state['mode'] ?? self::CONVERT );

		$inflight = (int) ( $state['inflight'] ?? 0 );
		if ( $inflight > 0 ) {
			// The message is a code: the admin screen translates it (every user-facing string is).
			$state = self::record( $state, $inflight, 'failed', self::INTERRUPTED );
			$persist( $state );
		}

		$ids = $fetch( (int) $state['cursor'], $size, $mode );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id <= (int) $state['cursor'] ) {
				// A fetch that does not honour the cursor would loop for ever; refuse to go backwards.
				continue;
			}
			$state['inflight'] = $id;
			$persist( $state );
			try {
				$result  = $handle( $id, $mode );
				$outcome = (string) ( $result['outcome'] ?? 'failed' );
				$message = (string) ( $result['message'] ?? '' );
			} catch ( \Throwable $e ) {
				$outcome = 'failed';
				$message = $e->getMessage();
			}
			$state = self::record( $state, $id, $outcome, $message );
			$persist( $state );
		}

		++$state['batches'];
		if ( count( $ids ) < $size ) {
			$state['status']      = 'done';
			$state['finished_at'] = $now;
		}
		$persist( $state );

		return $state;
	}

	/**
	 * Count one id's outcome and move the cursor past it.
	 *
	 * @param array<string, mixed> $state   State.
	 * @param int                  $id      Post id reached.
	 * @param string               $outcome converted|skipped|restored|failed.
	 * @param string               $message Failure message.
	 * @return array<string, mixed>
	 */
	private static function record( array $state, int $id, string $outcome, string $message ): array {
		if ( ! in_array( $outcome, array( 'converted', 'skipped', 'restored', 'failed' ), true ) ) {
			$outcome = 'failed';
			$message = '' === $message ? 'Unknown result.' : $message;
		}
		++$state[ $outcome ];
		++$state['processed'];
		if ( 'failed' === $outcome ) {
			$failures          = (array) $state['failures'];
			$failures[]        = array(
				'id'      => $id,
				'message' => $message,
			);
			$state['failures'] = array_slice( $failures, -self::FAILURES_KEPT );
		}
		$state['cursor']   = max( (int) $state['cursor'], $id );
		$state['inflight'] = 0;

		return $state;
	}
}
