<?php
/**
 * SVG Upload Support
 *
 * @package    frost_child
 * @subpackage Functions
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use enshrined\svgSanitize\Sanitizer;

/**
 * Enable SVG uploads
 *
 * Allows SVG files to be uploaded to the media library.
 * Note: SVG files are sanitized on upload for security.
 *
 * @param array $mimes Allowed MIME types.
 * @return array Modified MIME types array.
 */
function frost_child_allow_svg_uploads( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';

	return $mimes;
}
add_filter( 'upload_mimes', 'frost_child_allow_svg_uploads' );

/**
 * Check SVG file type
 *
 * WordPress file type checking for SVG uploads.
 *
 * @param array  $wp_check_filetype_and_ext File data array.
 * @param string $file Full path to the file.
 * @param string $filename The name of the file.
 * @param array  $mimes Array of allowed MIME types.
 * @return array Modified file data array.
 */
function frost_child_check_svg_filetype( $wp_check_filetype_and_ext, $file, $filename, $mimes ) {
	if ( ! $wp_check_filetype_and_ext['type'] ) {
		$check_filetype  = wp_check_filetype( $filename, $mimes );
		$ext             = $check_filetype['ext'];
		$type            = $check_filetype['type'];
		$proper_filename = $filename;

		if ( $type && 0 === strpos( $type, 'image/' ) && 'svg' === $ext ) {
			$wp_check_filetype_and_ext['ext']             = $ext;
			$wp_check_filetype_and_ext['type']            = $type;
			$wp_check_filetype_and_ext['proper_filename'] = $proper_filename;
		}
	}

	return $wp_check_filetype_and_ext;
}
add_filter( 'wp_check_filetype_and_ext', 'frost_child_check_svg_filetype', 10, 4 );

/**
 * Sanitize SVG uploads
 *
 * Uses the enshrined/svg-sanitize library for robust SVG sanitization.
 *
 * @param array $file The uploaded file array.
 * @return array The file array.
 */
function frost_child_sanitize_svg_upload( $file ) {
	// Only process SVG files
	if ( ! isset( $file['type'] ) || 0 !== strpos( $file['type'], 'image/svg' ) ) {
		return $file;
	}

	// Read file contents
	$svg_content = file_get_contents( $file['tmp_name'] );

	if ( false === $svg_content ) {
		$file['error'] = __( 'Unable to read SVG file.', 'frost_child' );
		return $file;
	}

	// Sanitize using enshrined/svg-sanitize library
	$sanitizer = new Sanitizer();
	$sanitized_svg = $sanitizer->sanitize( $svg_content );

	if ( false === $sanitized_svg ) {
		$file['error'] = __( 'Invalid or potentially malicious SVG file.', 'frost_child' );
		return $file;
	}

	// Write sanitized content back
	$bytes_written = file_put_contents( $file['tmp_name'], $sanitized_svg );

	if ( false === $bytes_written ) {
		$file['error'] = __( 'Unable to save sanitized SVG file.', 'frost_child' );
		return $file;
	}

	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'frost_child_sanitize_svg_upload' );

/**
 * Display SVG thumbnails in media library
 *
 * @param string $response The response data.
 * @param object $attachment The attachment object.
 * @return string Modified response data.
 */
function frost_child_svg_media_thumbnails( $response, $attachment ) {
	if ( 'image/svg+xml' === $response['mime'] ) {
		$response['image'] = array(
			'src' => $response['url'],
		);
	}

	return $response;
}
add_filter( 'wp_prepare_attachment_for_js', 'frost_child_svg_media_thumbnails', 10, 2 );
