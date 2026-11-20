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
 * - Reads the latest GitHub release (version, ZIP asset, readme.txt headers).
 * - Feeds WordPress's update transient (update available or up to date).
 * - Provides the "View details" popup content (description, changelog, support).
 * - Keeps the installed folder name when the update package is unpacked, so
 *   the plugin stays active whatever the ZIP layout.
 *
 * @since 1.0.0
 */
class AnchorDynamicUrlUpdater {

	/**
	 * Transient holding the latest release data.
	 *
	 * @since 1.5.2
	 */
	const RELEASE_CACHE_KEY = 'anchor_dynamic_url_release';

	/**
	 * Transient holding the formatted changelog.
	 *
	 * @since 1.5.2
	 */
	const CHANGELOG_CACHE_KEY = 'anchor_dynamic_url_changelog';

	/**
	 * Support page (GitHub issues).
	 *
	 * @since 1.5.2
	 */
	const SUPPORT_URL = 'https://github.com/Hylbee/anchor-dynamic-url/issues';

	/**
	 * Plugin basename (folder/file.php).
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Full path to the main plugin file.
	 *
	 * @var string
	 */
	private $plugin_path;

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
	 * WordPress plugin header data, loaded on demand.
	 *
	 * @var array|null
	 */
	private $plugin_data = null;

	/**
	 * Latest release for the current request: false = not fetched yet, null = unavailable.
	 *
	 * @var array|null|false
	 */
	private $release = false;

	/**
	 * Initialize the updater with plugin information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Full path to the main plugin file.
	 * @param string $version Current plugin version.
	 */
	public function __construct( $plugin_file, $version ) {
		$this->plugin_path = $plugin_file;
		$this->plugin_file = plugin_basename( $plugin_file );
		$this->plugin_slug = dirname( $this->plugin_file );
		$this->version     = $version;

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
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_folder' ), 10, 4 );
	}

	/**
	 * Add this plugin's update status to WordPress's update transient.
	 *
	 * The plugin is listed in `response` when a newer release exists, and in
	 * `no_update` otherwise (which enables the auto-update toggle and the
	 * "View details" link in the plugins list).
	 *
	 * @since 1.0.0
	 *
	 * @param object $transient The update transient object from WordPress.
	 * @return object Modified transient with our plugin update info if available.
	 */
	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$item = (object) array(
			'id'            => 'https://github.com/' . $this->github_repo,
			'slug'          => $this->plugin_slug,
			'plugin'        => $this->plugin_file,
			'new_version'   => $release['version'],
			'url'           => 'https://github.com/' . $this->github_repo,
			'package'       => $release['package'],
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'requires'      => $release['requires'],
			'tested'        => $this->normalize_tested( $release['tested'] ),
			'requires_php'  => $release['requires_php'],
			'compatibility' => new \stdClass(),
		);

		if ( version_compare( $this->version, $release['version'], '<' ) ) {
			$transient->response[ $this->plugin_file ] = $item;
			unset( $transient->no_update[ $this->plugin_file ] );
		} else {
			$transient->no_update[ $this->plugin_file ] = $item;
			unset( $transient->response[ $this->plugin_file ] );
		}

