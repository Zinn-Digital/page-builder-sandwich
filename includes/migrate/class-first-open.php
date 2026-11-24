<?php
/**
 * Offer to convert a legacy post when someone opens it in an editor (pbs-f4).
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

namespace ZinnDigital\PBS\Migrate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Opening an editor is a read and changes nothing: a forged link to an edit URL must not be able
 * to rewrite a post (WordPress.org review, round 5). The editor shows an offer instead, and the
 * conversion runs only when the person presses its button:
 * - block editor: a notice whose button POSTs to `pbsw/v1/posts/<id>/convert` (REST nonce);
 * - classic editor: a notice with a form POSTing to admin-post.php (action nonce).
 *
 * ⛔ Only for a user who may edit THAT post (`edit_post` on its id), never a post a person undid
 * (Batch::OPTOUT_META), and never one already converted.
 */
final class First_Open {

	/**
	 * The admin-post action and nonce action prefix.
	 */
	public const ACTION = 'pbsw_convert_legacy_post';

	/**
	 * Hook the two offers and the classic editor's form handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'enqueue_block_editor_offer' ) );
		add_action( 'admin_notices', array( self::class, 'classic_editor_offer' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle_classic_convert' ) );
	}

	/**
	 * Block editor: a warning notice with a Convert button.
	 *
	 * @return void
	 */
	public static function enqueue_block_editor_offer(): void {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! self::can_offer( (int) $post->ID ) ) {
			return;
		}

		$config = array(
			'path'    => '/pbsw/v1/posts/' . (int) $post->ID . '/convert',
			'message' => __( 'This page uses the older Page Builder Sandwich format. Convert it to blocks to edit it here. A backup is kept, and you can undo the conversion.', 'page-builder-sandwich-by-zinn-digital' ),
			'button'  => __( 'Convert to blocks', 'page-builder-sandwich-by-zinn-digital' ),
			'failed'  => __( 'This page could not be converted. It has not been changed.', 'page-builder-sandwich-by-zinn-digital' ),
		);

		wp_enqueue_script( 'pbsw-first-open', plugins_url( 'assets/first-open.js', PBSW_FILE ), array( 'wp-api-fetch', 'wp-data', 'wp-dom-ready', 'wp-notices' ), PBSW_VERSION, true );
		wp_add_inline_script( 'pbsw-first-open', 'window.pbswFirstOpen = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
	}

	/**
	 * Classic editor: a notice with a nonce'd POST form. It prints above the post form, so the two
	 * forms never nest.
	 *
	 * @return void
	 */
	public static function classic_editor_offer(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || 'post' !== $screen->base || $screen->is_block_editor() ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! self::can_offer( (int) $post->ID ) ) {
			return;
		}
		$post_id = (int) $post->ID;
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'This page uses the older Page Builder Sandwich format. Convert it to blocks to edit it here. A backup is kept, and you can undo the conversion.', 'page-builder-sandwich-by-zinn-digital' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<?php wp_nonce_field( self::ACTION . '_' . $post_id ); ?>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Convert to blocks', 'page-builder-sandwich-by-zinn-digital' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * The classic editor form's POST: nonce for that post, then the capability on that post.
	 *
	 * @return void
	 */
	public static function handle_classic_convert(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the id names the nonce action, which is verified on the next line.
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		check_admin_referer( self::ACTION . '_' . $post_id );
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You are not allowed to convert this page.', 'page-builder-sandwich-by-zinn-digital' ), '', array( 'response' => 403 ) );
		}
		if ( self::can_offer( $post_id ) ) {
			Batch::convert_post( $post_id, 'manual' );
		}
		wp_safe_redirect( (string) get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}

	/**
	 * Whether this user should be offered a conversion of this post. Reads only.
	 *
	 * @param int $post_id Post id.
	 * @return bool
	 */
	public static function can_offer( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'revision' === $post->post_type || 'attachment' === $post->post_type ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		// Cheap test first: every legacy marker contains `pbs`.
		if ( ! str_contains( (string) $post->post_content, 'pbs' ) ) {
			return false;
		}
		if ( '' !== (string) get_post_meta( $post_id, Batch::CONVERTED_META, true ) || '' !== (string) get_post_meta( $post_id, Batch::OPTOUT_META, true ) ) {
			return false;
		}
		return Legacy::post_is_legacy( $post_id );
	}
}
