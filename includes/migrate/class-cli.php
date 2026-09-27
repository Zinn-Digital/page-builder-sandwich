<?php
/**
 * `wp pbs migrate status|run|undo [--post=<id>]`.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts legacy Page Builder Sandwich content to blocks, or undoes the conversion.
 *
 * `run` and `undo` without `--post` start a site-wide run and work it to the end in this
 * process (no time limit applies to WP-CLI), holding the same lock the background queue uses.
 */
final class Cli {

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'pbs migrate', self::class );
		}
	}

	/**
	 * Show the migration state.
	 *
	 * [--format=<format>]
	 * : table or json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @param string[]              $args  Positional.
	 * @param array<string, string> $assoc Named.
	 * @return void
	 */
	public function status( array $args, array $assoc ): void {
		unset( $args );
		$payload = Rest_Routes::payload();
		if ( 'json' === ( $assoc['format'] ?? 'table' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $payload ) );
			return;
		}
		$rows = array();
		foreach ( $payload as $key => $value ) {
			if ( 'failures' !== $key ) {
				$rows[] = array(
					'field' => $key,
					'value' => is_bool( $value ) ? ( $value ? 'yes' : 'no' ) : (string) $value,
				);
			}
		}
		\WP_CLI\Utils\format_items( 'table', $rows, array( 'field', 'value' ) );
		foreach ( $payload['failures'] as $failure ) {
			\WP_CLI::warning( sprintf( '#%d %s: %s', $failure['id'], $failure['title'], $failure['message'] ) );
		}
	}

	/**
	 * Convert legacy content: every post, or one.
	 *
	 * [--post=<id>]
	 * : Convert only this post.
	 *
	 * @param string[]              $args  Positional.
	 * @param array<string, string> $assoc Named.
	 * @return void
	 */
	public function run( array $args, array $assoc ): void {
		unset( $args );
		if ( isset( $assoc['post'] ) ) {
			$this->one( (int) $assoc['post'], Batch::convert_post( (int) $assoc['post'], 'manual' ) );
			return;
		}
		$this->drive( Batch::start( Run::CONVERT, 'manual' ) );
	}

	/**
	 * Undo conversions from their backups: every post, or one.
	 *
	 * [--post=<id>]
	 * : Undo only this post.
	 *
	 * @param string[]              $args  Positional.
	 * @param array<string, string> $assoc Named.
	 * @return void
	 */
	public function undo( array $args, array $assoc ): void {
		unset( $args );
		if ( isset( $assoc['post'] ) ) {
			$this->one( (int) $assoc['post'], Batch::undo_post( (int) $assoc['post'] ) );
			return;
		}
		$this->drive( Batch::undo_all() );
	}

	/**
	 * Report a single-post result.
	 *
	 * @param int                   $id     Post id.
	 * @param array<string, string> $result Result.
	 * @return void
	 */
	private function one( int $id, array $result ): void {
		if ( 'failed' === $result['outcome'] ) {
			\WP_CLI::error( sprintf( '#%d failed: %s', $id, $result['message'] ?? '' ) );
		}
		\WP_CLI::success( sprintf( '#%d %s', $id, $result['outcome'] ) );
	}

	/**
	 * Work a started run to the end in this process.
	 *
	 * @param array<string, mixed>|null $state Start result.
	 * @return void
	 */
	private function drive( ?array $state ): void {
		if ( null === $state ) {
			\WP_CLI::error( 'A migration step is running right now; try again in a minute.' );
		}
		while ( 'running' === ( $state['status'] ?? '' ) ) {
			$next = Batch::step( (string) $state['run'] );
			if ( null === $next ) {
				// The background queue is running a step of the same run: wait for it, then carry on.
				sleep( 2 );
				$next = Batch::state();
				if ( ( $next['run'] ?? '' ) !== $state['run'] ) {
					\WP_CLI::error( 'This run was replaced by another one (the admin screen or a second command).' );
				}
			}
			$state = $next;
			\WP_CLI::log( sprintf( '%d/%d processed', $state['processed'], $state['total'] ) );
		}
		\WP_CLI::success(
			sprintf(
				'%s: %d converted, %d skipped, %d restored, %d failed.',
				$state['mode'],
				$state['converted'],
				$state['skipped'],
				$state['restored'],
				$state['failed']
			)
		);
	}
}
