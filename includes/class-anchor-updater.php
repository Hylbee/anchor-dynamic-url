<?php
/**
 * GitHub Updater Class - Handles automatic updates from GitHub releases.
 *
 * This class provides automatic plugin updates by checking GitHub releases
 * and integrating with WordPress's built-in update system.
 *
 * @package AnchorDynamicUrl\Admin
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Admin;

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub Updater Class - Handles automatic updates from GitHub releases.
 *
 * This class provides automatic plugin updates by checking GitHub releases
 * and integrating with WordPress's built-in update system.
 *
 * @since 1.0.0
 */
class AnchorDynamicUrlUpdater {

	/**
	 * Plugin basename (folder/file.php).
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin directory name.
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * GitHub repository identifier.
	 *
	 * @var string
	 */
	private $github_repo = 'Hylbee/anchor-dynamic-url';

	/**
	 * WordPress plugin header data.
	 *
	 * @var array
	 */
	private $plugin_data;

	/**
	 * Initialize the updater with plugin information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Full path to the main plugin file.
	 * @param string $version Current plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_file = plugin_basename( $plugin_file );
		$this->plugin_slug = dirname( $this->plugin_file );
		$this->version     = $version;

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$this->plugin_data = get_plugin_data( $plugin_file );

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks for update system.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
		add_action( 'admin_init', array( $this, 'show_upgrade_notification' ) );
	}

	/**
	 * Check for plugin updates by comparing local and remote versions.
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient The update transient object from WordPress.
	 * @return object Modified transient with our plugin update info if available.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote_version = $this->get_remote_version();

		if ( version_compare( $this->version, $remote_version, '<' ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'slug'           => $this->plugin_slug,
				'plugin'         => $this->plugin_file,
				'new_version'    => $remote_version,
				'url'            => 'https://github.com/' . $this->github_repo,
				'package'        => $this->get_download_url( $remote_version ),
				'icons'          => array(),
				'banners'        => array(),
				'banners_rtl'    => array(),
				'tested'         => isset( $this->plugin_data['RequiresWP'] ) ? $this->plugin_data['RequiresWP'] : '6.4',
				'requires_php'   => isset( $this->plugin_data['RequiresPHP'] ) ? $this->plugin_data['RequiresPHP'] : '7.4',
				'compatibility'  => new stdClass(),
			);
		}

		return $transient;
	}

	/**
	 * Get remote version from GitHub API with caching.
	 *
	 * @since 1.0.0
	 *
	 * @return string The remote version number, or current version if fetch fails.
	 */
	private function get_remote_version() {
		$transient_key  = 'anchor_dynamic_url_remote_version';
		$cached_version = get_transient( $transient_key );

		if ( false !== $cached_version ) {
			return $cached_version;
		}

		$request = wp_remote_get(
			'https://api.github.com/repos/' . $this->github_repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$body = wp_remote_retrieve_body( $request );
			$data = json_decode( $body, true );

			if ( isset( $data['tag_name'] ) ) {
				$version = ltrim( $data['tag_name'], 'v' );
				set_transient( $transient_key, $version, 12 * HOUR_IN_SECONDS );
				return $version;
			}
		}

		return $this->version;
	}

	/**
	 * Get download URL for specific version from GitHub releases.
	 *
	 * @since 1.0.0
	 *
	 * @param string $version The version to download.
	 * @return string Direct download URL for the plugin ZIP.
	 */
	private function get_download_url( $version ) {
		return 'https://github.com/' . $this->github_repo . '/releases/download/v' . $version . '/anchor-dynamic-url.zip';
	}

	/**
	 * Handle plugin information popup.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $result The result object or array.
	 * @param string $action The type of information being requested.
	 * @param object $args Arguments for the request.
	 * @return mixed Modified result or original result if not our plugin.
	 */
	public function plugin_popup( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$remote_version = $this->get_remote_version();
		$changelog = $this->get_remote_changelog();

		return (object) array(
			'name' => $this->plugin_data['Name'],
			'slug' => $this->plugin_slug,
			'version' => $remote_version,
			'author' => $this->plugin_data['Author'],
			'author_profile' => $this->plugin_data['AuthorURI'],
			'requires' => isset( $this->plugin_data['RequiresWP'] ) ? $this->plugin_data['RequiresWP'] : '5.0',
			'tested' => isset( $this->plugin_data['Tested'] ) ? $this->plugin_data['Tested'] : '6.4',
			'requires_php' => isset( $this->plugin_data['RequiresPHP'] ) ? $this->plugin_data['RequiresPHP'] : '7.4',
			'sections' => array(
				'description' => $this->plugin_data['Description'],
				'changelog' => $changelog,
			),
			'homepage' => 'https://github.com/' . $this->github_repo,
			'download_link' => $this->get_download_url( $remote_version ),
			'trunk' => $this->get_download_url( $remote_version ),
		);
	}

	/**
	 * Get remote changelog from GitHub, cached for 12 hours.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted changelog HTML.
	 */
	private function get_remote_changelog() {
		$transient_key = 'anchor_dynamic_url_changelog';
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$request = wp_remote_get(
			'https://api.github.com/repos/' . $this->github_repo . '/releases',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
				),
			)
		);

		if ( is_wp_error( $request ) ) {
			/* translators: Error message when changelog cannot be fetched from GitHub */
			return __( 'Unable to fetch changelog.', 'anchor-dynamic-url' );
		}

		$body     = wp_remote_retrieve_body( $request );
		$releases = json_decode( $body, true );

		if ( ! is_array( $releases ) ) {
			/* translators: Message shown when no changelog data is available */
			return __( 'No changelog available.', 'anchor-dynamic-url' );
		}

		$changelog = '<div class="menu-anchor-changelog">';

		foreach ( array_slice( $releases, 0, 5 ) as $release ) {
			$version      = ltrim( $release['tag_name'], 'v' );
			$date         = wp_date( 'F j, Y', strtotime( $release['published_at'] ) );
			/* translators: Message shown when a release has no notes */
			$release_body = $release['body'] ? wp_kses_post( $release['body'] ) : __( 'No release notes available.', 'anchor-dynamic-url' );

			$changelog .= '<h4>' . sprintf(
				/* translators: 1: version number, 2: release date */
				esc_html__( 'Version %1$s - %2$s', 'anchor-dynamic-url' ),
				esc_html( $version ),
				esc_html( $date )
			) . '</h4>';
			$changelog .= '<div>' . wp_kses_post( $release_body ) . '</div>';
		}

		$changelog .= '</div>';

		set_transient( $transient_key, $changelog, 12 * HOUR_IN_SECONDS );

		return $changelog;
	}

	/**
	 * Show upgrade notification in admin.
	 *
	 * @since 1.0.0
	 */
	public function show_upgrade_notification() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$remote_version = $this->get_remote_version();

		if ( version_compare( $this->version, $remote_version, '<' ) ) {
			$message = sprintf(
				/* translators: 1: version number, 2: update URL */
				__( 'Anchor Dynamic URL version %1$s is available. <a href="%2$s">Update now</a>.', 'anchor-dynamic-url' ),
				$remote_version,
				wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $this->plugin_file ), 'upgrade-plugin_' . $this->plugin_file )
			);

			echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
		}
	}
}