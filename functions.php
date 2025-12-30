<?php
/**
 * Frost Child Theme Functions
 *
 * @package Frost_Child
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load Composer autoloader
 */
$composer_autoload = get_stylesheet_directory() . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
} else {
	error_log( 'Composer autoloader not found at: ' . $composer_autoload );
}

/**
 * Load theme configuration (load first, before other functions)
 */
require_once get_stylesheet_directory() . '/config.php';

/**
 * Include modular function files
 */
require_once get_stylesheet_directory() . '/functions/setup.php';

/**
 * Load production asset functions (always loaded)
 */
require_once get_stylesheet_directory() . '/functions/prod-assets.php';

/**
 * Load Vite integration (handles environment detection, dev server, and asset filtering)
 */
require_once get_stylesheet_directory() . '/functions/vite-integration.php';
