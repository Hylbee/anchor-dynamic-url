<?php
/**
 * Service - Handles the business logic for menu anchors.
 *
 * This class implements the Service pattern to coordinate between UI and data layers.
 * It contains the core business logic for managing menu anchors.
 *
 * @package AnchorDynamicUrl\Service
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Service;

use AnchorDynamicUrl\Repository\AnchorRepository;
use AnchorDynamicUrl\Utils\AnchorSanitizer;

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service - Handles the business logic for menu anchors.
 *
 * This class implements the Service pattern to coordinate between UI and data layers.
 * It contains the core business logic for managing menu anchors.
 *
 * @since 1.0.0
 */
class AnchorService {

	/**
	 * Data access layer.
	 *
	 * @var AnchorRepository
	 */
	private $repository;

	/**
	 * Initialize the service with its dependencies.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->repository = new AnchorRepository();
	}

	/**
	 * Get the repository instance.
	 *
	 * @since 1.4.1
	 *
	 * @return AnchorRepository
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Add anchor field to menu item edit form.
	 *
	 * This method is called by WordPress when rendering menu item edit forms.
	 * It adds our custom anchor input field to each menu item.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $item_id Menu item ID.
	 * @param object $item Menu item object.
	 * @param int    $depth Menu item depth (for nested menus).
	 * @param object $args Additional arguments from WordPress.
	 */
	public function add_anchor_field( $item_id, $item, $depth, $args ) {
		// Get existing anchor value for this menu item.
		$anchor = $this->repository->get_anchor( $item_id );

		// Output HTML for the anchor input field.
		?>
		<p class="field-anchor description description-wide">
			<label for="edit-menu-item-anchor-<?php echo esc_attr( $item_id ); ?>">
				<?php
				/* translators: Label for the anchor input field in menu editor */
				esc_html_e( 'Anchor (optional)', 'anchor-dynamic-url' );
				?><br>
				<!-- Anchor input field -->
				<input type="text"
					   id="edit-menu-item-anchor-<?php echo esc_attr( $item_id ); ?>"
					   class="widefat edit-menu-item-anchor"
					   name="menu-item-anchor[<?php echo esc_attr( $item_id ); ?>]"
					   value="<?php echo esc_attr( $anchor ? $anchor : '' ); ?>"
					   placeholder="<?php
					   /* translators: Example placeholder text for anchor input field */
					   esc_attr_e( 'e.g: contact-section', 'anchor-dynamic-url' );
					   ?>">
			</label>
			<!-- Help text explaining the feature -->
			<span class="description">
				<?php
				/* translators: Help text explaining what the anchor field does */
				esc_html_e( 'Adds #anchor to the URL. Updates automatically if the page slug changes.', 'anchor-dynamic-url' );
				?>
			</span>
		</p>
		<?php
	}

	/**
	 * Save anchor when menu item is updated.
	 *
	 * This method is called by WordPress when a menu is saved.
	 * It processes and saves the anchor data for each menu item.
	 *
	 * @since 1.0.0
	 *
	 * @param int $menu_id Menu ID (not used but required by WordPress hook).
	 * @param int $menu_item_db_id Menu item ID being saved.
	 */
	public function save_anchor( $menu_id, $menu_item_db_id ) {
		// Verify nonce for security.
		if ( ! isset( $_POST['update-nav-menu-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['update-nav-menu-nonce'] ) ), 'update-nav_menu' ) ) {
			return;
		}

		// Check if anchor data was submitted for this menu item.
		if ( ! isset( $_POST['menu-item-anchor'][ $menu_item_db_id ] ) ) {
			return;
		}

		// Get the submitted anchor value with proper sanitization.
		$anchor = sanitize_text_field( wp_unslash( $_POST['menu-item-anchor'][ $menu_item_db_id ] ) );

		// Additional sanitization for anchor format.
		$sanitized_anchor = AnchorSanitizer::sanitize( $anchor );

		// Save to database through repository.
		$this->repository->save_anchor( $menu_item_db_id, $sanitized_anchor );
	}

	/**
	 * Update menu item URL with anchor.
	 *
	 * This method is called for each menu item before display.
	 * It modifies the URL to include the anchor if one is set.
	 *
	 * @since 1.0.0
	 *
	 * @param object $menu_item WordPress menu item object.
	 * @return object Modified menu item with updated URL.
	 */
	public function update_menu_item_url( $menu_item ) {
		// Create domain entity from menu item.
		$anchor_item = $this->repository->create_entity_from_menu_item( $menu_item );
		// Update the menu item's URL with anchor.
		$menu_item->url = $anchor_item->generate_url();

		return $menu_item;
	}

	/**
	 * Handle bulk menu updates.
	 *
	 * This method processes all menu items at once for efficiency.
	 * Called when WordPress prepares menu objects for display.
	 *
	 * @since 1.0.0
	 *
	 * @param array $menu_items Array of WordPress menu item objects.
	 * @return array Array of modified menu items with updated URLs.
	 */
	public function handle_bulk_update( $menu_items ) {
		// Process each menu item to add anchors.
		foreach ( $menu_items as &$menu_item ) {
			$menu_item = $this->update_menu_item_url( $menu_item );
		}

		return $menu_items;
	}
}