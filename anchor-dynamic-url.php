<?php
/**
 * Plugin Name: Anchor Dynamic URL
 * Plugin URI: https://www.hylbee.fr/
 * Description: This plugin allows you to add a anchor to menu element with a dynamic URL for WordPress menu items and for Elementor element.
 * Version: 1.4.0
 * Author: Hylbee
 * Author URI: https://www.hylbee.fr/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anchor-dynamic-url
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Update URI: https://github.com/Hylbee/anchor-dynamic-url
 * Network: false
 *
 * @package AnchorDynamicUrl
 */

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Check minimum requirements.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: Required PHP version */
				esc_html__( 'Anchor Dynamic URL requires PHP version %s or higher.', 'anchor-dynamic-url' ),
				esc_html( '7.4' )
			);
			echo '</p></div>';
		}
	);
	return;
}

if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
	add_action(
		'admin_notices',
		function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: Required WordPress version */
				esc_html__( 'Anchor Dynamic URL requires WordPress version %s or higher.', 'anchor-dynamic-url' ),
				esc_html( '5.0' )
			);
			echo '</p></div>';
		}
	);
	return;
}

// Define plugin constants for use throughout the plugin.
define( 'ANCHOR_DYNAMIC_URL_VERSION', '1.4.0' );
define( 'ANCHOR_DYNAMIC_URL_FILE', __FILE__ );
define( 'ANCHOR_DYNAMIC_URL_PATH', plugin_dir_path( __FILE__ ) );
define( 'ANCHOR_DYNAMIC_URL_URL', plugin_dir_url( __FILE__ ) );
define( 'ANCHOR_DYNAMIC_URL_BASENAME', plugin_basename( __FILE__ ) );

// Include the PSR-4 autoloader.
require_once ANCHOR_DYNAMIC_URL_PATH . 'includes/autoloader.php';

// Initialize the plugin when WordPress is ready.
add_action(
	'init',
	function() {
		\AnchorDynamicUrl\Plugin\AnchorDynamicUrlManager::get_instance();
	}
);

// Helper function for developers.
if ( ! function_exists( 'get_menu_item_anchor' ) ) {
	/**
	 * Get anchor for a specific menu item.
	 *
	 * Public API function for theme and plugin developers.
	 *
	 * @since 1.0.0
	 *
	 * @param int $menu_item_id WordPress menu item ID.
	 * @return string|null Anchor string or null if not set.
	 */
	function get_menu_item_anchor( $menu_item_id ) {
		$repository = new \AnchorDynamicUrl\Repository\AnchorRepository();
		return $repository->get_anchor( $menu_item_id );
	}
}

/**
 * Plugin activation hook.
 *
 * Runs when the plugin is activated to check system requirements.
 *
 * @since 1.0.0
 */
register_activation_hook( __FILE__, 'anchor_dynamic_url_activate' );

/**
 * Plugin activation callback.
 *
 * Verifies system requirements and performs initial setup.
 *
 * @since 1.0.0
 */
function anchor_dynamic_url_activate() {
	// Security check - only administrators can activate plugins.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// Check WordPress version compatibility.
	if ( version_compare( get_bloginfo( 'version' ), '5.0', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Anchor Dynamic URL requires WordPress 5.0 or higher.', 'anchor-dynamic-url' ),
			esc_html__( 'Plugin Activation Error', 'anchor-dynamic-url' ),
			array( 'back_link' => true )
		);
	}

	// Check PHP version compatibility.
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'Anchor Dynamic URL requires PHP 7.4 or higher.', 'anchor-dynamic-url' ),
			esc_html__( 'Plugin Activation Error', 'anchor-dynamic-url' ),
			array( 'back_link' => true )
		);
	}

	// Clear cached update information to force fresh check.
	delete_transient( 'anchor_dynamic_url_remote_version' );
}

/**
 * Plugin deactivation hook.
 *
 * Runs when the plugin is deactivated to clean up temporary data.
 *
 * @since 1.0.0
 */
register_deactivation_hook( __FILE__, 'anchor_dynamic_url_deactivate' );

/**
 * Plugin deactivation callback.
 *
 * Cleans up temporary data but preserves user data.
 *
 * @since 1.0.0
 */
function anchor_dynamic_url_deactivate() {
	// Clear cached update information.
	delete_transient( 'anchor_dynamic_url_remote_version' );
}

/**
 * Plugin uninstall hook.
 *
 * Runs when the plugin is completely removed from WordPress.
 *
 * @since 1.0.0
 */
register_uninstall_hook( __FILE__, 'anchor_dynamic_url_uninstall' );

/**
 * Plugin uninstall callback.
 *
 * Removes all plugin data from the database when plugin is deleted.
 * This is only called when user chooses to delete the plugin permanently.
 *
 * @since 1.0.0
 */
function anchor_dynamic_url_uninstall() {
	// Clear any remaining transient data.
	delete_transient( 'anchor_dynamic_url_remote_version' );

	// Remove all menu anchor meta data using WordPress functions.
	$menu_items = get_posts(
		array(
			'post_type'      => 'nav_menu_item',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	// Delete anchor meta for each menu item.
	foreach ( $menu_items as $menu_item_id ) {
		delete_post_meta( $menu_item_id, '_menu_item_anchor' );
	}
}