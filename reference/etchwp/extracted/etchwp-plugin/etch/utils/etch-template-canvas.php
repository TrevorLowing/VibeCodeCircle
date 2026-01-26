<?php
/**
 * Get the template HTML.
 * This needs to run before <head> so that blocks can add scripts and styles in wp_head().
 *
 * @package Etch
 * @gplv2
 */

$original_html = (string) get_the_block_template_html();
$template_html = \Etch\Helpers\EtchGlobal::remove_wp_site_blocks_wrapper( $original_html );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>

<?php echo $template_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<?php wp_footer(); ?>
</body>
</html>