		return $transient;
	}

	/**
	 * Handle plugin information popup ("View details").
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $result The result object or array.
	 * @param string $action The type of information being requested.
	 * @param object $args Arguments for the request.
	 * @return mixed Modified result or original result if not our plugin.
	 */
	public function plugin_popup( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release     = $this->get_latest_release();
		$plugin_data = $this->get_plugin_data();

		return (object) array(
			'name'           => $plugin_data['Name'],
			'slug'           => $this->plugin_slug,
			'version'        => $release ? $release['version'] : $this->version,
			'author'         => $plugin_data['Author'],
			'author_profile' => $plugin_data['AuthorURI'],
			'requires'       => $release && $release['requires'] ? $release['requires'] : $plugin_data['RequiresWP'],
			'tested'         => $release ? $this->normalize_tested( $release['tested'] ) : '',
			'requires_php'   => $release && $release['requires_php'] ? $release['requires_php'] : $plugin_data['RequiresPHP'],
			'last_updated'   => $release ? $release['published_at'] : '',
			'sections'       => array(
				'description' => $plugin_data['Description'],
				'changelog'   => $this->get_remote_changelog(),
				'support'     => $this->get_support_section(),
			),
			'homepage'       => 'https://github.com/' . $this->github_repo,
			'download_link'  => $release ? $release['package'] : '',
		);
	}

	/**
	 * Keep the installed folder name when an update package is unpacked.
	 *
	 * WordPress installs an update into a folder named after the ZIP's top-level
	 * directory (or after its temporary working directory when the ZIP has no
	 * top-level directory), then deletes the old folder. If both names differ,
	 * the plugin's path changes and WordPress deactivates it. The unpacked files
	 * are therefore moved into a folder named like the installed one.
	 *
	 * @since 1.5.2
	 *
	 * @param string|\WP_Error $source        Path of the unpacked files to install.
	 * @param string           $remote_source Working directory the package was unpacked into.
	 * @param \WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array            $hook_extra    Extra arguments (contains `plugin` on updates).
	 * @return string|\WP_Error Corrected source path or error.
	 */
	public function fix_source_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( is_wp_error( $source )
			|| ! $wp_filesystem
			|| empty( $hook_extra['plugin'] )
			|| $this->plugin_file !== $hook_extra['plugin']
			|| '.' === $this->plugin_slug ) {
			return $source;
		}

		$source        = trailingslashit( $source );
		$remote_source = trailingslashit( $remote_source );
		$target        = $remote_source . $this->plugin_slug . '/';

		// Make sure the package really is this plugin before installing it.
		if ( ! $wp_filesystem->exists( $source . basename( $this->plugin_file ) ) ) {
			return new \WP_Error(
				'anchor_dynamic_url_invalid_package',
				/* translators: Error shown when the downloaded update ZIP is not this plugin */
				__( 'The update package does not contain the Anchor Dynamic URL plugin.', 'anchor-dynamic-url' )
			);
		}

		if ( $source === $target ) {
			return $source;
		}

		if ( $source === $remote_source ) {
			// ZIP without a top-level directory: move its entries into a sub-folder.
			$entries = $wp_filesystem->dirlist( $remote_source );
			$moved   = is_array( $entries ) && $wp_filesystem->mkdir( $target );

			foreach ( $moved ? array_keys( $entries ) : array() as $name ) {
				if ( ! $this->move_path( $remote_source . $name, $target . $name ) ) {
					$moved = false;
					break;
				}
			}
		} else {
			$moved = $this->move_path( $source, $target );
		}

		if ( ! $moved ) {
			return new \WP_Error(
				'anchor_dynamic_url_rename_failed',
				/* translators: Error shown when the unpacked update cannot be moved to the plugin folder name */
				__( 'Unable to prepare the update package folder.', 'anchor-dynamic-url' )
			);
		}

		return $target;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Move a file or directory with the WordPress filesystem.
	 *
	 * Uses move_dir() (WordPress 6.2+) for directories, which falls back to a
	 * recursive copy when a plain rename is not possible.
	 *
	 * @since 1.5.2
	 *
	 * @param string $from Source path.
	 * @param string $to   Destination path.
	 * @return bool
	 */
	private function move_path( $from, $to ) {
		global $wp_filesystem;

		if ( function_exists( 'move_dir' ) && $wp_filesystem->is_dir( $from ) ) {
			return true === move_dir( $from, $to );
		}

		return $wp_filesystem->move( $from, $to );
	}

	/**
	 * Get the latest release, cached for 12 hours (1 hour after a failed request).
	 *
	 * The "Check again" button of Dashboard > Updates bypasses the cache.
	 *
	 * @since 1.5.2
	 *
	 * @return array|null Release data, or null when unavailable.
	 */
	private function get_latest_release() {
		if ( false !== $this->release ) {
			return $this->release;
		}

		$cached = get_transient( self::RELEASE_CACHE_KEY );

		if ( is_array( $cached ) && ! $this->is_force_check() ) {
			$this->release = empty( $cached['version'] ) ? null : $cached;
			return $this->release;
		}

		$this->release = $this->fetch_latest_release();

		// Cache failures too, so a GitHub outage or rate limit is not hit on every page load.
		set_transient(
			self::RELEASE_CACHE_KEY,
			$this->release ? $this->release : array( 'version' => '' ),
			$this->release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS
		);

		return $this->release;
	}

	/**
	 * Fetch the latest published release from the GitHub API.
	 *
	 * @since 1.5.2
	 *
	 * @return array|null Release data, or null on failure.
	 */
	private function fetch_latest_release() {
		$response = wp_remote_get( 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest', $this->request_args() );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! is_string( $data['tag_name'] ) ) {
			return null;
		}

		$package = $this->find_package_url( $data );

		if ( ! $package ) {
			return null;
		}

		return array(
			'version'      => ltrim( $data['tag_name'], 'vV' ),
			'package'      => $package,
			'published_at' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
		) + $this->fetch_readme_headers( $data['tag_name'] );
	}

	/**
	 * Pick the ZIP to install from a release.
	 *
	 * Prefers a `anchor-dynamic-url*.zip` asset, then any ZIP asset, then the
	 * source archive GitHub generates for the tag (its folder name is fixed by
	 * fix_source_folder()).
	 *
	 * @since 1.5.2
	 *
	 * @param array $release Release data from the GitHub API.
	 * @return string Download URL, or empty string.
	 */
	private function find_package_url( array $release ) {
		$fallback = '';

		foreach ( isset( $release['assets'] ) && is_array( $release['assets'] ) ? $release['assets'] : array() as $asset ) {
			if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) || '.zip' !== strtolower( substr( $asset['name'], -4 ) ) ) {
				continue;
			}

			if ( 0 === strpos( $asset['name'], 'anchor-dynamic-url' ) ) {
				return $asset['browser_download_url'];
			}

			if ( ! $fallback ) {
				$fallback = $asset['browser_download_url'];
			}
		}

		if ( ! $fallback && ! empty( $release['zipball_url'] ) ) {
			$fallback = $release['zipball_url'];
		}

		return (string) $fallback;
	}

	/**
	 * Read the compatibility headers from the readme.txt of a release.
	 *
	 * @since 1.5.2
	 *
	 * @param string $tag Release tag.
	 * @return array With keys requires, tested, requires_php (empty when unknown).
	 */
	private function fetch_readme_headers( $tag ) {
		$headers = array(
			'requires'     => '',
			'tested'       => '',
			'requires_php' => '',
		);

		$response = wp_remote_get(
			'https://raw.githubusercontent.com/' . $this->github_repo . '/' . rawurlencode( $tag ) . '/readme.txt',
			$this->request_args()
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $headers;
		}

		$readme = wp_remote_retrieve_body( $response );
		$labels = array(
			'requires'     => 'Requires at least',
			'tested'       => 'Tested up to',
			'requires_php' => 'Requires PHP',
		);

		foreach ( $labels as $key => $label ) {
			if ( preg_match( '/^' . preg_quote( $label, '/' ) . ':\s*([0-9][0-9.]*)/mi', $readme, $match ) ) {
				$headers[ $key ] = rtrim( $match[1], '.' );
			}
		}

		return $headers;
	}

	/**
	 * Expand a "Tested up to" branch (e.g. 7.1) to the running point release (e.g. 7.1.5).
	 *
	 * WordPress compares this value with its own full version, so "7.1" would
	 * otherwise be reported as "Compatibility: Unknown" on WordPress 7.1.5.
	 *
	 * @since 1.5.2
	 *
	 * @param string $tested Tested up to value.
	 * @return string
	 */
	private function normalize_tested( $tested ) {
		$wp_version = get_bloginfo( 'version' );

		if ( preg_match( '/^\d+\.\d+$/', $tested ) && 0 === strpos( $wp_version, $tested . '.' ) ) {
			return $wp_version;
		}

		return $tested;
	}

	/**
	 * Get remote changelog from GitHub, cached for 12 hours.
	 *
	 * @since 1.0.0
	 *
	 * @return string Formatted changelog HTML.
	 */
	private function get_remote_changelog() {
		$cached = get_transient( self::CHANGELOG_CACHE_KEY );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/' . $this->github_repo . '/releases', $this->request_args() );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			/* translators: Error message when changelog cannot be fetched from GitHub */
			return __( 'Unable to fetch changelog.', 'anchor-dynamic-url' );
		}

		$releases = json_decode( wp_remote_retrieve_body( $response ), true );

		// A list of releases is expected (an error payload is an associative array).
		if ( ! is_array( $releases ) || ! isset( $releases[0] ) ) {
			/* translators: Message shown when no changelog data is available */
			return __( 'No changelog available.', 'anchor-dynamic-url' );
		}

		$changelog = '<div class="menu-anchor-changelog">';

		foreach ( array_slice( $releases, 0, 5 ) as $release ) {
			if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
				continue;
			}

			$version      = ltrim( $release['tag_name'], 'vV' );
			$date         = empty( $release['published_at'] ) ? '' : wp_date( 'F j, Y', strtotime( $release['published_at'] ) );
			$release_body = empty( $release['body'] )
				/* translators: Message shown when a release has no notes */
				? esc_html__( 'No release notes available.', 'anchor-dynamic-url' )
				: wpautop( wp_kses_post( $release['body'] ) );

			$changelog .= '<h4>' . sprintf(
				/* translators: 1: version number, 2: release date */
				esc_html__( 'Version %1$s - %2$s', 'anchor-dynamic-url' ),
				esc_html( $version ),
				esc_html( $date )
			) . '</h4>';
			$changelog .= '<div>' . $release_body . '</div>';
		}

		$changelog .= '</div>';

		set_transient( self::CHANGELOG_CACHE_KEY, $changelog, 12 * HOUR_IN_SECONDS );

		return $changelog;
	}

	/**
	 * Build the "Support" tab of the plugin details popup.
	 *
	 * @since 1.5.2
	 *
	 * @return string HTML.
	 */
	private function get_support_section() {
		/* translators: Text of the "Support" tab in the plugin details popup, followed by the GitHub issues link */
		return '<p>' . esc_html__( 'Found a bug or need help? Open an issue on GitHub.', 'anchor-dynamic-url' ) . '</p>'
			. '<p><a href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank">' . esc_html( self::SUPPORT_URL ) . '</a></p>';
	}

	/**
	 * Whether the current request is a forced update check (Dashboard > Updates > Check again).
	 *
	 * @since 1.5.2
	 *
	 * @return bool
	 */
	private function is_force_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag; WordPress core handles the request.
		return is_admin() && ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' );
	}

	/**
	 * Get the plugin header data, loaded only when needed.
	 *
	 * @since 1.5.2
	 *
	 * @return array
	 */
	private function get_plugin_data() {
		if ( null === $this->plugin_data ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			$this->plugin_data = get_plugin_data( $this->plugin_path );
		}

		return $this->plugin_data;
	}

	/**
	 * HTTP arguments for GitHub requests.
	 *
	 * @since 1.5.2
	 *
	 * @return array
	 */
	private function request_args() {
		return array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
			),
		);
	}
}
