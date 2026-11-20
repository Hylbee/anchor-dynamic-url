<?php
/**
 * Domain Entity - Represents a menu item with anchor capability.
 *
 * This class encapsulates the data and behavior of a menu item that can have an anchor.
 * It follows Domain-Driven Design principles for clean architecture.
 *
 * @package AnchorDynamicUrl\Entity
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Entity;

use AnchorDynamicUrl\Utils\AnchorSanitizer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AnchorItem class.
 */
class AnchorItem {

	/**
	 * WordPress menu item ID.
	 *
	 * @var int
	 */
	private $menu_item_id;

	/**
	 * Sanitized anchor string (without #).
	 *
	 * @var string|null
	 */
	private $anchor;

	/**
	 * Target page ID if linking to a specific page.
	 *
	 * @var int|null
	 */
	private $target_page_id;

	/**
	 * Original URL before anchor modification.
	 *
	 * @var string
	 */
	private $original_url;

	/**
	 * Create a new menu item anchor entity.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $menu_item_id WordPress menu item ID.
	 * @param string|null $anchor Raw anchor string (will be sanitized).
	 * @param int|null    $target_page_id Target page ID for dynamic URL generation.
	 * @param string      $original_url Original menu item URL.
	 */
	public function __construct( $menu_item_id, $anchor = null, $target_page_id = null, $original_url = '' ) {
		$this->menu_item_id   = $menu_item_id;
		$this->anchor         = AnchorSanitizer::sanitize( $anchor );
		$this->target_page_id = $target_page_id;
		$this->original_url   = $original_url;
	}

	/**
	 * Generate the final URL with anchor if present.
	 *
	 * This method constructs the complete URL including the anchor fragment.
	 *
	 * @since 1.0.0
	 *
	 * @return string Complete URL with anchor, or original URL if no anchor.
	 */
	public function generate_url() {
		if ( empty( $this->anchor ) ) {
			return $this->original_url;
		}

		if ( $this->target_page_id ) {
			$base_url = get_permalink( $this->target_page_id );
		} else {
			$base_url = strtok( $this->original_url, '#' );
		}

		return $base_url . '#' . $this->anchor;
	}

	/**
	 * Get the anchor value.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null The sanitized anchor string, or null if not set.
	 */
	public function get_anchor() {
		return $this->anchor;
	}
}