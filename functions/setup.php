<?php
/**
 * Theme Setup and Configuration
 *
 * @package Frost_Child
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Disable theme and plugin file editors
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}

/**
 * Note: Parent and child theme styles are both handled by Vite.
 * - Parent theme styles are imported via @import in src/css/main.css
 * - Child theme styles are compiled and enqueued by Vite (see prod-assets.php)
 * - The default child theme style.css is filtered out (see vite-integration.php)
 */



