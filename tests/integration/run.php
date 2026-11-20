<?php
/**
 * Integration tests executed inside WordPress Playground (see bin/test-integration.sh).
 *
 * Prints one PASS/FAIL line per check, then "ALL PASSED" (HTTP 200) or
 * "N FAILED" (HTTP 500). GitHub is never contacted: its API is simulated
 * through the `pre_http_request` filter and every other external request is blocked.
 *
 * Expects the release ZIP under test at /wordpress/adu-dist/anchor-dynamic-url.zip.
 *
 * @package AnchorDynamicUrl\Tests
 */

require dirname( __DIR__ ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

ob_start();
nocache_headers();
header( 'Content-Type: text/plain; charset=utf-8' );
wp_set_current_user( 1 );

$adu_failures   = 0;
$adu_php_errors = array();
$adu_plugin     = 'anchor-dynamic-url/anchor-dynamic-url.php';
$adu_zip        = ABSPATH . 'adu-dist/anchor-dynamic-url.zip';

/**
 * Compare a value and print the result.
 *
 * @param string $label    Check label.
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 */
function adu_check( $label, $expected, $actual ) {
	global $adu_failures;

	if ( $expected === $actual ) {
		echo "PASS  $label\n";
		return;
	}

	$adu_failures++;
	echo "FAIL  $label\n      expected: " . var_export( $expected, true ) . "\n      actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * Build a fake HTTP response for `pre_http_request`.
 *
 * @param int    $code HTTP status.
 * @param string $body Response body.
 * @return array
 */
function adu_http_response( $code, $body ) {
	return array(
		'headers'  => array(),
		'body'     => $body,
		'response' => array(
			'code'    => $code,
			'message' => 200 === $code ? 'OK' : 'Error',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}

// Record PHP warnings/notices raised by the plugin while the tests run.
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
	static function ( $errno, $errstr, $errfile, $errline ) use ( &$adu_php_errors ) {
		if ( false !== strpos( wp_normalize_path( $errfile ), '/plugins/anchor-dynamic-url' ) ) {
			$adu_php_errors[] = "$errstr in $errfile:$errline";
		}
		return false;
	}
);

printf( "WordPress %s, PHP %s, Elementor %s\n\n", get_bloginfo( 'version' ), PHP_VERSION, defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'not loaded' );

// -----------------------------------------------------------------------------
echo "== Plugin\n";

adu_check( 'plugin is active', true, is_plugin_active( $adu_plugin ) );
adu_check(
	'ANCHOR_DYNAMIC_URL_VERSION matches the plugin header',
	get_plugin_data( WP_PLUGIN_DIR . '/' . $adu_plugin, false, false )['Version'],
	defined( 'ANCHOR_DYNAMIC_URL_VERSION' ) ? ANCHOR_DYNAMIC_URL_VERSION : null
);

// -----------------------------------------------------------------------------
echo "\n== Menu anchors\n";

$adu_page_id = (int) wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Contact',
		'post_name'   => 'adu-contact-' . wp_generate_password( 6, false, false ),
	)
);
$adu_menu_id = wp_create_nav_menu( 'ADU test ' . wp_generate_password( 6, false, false ) );
$adu_item_id = wp_update_nav_menu_item(
	$adu_menu_id,
	0,
	array(
		'menu-item-title'     => 'Contact',
		'menu-item-object'    => 'page',
		'menu-item-object-id' => $adu_page_id,
		'menu-item-type'      => 'post_type',
		'menu-item-status'    => 'publish',
	)
);
update_post_meta( $adu_item_id, '_menu_item_anchor', 'coordonnees' );

/**
 * Render the test menu.
 *
 * @param int $menu_id Menu ID.
 * @return string
 */
function adu_render_menu( $menu_id ) {
	return (string) wp_nav_menu(
		array(
			'menu'        => $menu_id,
			'echo'        => false,
			'fallback_cb' => false,
			'container'   => false,
		)
	);
}

adu_check(
	'menu item links to page#anchor',
	1,
	substr_count( adu_render_menu( $adu_menu_id ), 'href="' . esc_url( get_permalink( $adu_page_id ) . '#coordonnees' ) . '"' )
);

wp_update_post(
	array(
		'ID'        => $adu_page_id,
		'post_name' => 'adu-contact-renamed-' . wp_generate_password( 6, false, false ),
	)
);
clean_post_cache( $adu_page_id );

adu_check(
	'menu URL follows a page slug change',
	1,
	substr_count( adu_render_menu( $adu_menu_id ), 'href="' . esc_url( get_permalink( $adu_page_id ) . '#coordonnees' ) . '"' )
);

wp_delete_nav_menu( $adu_menu_id );
wp_delete_post( $adu_page_id, true );

// -----------------------------------------------------------------------------
echo "\n== Updater (GitHub simulated)\n";

adu_check( 'release ZIP under test is available', true, is_file( $adu_zip ) );

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( $adu_zip ) {
		$api = 'https://api.github.com/repos/Hylbee/anchor-dynamic-url/releases';

		if ( 0 === strpos( $url, $api . '/latest' ) ) {
			return adu_http_response(
				200,
				wp_json_encode(
					array(
						'tag_name'     => 'v9.9.9',
						'published_at' => '2026-01-01T10:00:00Z',
						'assets'       => array(
							array(
								'name'                 => 'anchor-dynamic-url.zip',
								'browser_download_url' => $adu_zip, // Local path: WordPress installs it without downloading.
							),
						),
						'zipball_url'  => 'https://api.github.com/repos/Hylbee/anchor-dynamic-url/zipball/v9.9.9',
					)
				)
			);
		}

		if ( 0 === strpos( $url, $api ) ) {
			return adu_http_response(
				200,
				wp_json_encode(
					array(
						array(
							'tag_name'     => 'v9.9.9',
							'published_at' => '2026-01-01T10:00:00Z',
							'body'         => "### Fixed\n- Simulated release",
						),
					)
				)
			);
		}

		if ( 'https://raw.githubusercontent.com/Hylbee/anchor-dynamic-url/v9.9.9/anchor-dynamic-url/readme.txt' === $url ) {
			return adu_http_response( 200, "=== Anchor Dynamic URL ===\nRequires at least: 5.3\nTested up to: 7.1\nRequires PHP: 7.4\n" );
		}

		return new WP_Error( 'adu_blocked', 'External request blocked during tests: ' . $url );
	},
	10,
	3
);

