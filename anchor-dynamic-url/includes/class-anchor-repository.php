<?php
/**
 * Repository - Handles data persistence for menu anchors.
 *
 * This class follows the Repository pattern to abstract database operations.
 * It provides a clean interface for storing and retrieving menu anchor data.
 *
 * @package AnchorDynamicUrl\Repository
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Repository;

use AnchorDynamicUrl\Entity\AnchorItem;
use AnchorDynamicUrl\Utils\AnchorSanitizer;

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository - Handles data persistence for menu anchors.
 *
 * This class follows the Repository pattern to abstract database operations.
 * It provides a clean interface for storing and retrieving menu anchor data.
 *
 * @since 1.0.0
 */
class AnchorRepository {

	/**
	 * WordPress meta key for storing anchor data.
	 *
	 * @var string
	 */
	private $meta_key = '_menu_item_anchor';

	/**
	 * Save anchor for a menu item.
	 *
	 * Stores the anchor data in WordPress post meta table.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $menu_item_id Menu item ID to save anchor for.
	 * @param string $anchor Anchor string to save, or null to delete.
	 * @return bool True on success, false on failure.
	 */
	public function save_anchor( $menu_item_id, $anchor ) {
		// Delete meta if anchor is empty.
		if ( empty( $anchor ) ) {
			return delete_post_meta( $menu_item_id, $this->meta_key );
		}

		// Save sanitized anchor to post meta.
		return update_post_meta( $menu_item_id, $this->meta_key, sanitize_text_field( $anchor ) );
	}

	/**
	 * Get anchor for a menu item.
	 *
	 * Retrieves anchor data from WordPress post meta table.
	 *
	 * @since 1.0.0
	 *
	 * @param int $menu_item_id Menu item ID to get anchor for.
	 * @return string|null Anchor string or null if not set.
	 */
	public function get_anchor( $menu_item_id ) {
		$anchor = get_post_meta( $menu_item_id, $this->meta_key, true );
		return ! empty( $anchor ) ? $anchor : null;
	}

	/**
	 * Create MenuItemAnchor entity from menu item.
	 *
	 * Factory method to create domain entity from WordPress menu item object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $menu_item WordPress menu item object.
	 * @return AnchorItem Domain entity representing the menu item with anchor.
	 */
	public function create_entity_from_menu_item( $menu_item ) {
		// Get stored anchor data for this menu item.
		$anchor = $this->get_anchor( $menu_item->ID );
		$target_page_id = null;

		// Get target page ID if this menu item links to a page.
		// This enables dynamic URL generation when page slugs change.
		if ( 'page' === $menu_item->object && ! empty( $menu_item->object_id ) ) {
			$target_page_id = (int) $menu_item->object_id;
		}

		// Create and return domain entity.
		return new AnchorItem(
			$menu_item->ID,
			$anchor,
			$target_page_id,
			$menu_item->url
		);
	}
}