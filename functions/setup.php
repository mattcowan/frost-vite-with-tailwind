<?php 
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// Disable theme and plugin file editors
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}
/**
 * Enqueue parent and child theme styles
 */
function frost_child_enqueue_styles() {
	$parent_theme = wp_get_theme()->parent();
	$parent_version = wp_get_theme()->get('Version');
	if ( $parent_theme !== null ) {
		$parent_version = $parent_theme->get('Version');
	}

	// Enqueue parent theme stylesheet
	if ( $parent_theme ) {
		wp_enqueue_style(
			'frost-parent-style',
			get_template_directory_uri() . '/style.css',
			array(),
			$parent_theme->get('Version')
		);
	}

	// Enqueue child theme stylesheet
	wp_enqueue_style(
		'frost-child-style',
		get_stylesheet_uri(),
		array('frost-parent-style'),
		wp_get_theme()->get('Version')
	);
}
add_action('wp_enqueue_scripts', 'frost_child_enqueue_styles');



