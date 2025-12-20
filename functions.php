<?php
/**
 * Frost Child Theme Functions
 *
 * @package Frost_Child
 */

/**
 * Cached DNS resolution helper to avoid repeated gethostbyname() calls.
 *
 * Uses WordPress transients to cache DNS lookup results for 1 hour.
 * This prevents performance issues from DNS queries on every request.
 *
 * @param string $hostname The hostname to resolve.
 * @return string The resolved IP address, or the hostname if resolution failed.
 */
function frost_child_cached_dns_lookup($hostname) {
    $transient_key = 'frost_child_dns_' . preg_replace('/[^a-z0-9_]/i', '_', $hostname);
    $cached_result = get_transient($transient_key);

    if ($cached_result !== false) {
        return $cached_result;
    }

    $resolved_ip = gethostbyname($hostname);
    set_transient($transient_key, $resolved_ip, HOUR_IN_SECONDS);

    return $resolved_ip;
}

/**
 * Load theme configuration (load first, before other functions)
 */
require_once get_stylesheet_directory() . '/config.php';

/**
 * Load production asset functions (always loaded)
 */
require_once get_stylesheet_directory() . '/functions/prod-assets.php';

/**
 * Load development asset functions (only in debug/non-production environments)
 *
 * These functions enable Vite dev server detection and Hot Module Replacement.
 * They are excluded in production for performance and security.
 *
 * Dev mode is enabled when ANY of these conditions are true:
 * - WP_DEBUG is true
 * - WP_LOCAL_DEV is true
 * - WP_ENVIRONMENT_TYPE is set and not 'production'
 * - Running on localhost/127.0.0.1
 * - Running on common local dev domains (.local, .test, .dev, .localhost)
 * - Hostname resolves to 127.0.0.1 or ::1
 * - 'frost_child_is_dev_environment' filter returns true
 */
function frost_child_is_dev_environment() {
    // Check WP_DEBUG
    if (defined('WP_DEBUG') && WP_DEBUG) {
        return true;
    }

    // Check WP_LOCAL_DEV (common in local development setups)
    if (defined('WP_LOCAL_DEV') && WP_LOCAL_DEV) {
        return true;
    }

    // Check WP_ENVIRONMENT_TYPE
    if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE !== 'production') {
        return true;
    }

    // Check if running on localhost or local IP
    // Use trim() instead of sanitize_text_field() to preserve valid hostname characters including IPv6 brackets
    $server_name = isset($_SERVER['SERVER_NAME']) ? trim($_SERVER['SERVER_NAME']) : '';
    $http_host = isset($_SERVER['HTTP_HOST']) ? trim($_SERVER['HTTP_HOST']) : '';

    // Strip port from HTTP_HOST if present (e.g., "wplayground:8080" -> "wplayground")
    // For IPv6, require a well-formed bracketed literal (e.g., "[::1]:8080") and validate it.
    // Expected input: host[:port], where host is a domain, IPv4, or [IPv6]
    $http_host_clean = $http_host;

    if ($http_host) {
        // Match bracketed IPv6 with optional port, e.g., "[::1]" or "[::1]:8080"
        if (preg_match('/^\[(.+)\](?::\d+)?$/', $http_host, $matches)) {
            $ipv6_candidate = $matches[1];

            // Only treat as IPv6 if the inner value is a valid IPv6 address
            if (filter_var($ipv6_candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                // Normalize to bracketed IPv6 without port
                $http_host_clean = '[' . $ipv6_candidate . ']';
            } else {
                // Malformed bracketed host; fall back to generic port stripping
                $http_host_clean = preg_replace('/:\d+$/', '', $http_host);
            }
        } else {
            // IPv4 or domain, possibly with port (e.g., "localhost:8080")
            $http_host_clean = preg_replace('/:\d+$/', '', $http_host);
        }
    }

    // Check for localhost variants
    if (in_array($server_name, ['localhost', '127.0.0.1', '::1'], true) ||
        in_array($http_host_clean, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    // Check for common local development TLDs
    $local_tlds = ['.local', '.test', '.dev', '.localhost', '.invalid'];
    foreach ($local_tlds as $tld) {
        if (substr($server_name, -strlen($tld)) === $tld ||
            substr($http_host_clean, -strlen($tld)) === $tld) {
            return true;
        }
    }

    // Check for local IP ranges (192.168.x.x, 10.x.x.x, 172.16-31.x.x)
    if (preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/i', $server_name) ||
        preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.)/i', $http_host_clean)) {
        return true;
    }

    // Check if hostname resolves to localhost (handles custom hosts file entries)
    // Note: gethostbyname() only supports IPv4, so skip if server_name looks like an IPv6 address (contains colons)
    // SERVER_NAME with IPv6 would be the bare address without brackets (e.g., "::1" or "2001:db8::1")
    if ($server_name && !str_contains($server_name, ':')) {
        $resolved_ip = frost_child_cached_dns_lookup($server_name);
        // If resolution succeeded and points to localhost
        if ($resolved_ip !== $server_name && $resolved_ip === '127.0.0.1') {
            return true;
        }
    }

    if ($http_host_clean && $http_host_clean !== $server_name && !str_contains($http_host_clean, ':')) {
        $resolved_ip = frost_child_cached_dns_lookup($http_host_clean);
        // If resolution succeeded and points to localhost
        if ($resolved_ip !== $http_host_clean && $resolved_ip === '127.0.0.1') {
            return true;
        }
    }

    // Allow filtering for custom dev environment detection
    // Usage: add_filter('frost_child_is_dev_environment', '__return_true');
    return apply_filters('frost_child_is_dev_environment', false);
}

if (frost_child_is_dev_environment()) {
    require_once get_stylesheet_directory() . '/functions/dev-assets.php';
}

/**
 * Clear the cached Vite dev server status when themes are switched.
 *
 * This ensures we don't delete the transient on every request, but still
 * invalidate it when the theme lifecycle changes.
 *
 * @since 1.0.0
 */
function frost_child_clear_vite_dev_server_transient() {
    delete_transient('frost_child_vite_dev_server_running');
}
add_action('switch_theme', 'frost_child_clear_vite_dev_server_transient');
add_action('after_switch_theme', 'frost_child_clear_vite_dev_server_transient');

/**
 * Filter out the child theme's style.css from being loaded.
 *
 * We use Vite-compiled CSS instead of the default child theme stylesheet.
 *
 * @since 1.0.0
 * @param string $html   The link tag for the enqueued style.
 * @param string $handle The style's registered handle.
 * @param string $href   The stylesheet's source URL.
 * @param string $media  The stylesheet's media attribute.
 * @return string Empty string for child theme stylesheet, original tag otherwise.
 */
function frost_child_filter_style_tag( $html, $handle, $href, $media ) {
    if ( 'frost-child-style' === $handle ) {
        return '';
    }
    return $html;
}
add_filter( 'style_loader_tag', 'frost_child_filter_style_tag', 10, 4 );

/**
 * Enqueue parent theme styles
 *
 * Note: Child theme styles are loaded via Vite (see functions/prod-assets.php and functions/dev-assets.php)
 */
function frost_child_enqueue_parent_styles() {
    $parent_theme = wp_get_theme()->parent();

    // Enqueue parent theme stylesheet
    if ( $parent_theme ) {
        wp_enqueue_style(
            'frost-parent-style',
            get_template_directory_uri() . '/style.css',
            array(),
            $parent_theme->get('Version')
        );
    }
}
add_action('wp_enqueue_scripts', 'frost_child_enqueue_parent_styles', 5);
