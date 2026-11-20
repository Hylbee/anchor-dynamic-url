<?php
/**
 * Build the plugin ZIP published with each release.
 *
 * The archive contains the anchor-dynamic-url/ folder at its root, as WordPress
 * expects. Versions up to 1.5.1 download a release asset named exactly
 * "anchor-dynamic-url.zip" when updating, so keep that file name.
 *
 * Usage: php bin/build-zip.php [output.zip]   (default: dist/anchor-dynamic-url.zip)
 *
 * @package AnchorDynamicUrl\Tools
 */

$root   = dirname( __DIR__ );
$folder = 'anchor-dynamic-url';
$source = $root . '/' . $folder;
$output = isset( $argv[1] ) ? $argv[1] : $root . '/dist/anchor-dynamic-url.zip';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" );
	exit( 1 );
}

if ( ! is_file( $source . '/anchor-dynamic-url.php' ) ) {
	fwrite( STDERR, "Plugin folder not found: $source\n" );
	exit( 1 );
}

$files    = array();
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ( $iterator as $file ) {
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $source ) + 1 ) );

	// Skip hidden and OS files (.DS_Store, Thumbs.db...): only plugin files are shipped.
	if ( preg_match( '#(^|/)\.|(^|/)Thumbs\.db$#i', $relative ) ) {
		continue;
	}

	$files[ $relative ] = $file->getPathname();
}

ksort( $files );

if ( ! is_dir( dirname( $output ) ) && ! mkdir( dirname( $output ), 0777, true ) ) {
	fwrite( STDERR, 'Cannot create ' . dirname( $output ) . "\n" );
	exit( 1 );
}

if ( is_file( $output ) ) {
	unlink( $output );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $output, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create $output\n" );
	exit( 1 );
}

$zip->addEmptyDir( $folder );

foreach ( $files as $relative => $path ) {
	$zip->addFile( $path, $folder . '/' . $relative );
}

$zip->close();

printf( "Built %s (%d files, %s KB)\n", $output, count( $files ), number_format( filesize( $output ) / 1024, 1 ) );
