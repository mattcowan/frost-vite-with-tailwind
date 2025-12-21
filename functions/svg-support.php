<?php
/**
 * SVG Upload Support
 *
 * @package    frostvite
 * @subpackage Functions
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enable SVG uploads
 *
 * Allows SVG files to be uploaded to the media library.
 * Note: SVG files are sanitized on upload for security.
 *
 * @param array $mimes Allowed MIME types.
 * @return array Modified MIME types array.
 */
function frostvite_allow_svg_uploads( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';

	return $mimes;
}
add_filter( 'upload_mimes', 'frostvite_allow_svg_uploads' );

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
function frostvite_check_svg_filetype( $wp_check_filetype_and_ext, $file, $filename, $mimes ) {
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
add_filter( 'wp_check_filetype_and_ext', 'frostvite_check_svg_filetype', 10, 4 );

/**
 * Sanitize SVG uploads
 *
 * Proper SVG sanitization using DOMDocument to remove potentially malicious code.
 * Uses XML parsing instead of regex to prevent bypass vulnerabilities.
 *
 * @param array $file The uploaded file array.
 * @return array The file array.
 */
function frostvite_sanitize_svg_upload( $file ) {
	// Only process SVG files
	if ( 'image/svg+xml' !== $file['type'] ) {
		return $file;
	}

	// Read file contents
	$svg_content = file_get_contents( $file['tmp_name'] );

	if ( false === $svg_content ) {
		$file['error'] = __( 'Unable to read SVG file.', 'frostvite' );
		return $file;
	}

	// Use DOMDocument for proper XML parsing
	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput       = true;

	// Load SVG content
	if ( ! $dom->loadXML( $svg_content, LIBXML_NOENT | LIBXML_DTDLOAD ) ) {
		$file['error'] = __( 'Invalid SVG file format.', 'frostvite' );
		libxml_clear_errors();
		return $file;
	}

	// Dangerous tags to remove
	$dangerous_tags = array( 'script', 'embed', 'object', 'iframe', 'frame', 'foreignobject' );

	// Dangerous attributes to remove (event handlers and external references)
	$dangerous_attributes = array(
		'onload',
		'onerror',
		'onclick',
		'onmouseover',
		'onmouseout',
		'onmousemove',
		'onfocus',
		'onblur',
		'onchange',
		'onsubmit',
		'onkeydown',
		'onkeyup',
		'onkeypress',
	);

	// Remove dangerous tags using XPath
	$xpath = new DOMXPath( $dom );
	foreach ( $dangerous_tags as $tag ) {
		$nodes = $xpath->query( '//' . $tag );
		if ( $nodes instanceof DOMNodeList ) {
			// Iterate over a static list to avoid issues while removing nodes
			for ( $i = $nodes->length - 1; $i >= 0; $i-- ) {
				$node = $nodes->item( $i );
				if ( $node && $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
	}

	// Scan all elements for dangerous attributes
	$elements = $xpath->query( '//*' );

	foreach ( $elements as $element ) {
		// Remove dangerous attributes
		foreach ( $dangerous_attributes as $attr ) {
			if ( $element->hasAttribute( $attr ) ) {
				$element->removeAttribute( $attr );
			}
		}

		// Check href and xlink:href for javascript: protocol
		if ( $element->hasAttribute( 'href' ) ) {
			$href = $element->getAttribute( 'href' );
			if ( preg_match( '/^\s*javascript:/i', $href ) ) {
				$element->removeAttribute( 'href' );
			}
		}

		if ( $element->hasAttribute( 'xlink:href' ) ) {
			$href = $element->getAttribute( 'xlink:href' );
			if ( preg_match( '/^\s*javascript:/i', $href ) ) {
				$element->removeAttribute( 'xlink:href' );
			}
		}
	}

	// Save sanitized SVG
	$sanitized_svg = $dom->saveXML();

	if ( false === $sanitized_svg ) {
		$file['error'] = __( 'Failed to sanitize SVG file.', 'frostvite' );
		libxml_clear_errors();
		return $file;
	}

	// Write sanitized content back
	file_put_contents( $file['tmp_name'], $sanitized_svg );

	libxml_clear_errors();

	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'frostvite_sanitize_svg_upload' );

/**
 * Display SVG thumbnails in media library
 *
 * @param string $response The response data.
 * @param object $attachment The attachment object.
 * @return string Modified response data.
 */
function frostvite_svg_media_thumbnails( $response, $attachment ) {
	if ( 'image/svg+xml' === $response['mime'] ) {
		$response['image'] = array(
			'src' => $response['url'],
		);
	}

	return $response;
}
add_filter( 'wp_prepare_attachment_for_js', 'frostvite_svg_media_thumbnails', 10, 2 );
