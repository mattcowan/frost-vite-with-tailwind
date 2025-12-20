<?php
/**
 * Production asset loading functions
 *
 * Handles manifest-based asset enqueueing for production builds.
 * These functions are always loaded regardless of environment.
 *
 * @package Frost_Child
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Vite manifest with caching and security validation.
 *
 * Reads and caches the Vite manifest.json file with path traversal protection
 * and manifest structure validation.
 *
 * @since 1.0.0
 * @return array|false Manifest array on success, false on failure.
 */
function frost_child_get_manifest() {
    static $manifest_cache = null;

    // Use static variable to cache within the same request
    if (null !== $manifest_cache) {
        return $manifest_cache;
    }

    $theme_dir = get_stylesheet_directory();
    $manifest_path = $theme_dir . '/dist/.vite/manifest.json';

    // Validate the manifest path is within theme directory (path traversal protection)
    $real_manifest_path = realpath($manifest_path);
    $real_theme_dir = realpath($theme_dir);

    if (false === $real_manifest_path ||
        false === $real_theme_dir ||
        0 !== strpos($real_manifest_path, $real_theme_dir)) {
        error_log('Frost Child: Vite manifest path validation failed - potential path traversal attempt.');
        $manifest_cache = false;
        return false;
    }

    // Check file exists and is readable
    if (!is_readable($real_manifest_path)) {
        // Use theme-specific cache key to prevent collisions in multisite
        $cache_key = 'frost_child_vite_manifest_' . md5($theme_dir) . '_missing';

        // Try to get cached 'missing' state
        $cached_missing = get_transient($cache_key);

        if (false === $cached_missing) {
            error_log('Frost Child: Vite manifest not found or not readable.');
            set_transient($cache_key, true, HOUR_IN_SECONDS);
        }

        $manifest_cache = false;
        return false;
    }

    // Use theme-specific cache key with file modification time
    $cache_key = 'frost_child_vite_manifest_' . md5($theme_dir) . '_' . filemtime($real_manifest_path);

    // Try to get cached manifest
    $manifest = get_transient($cache_key);

    if (false === $manifest) {
        // Cache miss - read and decode manifest
        $manifest_content = file_get_contents($real_manifest_path);

        if (false === $manifest_content) {
            error_log('Frost Child: Failed to read Vite manifest file.');
            $manifest_cache = false;
            return false;
        }

        $manifest = json_decode($manifest_content, true);

        // Validate manifest is a non-empty array
        if (!is_array($manifest) || empty($manifest)) {
            error_log('Frost Child: Vite manifest is not a valid JSON array.');
            $manifest_cache = false;
            return false;
        }

        // Lightweight runtime check for path traversal (detailed validation in verify-build.js)
        foreach ($manifest as $key => $entry) {
            if (!is_array($entry)) {
                error_log('Frost Child: Invalid manifest entry structure.');
                $manifest_cache = false;
                return false;
            }

            if (isset($entry['file'])) {
                $file = $entry['file'];

                // Check for directory traversal attacks (build script validates other cases)
                if (strpos($file, '..') !== false) {
                    error_log('Frost Child: Manifest contains path traversal sequence: ' . esc_html($file));
                    $manifest_cache = false;
                    return false;
                }
            }
        }

        // Use longer cache in production; shorter in development or if debugging
        $is_debug = (defined('WP_DEBUG') && WP_DEBUG);
        $is_not_production = (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE !== 'production');
        $cache_duration = ($is_debug || $is_not_production) ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
        set_transient($cache_key, $manifest, $cache_duration);
    }

    // Cache in static variable for this request
    $manifest_cache = $manifest;

    return $manifest;
}

/**
 * Enqueue compiled Vite assets (CSS and JS) with security validation.
 *
 * Loads the compiled JavaScript and CSS files from the Vite manifest with
 * filename validation to prevent path traversal.
 *
 * @since 1.0.0
 * @return void
 */
function frost_child_enqueue_assets() {
    $manifest = frost_child_get_manifest();

    if (!$manifest || !is_array($manifest)) {
        // Use different error messages based on WP_DEBUG setting
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $error_message = 'Vite manifest not found. Run "npm run build" in the theme directory.';
        } else {
            $error_message = 'Theme assets are missing. Please contact your site administrator.';
        }

        error_log('Frost Child: ' . $error_message);

        // Show admin notice for easier debugging
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function() use ($error_message) {
                printf(
                    '<div class="notice notice-error"><p><strong>Theme Error:</strong> %s</p></div>',
                    esc_html($error_message)
                );
            });
        }

        return;
    }

    // Get theme version for cache busting
    $theme_version = wp_get_theme()->get('Version');
    $dist_uri = get_stylesheet_directory_uri() . '/dist/';

    // Enqueue the main JavaScript file with validation
    if (isset($manifest['src/js/main.js']['file']) &&
        is_string($manifest['src/js/main.js']['file'])) {

        $js_file = $manifest['src/js/main.js']['file'];

        // Validate path doesn't traverse and filename matches Vite format (name.hash.js)
        // Hash is exactly 8 base64url characters: A-Za-z0-9_- (e.g., main.DtwKV1wl.js or main.CSQ746O9.js)
        // Ensure no directory separators to prevent traversal via subdirectories
        if (!str_contains($js_file, '..') &&
            !str_contains($js_file, '/') &&
            !str_contains($js_file, '\\') &&
            preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]{8}\.js$/', $js_file)) {
            // Use 'strategy' parameter if WP >= 6.3.0, otherwise use wp_script_add_data for defer
            if ( version_compare( get_bloginfo( 'version' ), '6.3.0', '>=' ) ) {
                wp_enqueue_script(
                    'frost-child-main',
                    $dist_uri . $js_file,
                    array(),
                    $theme_version,
                    array(
                        'in_footer' => true,
                        'strategy'  => 'defer',
                    )
                );
            } else {
                wp_enqueue_script(
                    'frost-child-main',
                    $dist_uri . $js_file,
                    array(),
                    $theme_version,
                    true // in_footer
                );
                wp_script_add_data( 'frost-child-main', 'defer', true );
            }
        } else {
            error_log('Frost Child: Invalid JavaScript filename in Vite manifest: ' . esc_html($js_file));
        }
    }

    // Enqueue the main CSS file with validation
    if (isset($manifest['src/css/main.css']['file']) &&
        is_string($manifest['src/css/main.css']['file'])) {

        $css_file = $manifest['src/css/main.css']['file'];

        // Validate path doesn't traverse and filename matches Vite format (name.hash.css)
        // Hash is exactly 8 base64url characters: A-Za-z0-9_- (e.g., style.DX2Go6rB.css or style.L98F1T-T.css)
        // Ensure no directory separators to prevent traversal via subdirectories
        if (!str_contains($css_file, '..') &&
            !str_contains($css_file, '/') &&
            !str_contains($css_file, '\\') &&
            preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]{8}\.css$/', $css_file)) {
            wp_enqueue_style(
                'frost-child-main',
                $dist_uri . $css_file,
                array(),
                $theme_version
            );
        } else {
            error_log('Frost Child: Invalid CSS filename in Vite manifest: ' . esc_html($css_file));
        }
    }
}
add_action('wp_enqueue_scripts', 'frost_child_enqueue_assets', 20);
