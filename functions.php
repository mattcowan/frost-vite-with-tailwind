<?php
/**
 * Frost Child Theme Functions
 *
 * @package Frost_Child
 */

/**
 * Enqueue parent and child theme styles
 */
function frost_child_enqueue_styles() {
	// Enqueue parent theme stylesheet
	wp_enqueue_style(
		'frost-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme()->parent()->get('Version')
	);

	// Enqueue child theme stylesheet
	wp_enqueue_style(
		'frost-child-style',
		get_stylesheet_uri(),
		array('frost-parent-style'),
		wp_get_theme()->get('Version')
	);
}
add_action('wp_enqueue_scripts', 'frost_child_enqueue_styles');
