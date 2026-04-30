<?php
/**
 * Utility class for anchor sanitization.
 *
 * Provides secure sanitization of anchor strings to prevent XSS and ensure valid HTML/CSS identifiers.
 *
 * @package AnchorDynamicUrl\Utils
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Utils;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AnchorSanitizer class.
 */
class AnchorSanitizer {

	/**
	 * Sanitize anchor to be URL-safe and secure.
	 *
	 * Converts user input into a safe anchor identifier for use in URLs and HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $anchor Raw anchor input from user.
	 * @return string|null Sanitized anchor or null if invalid/empty.
	 */
	public static function sanitize( $anchor ) {
		if ( empty( $anchor ) ) {
			return null;
		}

		$anchor = trim( $anchor );
		$anchor = preg_split( '/[?\/\\\\]/', $anchor )[0];

		$anchor = str_replace( array( ' ', "\t", "\n", "\r" ), '-', $anchor );

		$anchor = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $anchor );

		$anchor = preg_replace( '/-+/', '-', $anchor );

		$anchor = trim( $anchor, '-' );

		return ! empty( $anchor ) ? $anchor : null;
	}
}