<?php
/**
 * Print the plugin version after checking that every place declaring it agrees.
 *
 * Usage: php bin/version.php
 * Prints the version (e.g. 1.5.2) on success, lists the mismatches and exits 1 otherwise.
 *
 * @package AnchorDynamicUrl\Tools
 */

$root      = dirname( __DIR__ );
$main      = (string) file_get_contents( $root . '/anchor-dynamic-url/anchor-dynamic-url.php' );
$readme    = (string) file_get_contents( $root . '/anchor-dynamic-url/readme.txt' );
$changelog = (string) file_get_contents( $root . '/CHANGELOG.md' );

$version = preg_match( '/^[ \t\/*#@]*Version:\s*(\S+)/m', $main, $match ) ? $match[1] : '';

if ( '' === $version ) {
	fwrite( STDERR, "No \"Version:\" header found in anchor-dynamic-url/anchor-dynamic-url.php\n" );
	exit( 1 );
}

$quoted = preg_quote( $version, '/' );
$checks = array(
	"ANCHOR_DYNAMIC_URL_VERSION constant is '$version'" => (bool) preg_match( "/define\(\s*'ANCHOR_DYNAMIC_URL_VERSION',\s*'$quoted'\s*\)/", $main ),
	"readme.txt \"Stable tag: $version\""                 => (bool) preg_match( "/^Stable tag:\s*$quoted\s*$/mi", $readme ),
	"readme.txt \"Version: $version\""                    => (bool) preg_match( "/^Version:\s*$quoted\s*$/mi", $readme ),
	"readme.txt changelog entry \"= $version\""          => (bool) preg_match( "/^= $quoted\b/m", $readme ),
	"CHANGELOG.md section \"## [$version]\""             => (bool) preg_match( "/^## \[$quoted\]/m", $changelog ),
);

$errors = array_keys( array_filter( $checks, static function ( $ok ) {
	return ! $ok;
} ) );

if ( $errors ) {
	fwrite( STDERR, "Version $version (plugin header) is not declared consistently. Missing:\n - " . implode( "\n - ", $errors ) . "\n" );
	exit( 1 );
}

echo $version, "\n";
