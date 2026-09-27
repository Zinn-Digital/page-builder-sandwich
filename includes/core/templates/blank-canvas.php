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
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="entry-content">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>
<?php wp_footer(); ?>
</body>
</html>
