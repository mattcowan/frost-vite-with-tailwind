<?php
/**
 * Development asset loading functions
 *
 * Handles Vite dev server detection and Hot Module Replacement (HMR) asset loading.
 * Only loaded when WP_DEBUG is true or WP_ENVIRONMENT_TYPE is not 'production'.
 *
 * @package Frost_Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if Vite dev server is running.
 *
 * Attempts to connect to the configured dev server to detect if Vite is active.
 * Checks multiple ports in the configured range if the primary port isn't responding.
 * Uses caching to avoid performance impact.
 *
 * @since 1.0.0
 * @return bool|int True if dev server is running, false otherwise. Returns the active port number on success.
 */
function frost_child_is_vite_dev_server_running() {
    static $result = null;

    // Cache result in static variable for this request
    if (null !== $result) {
        return $result;
    }

    // Check transient cache (5 minute TTL)
    $cache_key = 'frost_child_vite_dev_server_running';
    $cached = get_transient($cache_key);

    if (false !== $cached) {
        $result = ($cached === 'false') ? false : (int) $cached;
        return $result;
    }

    // Get configured host and port range
    $host = defined('VITE_DEV_SERVER_HOST') ? VITE_DEV_SERVER_HOST : 'localhost';
    $port_range = defined('VITE_DEV_SERVER_PORT_RANGE') ? VITE_DEV_SERVER_PORT_RANGE : [3000];

    // Ensure port range is an array
    if (!is_array($port_range)) {
        $port_range = [$port_range];
    }

    // Try to connect to dev server on each port in range
    $active_port = false;
    // Temporarily suppress fsockopen warnings; log minimal info in WP_DEBUG mode for troubleshooting.
    set_error_handler(function ($errno, $errstr) use ($host, $port_range) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Vite Dev Server Check] Connection attempt failed (errno %d): %s. Host: %s, Ports: %s',
                $errno,
                $errstr,
                $host,
                implode(',', (array) $port_range)
            ));
        }
        // Return true to indicate the error has been handled and prevent default PHP warning output.
        return true;
    });

    foreach ($port_range as $port) {
        $connection = fsockopen($host, $port, $errno, $errstr, 1);

        if ($connection) {
            fclose($connection);
            $active_port = $port;
            break;
        }
    }

    restore_error_handler();

    if ($active_port) {
        $result = $active_port;
        // Cache positive result for 5 minutes (store port number)
        set_transient($cache_key, $active_port, 5 * MINUTE_IN_SECONDS);
    } else {
        $result = false;
        // Cache negative result for 1 minute (shorter to detect when server starts)
        set_transient($cache_key, 'false', MINUTE_IN_SECONDS);
    }

    return $result;
}

/**
 * Enqueue Vite dev server assets with HMR support.
 *
 * Overrides the production asset enqueueing when dev server is detected.
 * Falls back to production mode if dev server is not running.
 *
 * @since 1.0.0
 * @return void
 */
function frost_child_enqueue_dev_assets() {
    // Check if dev server is running (returns port number or false)
    $active_port = frost_child_is_vite_dev_server_running();

    if (!$active_port) {
        // Dev server not running, remove this hook and let production function handle it
        remove_action('wp_enqueue_scripts', 'frost_child_enqueue_dev_assets', 10);
        return;
    }

    // Remove production hook since we're using dev server
    remove_action('wp_enqueue_scripts', 'frost_child_enqueue_assets', 20);

    // Get dev server URL using the detected port
    $dev_server_url = function_exists('frost_child_get_vite_url')
        ? frost_child_get_vite_url($active_port)
        : 'http://localhost:' . $active_port;

    // Enqueue Vite client for HMR
    wp_enqueue_script(
        'frost-child-vite-client',
        $dev_server_url . '/@vite/client',
        array(),
        null,
        false // Load in head for HMR to work properly
    );
    wp_script_add_data('frost-child-vite-client', 'type', 'module');

    // Enqueue main JavaScript module from dev server
    wp_enqueue_script(
        'frost-child-main',
        $dev_server_url . '/src/js/main.js',
        array('frost-child-vite-client'),
        null,
        true // in footer
    );
    wp_script_add_data('frost-child-main', 'type', 'module');

    // Note: CSS is handled by Vite's HMR and injected automatically
    // We still need to include the CSS entry point for Vite to process it
    // This is done via the main.js import in the actual source file
}
// Hook with higher priority (10) to run before production hook (20)
add_action('wp_enqueue_scripts', 'frost_child_enqueue_dev_assets', 10);

/**
 * Add type="module" attribute to dev server scripts.
 *
 * Ensures module scripts are properly loaded with the correct type attribute.
 *
 * @since 1.0.0
 * @param string $tag    The script tag.
 * @param string $handle The script handle.
 * @return string Modified script tag.
 */
function frost_child_dev_script_type_module($tag, $handle) {
    if (in_array($handle, ['frost-child-vite-client', 'frost-child-main'], true)) {
        if (strpos($tag, 'type=') === false) {
            $tag = str_replace(' src=', ' type="module" src=', $tag);
        }
    }
    return $tag;
}
add_filter('script_loader_tag', 'frost_child_dev_script_type_module', 10, 2);

/**
 * Display admin notice when using Vite dev server.
 *
 * Shows a notice to admins when the dev server is active, for awareness.
 *
 * @since 1.0.0
 * @return void
 */
function frost_child_dev_mode_notice() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show on admin pages
    if (!is_admin()) {
        return;
    }

    // Check if dev server is running (returns port number or false)
    $active_port = frost_child_is_vite_dev_server_running();

    if (!$active_port) {
        return;
    }

    // Get the full dev server URL for display
    $dev_server_url = function_exists('frost_child_get_vite_url')
        ? frost_child_get_vite_url($active_port)
        : 'http://localhost:' . $active_port;

    echo '<div class="notice notice-info is-dismissible">';
    echo '<p><strong>Development Mode:</strong> Vite dev server detected at <code>' . esc_html($dev_server_url) . '</code>. Hot Module Replacement (HMR) is active.</p>';
    echo '</div>';
}
add_action('admin_notices', 'frost_child_dev_mode_notice');
