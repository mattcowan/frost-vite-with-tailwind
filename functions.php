<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Frost Child Theme Functions
 *
 * @package Frost_Child
 */

/**
 * Include modular function files
 */
require_once get_stylesheet_directory() . '/functions/setup.php';
require_once get_stylesheet_directory() . '/functions/svg-support.php';

/**
 * Load theme configuration (load first, before other functions)
 */
require_once get_stylesheet_directory() . '/config.php';

/**
 * Load production asset functions (always loaded)
 */
require_once get_stylesheet_directory() . '/functions/prod-assets.php';

/**
 * Load Vite integration (handles environment detection, dev server, and asset filtering)
 */
require_once get_stylesheet_directory() . '/functions/vite-integration.php';
