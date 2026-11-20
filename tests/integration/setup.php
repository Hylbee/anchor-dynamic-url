<?php
/**
 * Integration fixtures, executed inside WordPress Playground (see bin/test-integration.sh).
 *
 * - `setup.php?ready`: HTTP 200 once WordPress, Elementor and the plugin are loaded, 503 before.
 * - `setup.php`: creates the Elementor test page and prints, as JSON, its URL and the
 *   href values its HTML must contain (checked by assert-frontend.php).
 *
 * The Elementor data reproduces the reported bugs: a linked container (href written
 * twice) and an Icon List whose repeater links ignored their anchors.
 *
 * @package AnchorDynamicUrl\Tests
 */

require dirname( __DIR__ ) . '/wp-load.php';

header( 'Content-Type: application/json; charset=utf-8' );
nocache_headers();

$adu_loaded = defined( 'ANCHOR_DYNAMIC_URL_VERSION' ) && defined( 'ELEMENTOR_VERSION' ) && did_action( 'elementor/loaded' );

if ( isset( $_GET['ready'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	status_header( $adu_loaded ? 200 : 503 );
	echo wp_json_encode(
		array(
			'plugin'    => defined( 'ANCHOR_DYNAMIC_URL_VERSION' ) ? ANCHOR_DYNAMIC_URL_VERSION : null,
			'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : null,
			'wordpress' => get_bloginfo( 'version' ),
			'php'       => PHP_VERSION,
		)
	);
	exit;
}

if ( ! $adu_loaded ) {
	status_header( 500 );
	echo wp_json_encode(
		array(
			'error'          => 'Elementor or Anchor Dynamic URL is not loaded.',
			'plugin'         => defined( 'ANCHOR_DYNAMIC_URL_VERSION' ),
			'elementor'      => defined( 'ELEMENTOR_VERSION' ) && did_action( 'elementor/loaded' ),
			'active_plugins' => get_option( 'active_plugins' ),
		)
	);
	exit;
}

wp_set_current_user( 1 );

// Containers are active by default on new sites; make it explicit.
update_option( 'elementor_experiment-container', 'active' );

// The blueprint switches to pretty permalinks: make sure the rewrite rules follow.
flush_rewrite_rules( false );

/**
 * Create (or recreate) a published page.
 *
 * @param string $slug  Page slug.
 * @param string $title Page title.
 * @return int Page ID.
 */
function adu_test_page( $slug, $title ) {
	$existing = get_page_by_path( $slug );

	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}

	return (int) wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => $slug,
			'post_title'  => $title,
		)
	);
}

/**
 * Elementor URL control value.
 *
 * @param string $url    URL.
 * @param string $anchor Anchor (anchor_target field).
 * @return array
 */
function adu_test_link( $url, $anchor = '' ) {
	return array(
		'url'               => $url,
		'is_external'       => '',
		'nofollow'          => '',
		'custom_attributes' => '',
		'anchor_target'     => $anchor,
	);
}

$adu_tarifs   = get_permalink( adu_test_page( 'adu-tarifs', 'Tarifs' ) );
$adu_domaines = get_permalink( adu_test_page( 'adu-domaines', 'Domaines' ) );
$adu_query    = 'https://example.org/page/?a=1&b=2';

$adu_data = array(
	// Linked container (HTML tag "a") with an anchor, holding a child link to the same URL.
	array(
		'id'       => 'adu0c01',
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => array(
			'html_tag' => 'a',
			'link'     => adu_test_link( $adu_domaines, 'immobilier' ),
		),
		'elements' => array(
			array(
				'id'         => 'adu0w01',
				'elType'     => 'widget',
				'widgetType' => 'button',
				'settings'   => array(
					'text' => 'Child link',
					'link' => adu_test_link( $adu_domaines ),
				),
				'elements'   => array(),
			),
		),
	),
	array(
		'id'       => 'adu0c02',
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => array(),
		'elements' => array(
			// Icon List: links stored in repeater items, two of them sharing a URL.
			array(
				'id'         => 'adu0w02',
				'elType'     => 'widget',
				'widgetType' => 'icon-list',
				'settings'   => array(
					'icon_list' => array(
						array(
							'_id'  => 'adu0i01',
							'text' => 'Brochure',
							'link' => adu_test_link( 'https://example.org/brochure.pdf' ),
						),
						array(
							'_id'  => 'adu0i02',
							'text' => 'Internat',
							'link' => adu_test_link( $adu_tarifs, 'internat' ),
						),
						array(
							'_id'  => 'adu0i03',
							'text' => 'Externat',
							'link' => adu_test_link( $adu_tarifs, 'externat' ),
						),
					),
				),
				'elements'   => array(),
			),
			// Button whose URL has a query string (escaped as &#038; by esc_url()).
			array(
				'id'         => 'adu0w03',
				'elType'     => 'widget',
				'widgetType' => 'button',
				'settings'   => array(
					'text' => 'Query',
					'link' => adu_test_link( $adu_query, 'top' ),
				),
				'elements'   => array(),
			),
		),
	),
);

$adu_page = adu_test_page( 'adu-elementor', 'Elementor anchors' );

update_post_meta( $adu_page, '_elementor_edit_mode', 'builder' );
update_post_meta( $adu_page, '_elementor_template_type', 'wp-page' );
update_post_meta( $adu_page, '_elementor_version', ELEMENTOR_VERSION );
update_post_meta( $adu_page, '_wp_page_template', 'elementor_canvas' );
update_post_meta( $adu_page, '_elementor_data', wp_slash( wp_json_encode( $adu_data ) ) );

if ( isset( \Elementor\Plugin::$instance->files_manager ) ) {
	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

/**
 * Expected `href="..."` attribute, as printed in the HTML.
 *
 * @param string $value Attribute value (already HTML-escaped where needed).
 * @return string
 */
function adu_test_href( $value ) {
	return 'href="' . $value . '"';
}

echo wp_json_encode(
	array(
		'versions' => array(
			'plugin'    => ANCHOR_DYNAMIC_URL_VERSION,
			'elementor' => ELEMENTOR_VERSION,
			'wordpress' => get_bloginfo( 'version' ),
			'php'       => PHP_VERSION,
		),
		'pages'    => array(
			'elementor' => array(
				'url'    => get_permalink( $adu_page ),
				// Label => array( needle, exact number of occurrences ).
				'expect' => array(
					'Linked container: href with anchor, written once'  => array( adu_test_href( esc_attr( $adu_domaines . '#immobilier' ) ), 1 ),
					'Linked container: child link left untouched'       => array( adu_test_href( esc_attr( $adu_domaines ) ), 1 ),
					'Icon List: repeater item gets its anchor'          => array( adu_test_href( esc_attr( $adu_tarifs . '#internat' ) ), 1 ),
					'Icon List: same URL, other anchor'                 => array( adu_test_href( esc_attr( $adu_tarifs . '#externat' ) ), 1 ),
					'Icon List: item without anchor left untouched'     => array( adu_test_href( 'https://example.org/brochure.pdf' ), 1 ),
					'Button: escaped query string kept, anchor added'   => array( adu_test_href( 'https://example.org/page/?a=1&amp;b=2#top' ), 1 ),
				),
			),
		),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
