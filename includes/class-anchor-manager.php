<?php
/**
 * Main Plugin Class - Orchestrates the entire system.
 *
 * This is the main controller class that coordinates all plugin functionality.
 * It implements the Singleton pattern to ensure only one instance exists.
 *
 * @package AnchorDynamicUrl\Plugin
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Plugin;

use AnchorDynamicUrl\Service\AnchorService;
use AnchorDynamicUrl\Admin\AnchorDynamicUrlUpdater;

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class - Orchestrates the entire system.
 *
 * This is the main controller class that coordinates all plugin functionality.
 * It implements the Singleton pattern to ensure only one instance exists.
 *
 * @since 1.0.0
 */
class AnchorDynamicUrlManager {

	/**
	 * Business logic service.
	 *
	 * @var AnchorService
	 */
	private $service;

	/**
	 * GitHub update handler.
	 *
	 * @var AnchorDynamicUrlUpdater
	 */
	private $updater;

	/**
	 * Singleton instance.
	 *
	 * @var AnchorDynamicUrlManager|null
	 */
	private static $instance = null;

	/**
	 * Private constructor to enforce singleton pattern.
	 *
	 * Initializes all dependencies and WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->service = new AnchorService();
		$this->updater = new AnchorDynamicUrlUpdater( ANCHOR_DYNAMIC_URL_FILE, ANCHOR_DYNAMIC_URL_VERSION );
		$this->init_hooks();
	}

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return AnchorDynamicUrlManager The plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new AnchorDynamicUrlManager();
		}

		return self::$instance;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * Connects all plugin functionality to WordPress action and filter hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Core menu functionality hooks.
		// Add custom anchor field to menu item edit forms.
		add_action( 'wp_nav_menu_item_custom_fields', array( $this->service, 'add_anchor_field' ), 10, 4 );

		// Save anchor field data when menu is updated.
		add_action( 'wp_update_nav_menu_item', array( $this->service, 'save_anchor' ), 10, 2 );

		// Handle complete menu output (bulk processing). wp_setup_nav_menu_item is intentionally
		// omitted to avoid processing each item twice (once per item + once in bulk).
		add_filter( 'wp_nav_menu_objects', array( $this->service, 'handle_bulk_update' ), 10, 1 );

		// Admin interface hooks.
		// Add custom CSS styles for admin menu interface.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

		// Internationalization is handled automatically for WordPress.org hosted plugins.
		// No need to manually load text domain since WordPress 4.6+.

		// Plugin management hooks.
		// Add additional links in plugin list (changelog, support, etc.).
		add_filter( 'plugin_row_meta', array( $this, 'add_plugin_links' ), 10, 2 );

		// Add action links in plugin list (settings, etc.).
		add_filter( 'plugin_action_links_' . plugin_basename( ANCHOR_DYNAMIC_URL_FILE ), array( $this, 'add_action_links' ) );

		// Initialize additional plugin components.
		$this->init_includes();
	}

	/**
	 * Initialize additional plugin components and integrations.
	 *
	 * Loads optional features and third-party integrations.
	 *
	 * @since 1.0.0
	 */
	private function init_includes() {
		// Load Elementor integration on the elementor/loaded hook so it fires regardless
		// of whether Elementor loads before or after this plugin during init.
		if ( file_exists( ANCHOR_DYNAMIC_URL_PATH . 'includes/elementor-integration.php' ) ) {
			add_action( 'elementor/loaded', function() {
				require_once ANCHOR_DYNAMIC_URL_PATH . 'includes/elementor-integration.php';
			} );
		}
	}

	/**
	 * Add custom links to plugin row (changelog, support, etc.).
	 *
	 * Adds helpful links below the plugin description in the admin plugins list.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $plugin_meta Existing plugin meta links.
	 * @param string $plugin_file Current plugin file being processed.
	 * @return array Modified plugin meta links.
	 */
	public function add_plugin_links( $plugin_meta, $plugin_file ) {
		// Only add links for our plugin.
		if ( plugin_basename( ANCHOR_DYNAMIC_URL_FILE ) === $plugin_file ) {
			// Add changelog link.
			/* translators: Link text for viewing the plugin changelog */
			$plugin_meta[] = '<a href="https://github.com/Hylbee/anchor-dynamic-url/blob/v' . ANCHOR_DYNAMIC_URL_VERSION . '/CHANGELOG.md" target="_blank"> ' . esc_html__( 'View Changelog', 'anchor-dynamic-url' ) . '</a>';
			// Add support link.
			/* translators: Link text for getting support */
			$plugin_meta[] = '<a href="https://github.com/Hylbee/anchor-dynamic-url/issues" target="_blank">' . esc_html__( 'Support', 'anchor-dynamic-url' ) . '</a>';
			// Add GitHub repository link.
			/* translators: Link text for GitHub repository */
			$plugin_meta[] = '<a href="https://github.com/Hylbee/anchor-dynamic-url" target="_blank">' . esc_html__( 'GitHub', 'anchor-dynamic-url' ) . '</a>';
		}

		return $plugin_meta;
	}

	/**
	 * Add action links (Settings, etc.).
	 *
	 * Adds action links next to "Activate/Deactivate" in the plugins list.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links with our additions.
	 */
	public function add_action_links( $links ) {
		$action_links = array(
			// Add link to menu configuration page.
			/* translators: Link text for configuring menus */
			'settings' => '<a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '">' . esc_html__( 'Configure Menus', 'anchor-dynamic-url' ) . '</a>',
		);

		// Prepend our links to the existing ones.
		return array_merge( $action_links, $links );
	}

	/**
	 * Enqueue admin styles for better UX.
	 *
	 * Adds custom CSS to improve the appearance of anchor fields in menu editor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_styles( $hook ) {
		// Only load styles on the nav-menus.php page.
		if ( 'nav-menus.php' !== $hook ) {
			return;
		}

		// Custom CSS for anchor field styling.
		$css = "
		/* Style for anchor field description text */
		.field-anchor .description {
			font-style: italic;
			color: #666;
			margin-top: 5px;
		}
		/* Style for anchor input field */
		.field-anchor input {
			margin-top: 5px;
		}
		";

		// Add CSS inline to the nav-menu stylesheet.
		wp_add_inline_style( 'nav-menu', $css );
	}

	// Text domain loading removed - WordPress handles this automatically for .org hosted plugins since 4.6+
}