<?php
/**
 * Print the CHANGELOG.md section of a version, used as GitHub release notes.
 *
 * Usage: php bin/changelog.php 1.5.2
 *
 * @package AnchorDynamicUrl\Tools
 */

$version = isset( $argv[1] ) ? $argv[1] : '';
$text    = (string) file_get_contents( dirname( __DIR__ ) . '/CHANGELOG.md' );

// A section runs from its "## [x.y.z]" heading to the next "## [" heading or "---" separator.
$pattern = '/^## \[' . preg_quote( $version, '/' ) . '\][^\n]*\n(.*?)(?=^## \[|^---\s*$|\z)/ms';

if ( '' === $version || ! preg_match( $pattern, $text, $match ) || '' === trim( $match[1] ) ) {
	fwrite( STDERR, "No CHANGELOG.md section found for version \"$version\".\n" );
	exit( 1 );
}

echo trim( $match[1] ), "\n";
