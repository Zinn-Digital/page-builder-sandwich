<?php
/**
 * The "Blank canvas" page template: the page's content and nothing else of the theme's.
 *
 * ⛔ Front end: no plugin name, no HTML comment, no generator tag (docs/adr/0033). The wrapper
 * keeps `entry-content` so a theme's content typography still applies, as the legacy template did.
 *
 * @package ZinnDigital\PBS
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// pbs-a2: a skip link to the content (the template owns the whole page, so it owns this too).
\ZinnDigital\PBS\Assets::enqueue_style( \ZinnDigital\PBS\Assets\Perf\Block_Assets::BASE_KEY );
$pbsw_main_id = \ZinnDigital\PBS\Settings::prefix() . '-main';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="<?php echo esc_attr( \ZinnDigital\PBS\Frontend::cls( 'skip' ) ); ?>" href="#<?php echo esc_attr( $pbsw_main_id ); ?>"><?php esc_html_e( 'Skip to content', 'page-builder-sandwich' ); ?></a>
<main id="<?php echo esc_attr( $pbsw_main_id ); ?>" class="entry-content" tabindex="-1">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php wp_footer(); ?>
</body>
</html>