// Keep upgrades self-contained (no update check or translation download afterwards).
remove_all_actions( 'upgrader_process_complete' );

anchor_dynamic_url_clear_update_cache();
set_site_transient(
	'update_plugins',
	(object) array(
		'last_checked' => time(),
		'checked'      => array( $adu_plugin => ANCHOR_DYNAMIC_URL_VERSION ),
		'response'     => array(),
		'no_update'    => array(),
		'translations' => array(),
	)
);

$adu_updates = get_site_transient( 'update_plugins' );
$adu_update  = isset( $adu_updates->response[ $adu_plugin ] ) ? $adu_updates->response[ $adu_plugin ] : null;

adu_check( 'update to v9.9.9 offered', '9.9.9', $adu_update ? $adu_update->new_version : null );
adu_check( 'package is the release asset', $adu_zip, $adu_update ? $adu_update->package : null );
adu_check( 'requires read from the release readme.txt', '5.3', $adu_update ? $adu_update->requires : null );
adu_check( 'requires_php read from the release readme.txt', '7.4', $adu_update ? $adu_update->requires_php : null );
adu_check( 'id is the Update URI', 'https://github.com/Hylbee/anchor-dynamic-url', $adu_update ? $adu_update->id : null );

$adu_info = plugins_api( 'plugin_information', array( 'slug' => 'anchor-dynamic-url' ) );
$adu_info = is_wp_error( $adu_info ) ? (object) array( 'version' => $adu_info->get_error_message(), 'sections' => array() ) : $adu_info;

adu_check( '"View details": version', '9.9.9', $adu_info->version );
adu_check( '"View details": changelog lists the release', true, false !== strpos( (string) ( $adu_info->sections['changelog'] ?? '' ), 'Version 9.9.9' ) );
adu_check( '"View details": Support tab links to GitHub issues', true, false !== strpos( (string) ( $adu_info->sections['support'] ?? '' ), 'https://github.com/Hylbee/anchor-dynamic-url/issues' ) );

