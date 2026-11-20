<?php
/**
 * Fetch the pages created by setup.php and check their HTML (runs on the host, not in WordPress).
 *
 * Usage: php tests/integration/assert-frontend.php <setup.json> [<output dir for the fetched HTML>]
 *
 * @package AnchorDynamicUrl\Tests
 */

$setup_file = isset( $argv[1] ) ? $argv[1] : '';
$out_dir    = isset( $argv[2] ) ? $argv[2] : dirname( $setup_file );
$setup      = json_decode( (string) @file_get_contents( $setup_file ), true );
$failures   = 0;

if ( ! is_array( $setup ) || empty( $setup['pages'] ) ) {
	fwrite( STDERR, "Invalid setup file: $setup_file\n" );
	exit( 1 );
}

/**
 * Print a check result.
 *
 * @param string $label  Check label.
 * @param bool   $ok     Whether it passed.
 * @param string $detail Failure detail.
 */
function adu_report( $label, $ok, $detail = '' ) {
	global $failures;

	if ( $ok ) {
		echo "PASS  $label\n";
		return;
	}

	$failures++;
	echo "FAIL  $label\n" . ( '' !== $detail ? "      $detail\n" : '' );
}

if ( ! empty( $setup['versions'] ) ) {
	$v = $setup['versions'];
	printf( "WordPress %s, PHP %s, Elementor %s, Anchor Dynamic URL %s\n\n", $v['wordpress'], $v['php'], $v['elementor'], $v['plugin'] );
}

foreach ( $setup['pages'] as $name => $page ) {
	$context = stream_context_create(
		array(
			'http' => array(
				'timeout'       => 60,
				'ignore_errors' => true,
			),
		)
	);
	$html    = @file_get_contents( $page['url'], false, $context );
	$status  = isset( $http_response_header[0] ) ? $http_response_header[0] : 'no response';

	adu_report( "[$name] page responds with HTTP 200", false !== $html && false !== strpos( $status, ' 200' ), "$status ({$page['url']})" );

	if ( false === $html ) {
		continue;
	}

	file_put_contents( rtrim( $out_dir, '/\\' ) . "/$name.html", $html );

	foreach ( $page['expect'] as $label => $expectation ) {
		list( $needle, $count ) = $expectation;
		$found                  = substr_count( $html, $needle );

		adu_report( "[$name] $label", $count === $found, "expected $count x $needle, found $found" );
	}

	// The original bug: two URLs joined by a space in a single href.
	preg_match_all( '/\shref=(["\'])([^"\']*\s[^"\']*)\1/', $html, $matches );
	adu_report( "[$name] no href holding several URLs", empty( $matches[2] ), implode( ' | ', $matches[2] ) );

	foreach ( array( 'Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Parse error' ) as $php_error ) {
		adu_report( "[$name] no \"$php_error\" in the page", false === strpos( $html, $php_error ) );
	}
}

echo $failures ? "\n$failures FAILED\n" : "\nALL PASSED\n";
exit( $failures ? 1 : 0 );