// -----------------------------------------------------------------------------
echo "\n== Update end to end (plugin installed in a non-standard folder)\n";

WP_Filesystem();

$adu_odd_slug = 'anchor-dynamic-url-aIPGWyMNsUTyyBKb';
$adu_odd_dir  = WP_PLUGIN_DIR . '/' . $adu_odd_slug;
$adu_odd_file = $adu_odd_slug . '/anchor-dynamic-url.php';
$adu_tmp_dir  = WP_CONTENT_DIR . '/adu-tmp';

$wp_filesystem->delete( $adu_tmp_dir, true );
$wp_filesystem->delete( $adu_odd_dir, true );

adu_check( 'release ZIP unzips', true, unzip_file( $adu_zip, $adu_tmp_dir ) );
adu_check( 'release ZIP has anchor-dynamic-url/ at its root', true, is_file( $adu_tmp_dir . '/anchor-dynamic-url/anchor-dynamic-url.php' ) );

// Install a copy in the odd folder and make it look older, so an update is offered for it.
$wp_filesystem->move( $adu_tmp_dir . '/anchor-dynamic-url', $adu_odd_dir );
$wp_filesystem->delete( $adu_tmp_dir, true );
$wp_filesystem->put_contents(
	$adu_odd_dir . '/anchor-dynamic-url.php',
	preg_replace( '/^([ \t\/*#@]*Version:\s*)\S+/m', '${1}1.0.0', (string) $wp_filesystem->get_contents( $adu_odd_dir . '/anchor-dynamic-url.php' ), 1 )
);

// The updater of that copy, as if it were the running one.
new \AnchorDynamicUrl\Admin\AnchorDynamicUrlUpdater( $adu_odd_dir . '/anchor-dynamic-url.php', '1.0.0' );

set_site_transient(
	'update_plugins',
	(object) array(
		'last_checked' => time(),
		'checked'      => array( $adu_odd_file => '1.0.0' ),
		'response'     => array(),
		'no_update'    => array(),
		'translations' => array(),
	)
);

$adu_upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$adu_result   = $adu_upgrader->upgrade( $adu_odd_file );

adu_check( 'update installed', true, is_wp_error( $adu_result ) ? $adu_result->get_error_message() : $adu_result );
adu_check( 'installed folder name kept (plugin stays active)', true, is_file( $adu_odd_dir . '/anchor-dynamic-url.php' ) );
adu_check(
	'new files installed in that folder',
	ANCHOR_DYNAMIC_URL_VERSION,
	is_file( $adu_odd_dir . '/anchor-dynamic-url.php' ) ? get_plugin_data( $adu_odd_dir . '/anchor-dynamic-url.php', false, false )['Version'] : null
);
adu_check( 'running plugin folder untouched', true, is_file( WP_PLUGIN_DIR . '/' . $adu_plugin ) );
adu_check(
	'no other anchor-dynamic-url* folder created',
	array(),
	array_values( array_diff( (array) glob( WP_PLUGIN_DIR . '/anchor-dynamic-url*', GLOB_ONLYDIR ), array( WP_PLUGIN_DIR . '/anchor-dynamic-url', $adu_odd_dir ) ) )
);

$wp_filesystem->delete( $adu_odd_dir, true );

// -----------------------------------------------------------------------------
echo "\n== PHP errors\n";

restore_error_handler();
adu_check( 'no PHP warning/notice from the plugin during these tests', array(), $adu_php_errors );

// Includes plugin activation and the frontend pages rendered by assert-frontend.php.
$adu_log = WP_CONTENT_DIR . '/debug.log';
adu_check(
	'no plugin entry in wp-content/debug.log',
	array(),
	is_file( $adu_log ) ? array_values( preg_grep( '/anchor-dynamic-url/', (array) file( $adu_log, FILE_IGNORE_NEW_LINES ) ) ) : array()
);

echo $adu_failures ? "\n$adu_failures FAILED\n" : "\nALL PASSED\n";

status_header( $adu_failures ? 500 : 200 );
ob_end_flush();
