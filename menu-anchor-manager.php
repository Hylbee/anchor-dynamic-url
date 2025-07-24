<?php
/**
 * Plugin Name: Menu Anchor Manager
 * Plugin URI: https://www.hylbee.fr/
 * Description: Add dynamic anchors to WordPress menu items with automatic URL updates
 * Version: 1.1.0
 * Author: Hylbee
 * Author URI: https://www.hylbee.fr/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: menu-anchor-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Domain Entity - Represents a menu item with anchor capability
 */
class MenuItemAnchor {
    private int $menuItemId;
    private ?string $anchor;
    private ?int $targetPageId;
    private string $originalUrl;
    
    public function __construct(int $menuItemId, ?string $anchor = null, ?int $targetPageId = null, string $originalUrl = '') {
        $this->menuItemId = $menuItemId;
        $this->anchor = $this->sanitizeAnchor($anchor);
        $this->targetPageId = $targetPageId;
        $this->originalUrl = $originalUrl;
    }
    
    /**
     * Generate the final URL with anchor if present
     */
    public function generateUrl(): string {
        if (empty($this->anchor)) {
            return $this->originalUrl;
        }
        
        // If we have a target page, use its permalink
        if ($this->targetPageId) {
            $baseUrl = get_permalink($this->targetPageId);
        } else {
            // Remove existing anchor from original URL
            $baseUrl = strtok($this->originalUrl, '#');
        }
        
        return $baseUrl . '#' . $this->anchor;
    }
    
    /**
     * Get the anchor value
     */
    public function getAnchor(): ?string {
        return $this->anchor;
    }
    
    /**
     * Sanitize anchor to be URL-safe and secure
     */
    private function sanitizeAnchor(?string $anchor): ?string {
        if (empty($anchor)) {
            return null;
        }
        
        // Remove everything after dangerous characters for security
        $anchor = trim($anchor);
        $anchor = preg_split('/[?\/\\\\]/', $anchor)[0];
        
        // Convert spaces to hyphens
        $anchor = str_replace([' ', '\t', '\n', '\r'], '-', $anchor);
        
        // Remove any characters that aren't letters, numbers, hyphens, or underscores
        $anchor = preg_replace('/[^a-zA-Z0-9\-_]/', '', $anchor);
        
        // Remove multiple consecutive hyphens (keep case sensitivity)
        $anchor = preg_replace('/-+/', '-', $anchor);
        
        // Remove leading/trailing hyphens
        $anchor = trim($anchor, '-');
        
        // Return null if empty after sanitization
        return !empty($anchor) ? $anchor : null;
    }
}

/**
 * Repository - Handles data persistence for menu anchors
 */
class MenuAnchorRepository {
    private const META_KEY = '_menu_item_anchor';
    
    /**
     * Save anchor for a menu item
     */
    public function saveAnchor(int $menuItemId, ?string $anchor): bool {
        if (empty($anchor)) {
            return delete_post_meta($menuItemId, self::META_KEY);
        }
        
        return update_post_meta($menuItemId, self::META_KEY, sanitize_text_field($anchor));
    }
    
    /**
     * Get anchor for a menu item
     */
    public function getAnchor(int $menuItemId): ?string {
        $anchor = get_post_meta($menuItemId, self::META_KEY, true);
        return !empty($anchor) ? $anchor : null;
    }
    
    /**
     * Create MenuItemAnchor entity from menu item
     */
    public function createEntityFromMenuItem(object $menuItem): MenuItemAnchor {
        $anchor = $this->getAnchor($menuItem->ID);
        $targetPageId = null;
        
        // Get target page ID if it's a page
        if ($menuItem->object === 'page' && !empty($menuItem->object_id)) {
            $targetPageId = (int) $menuItem->object_id;
        }
        
        return new MenuItemAnchor(
            $menuItem->ID,
            $anchor,
            $targetPageId,
            $menuItem->url
        );
    }
}

/**
 * Service - Handles the business logic for menu anchors
 */
class MenuAnchorService {
    private MenuAnchorRepository $repository;
    
    public function __construct() {
        $this->repository = new MenuAnchorRepository();
    }
    
    /**
     * Add anchor field to menu item edit form
     */
    public function addAnchorField(int $item_id, object $item, int $depth, object $args): void {
        $anchor = $this->repository->getAnchor($item_id);
        
        ?>
        <p class="field-anchor description description-wide">
            <label for="edit-menu-item-anchor-<?php echo $item_id; ?>">
                <?php _e('Anchor (optional)', 'menu-anchor-manager'); ?><br>
                <input type="text" 
                       id="edit-menu-item-anchor-<?php echo $item_id; ?>" 
                       class="widefat edit-menu-item-anchor" 
                       name="menu-item-anchor[<?php echo $item_id; ?>]" 
                       value="<?php echo esc_attr($anchor ?? ''); ?>"
                       placeholder="<?php _e('e.g: contact-section', 'menu-anchor-manager'); ?>">
            </label>
            <span class="description">
                <?php _e('Adds #anchor to the URL. Updates automatically if the page slug changes.', 'menu-anchor-manager'); ?>
            </span>
        </p>
        <?php
    }
    
    /**
     * Save anchor when menu item is updated
     */
    public function saveAnchor(int $menu_id, int $menu_item_db_id): void {
        if (!isset($_POST['menu-item-anchor'][$menu_item_db_id])) {
            return;
        }
        
        $anchor = $_POST['menu-item-anchor'][$menu_item_db_id];
        $this->repository->saveAnchor($menu_item_db_id, $anchor);
    }
    
    /**
     * Update menu item URL with anchor
     */
    public function updateMenuItemUrl(object $menu_item): object {
        $menuItemAnchor = $this->repository->createEntityFromMenuItem($menu_item);
        $menu_item->url = $menuItemAnchor->generateUrl();
        
        return $menu_item;
    }
    
    /**
     * Handle bulk menu updates
     */
    public function handleBulkUpdate(array $menu_items): array {
        foreach ($menu_items as &$menu_item) {
            $menu_item = $this->updateMenuItemUrl($menu_item);
        }
        
        return $menu_items;
    }
}

/**
 * Main Plugin Class - Orchestrates the entire system
 */
class MenuAnchorManager {
    private MenuAnchorService $service;
    private static ?MenuAnchorManager $instance = null;
    
    private function __construct() {
        $this->service = new MenuAnchorService();
        $this->initHooks();
    }
    
    public static function getInstance(): MenuAnchorManager {
        if (self::$instance === null) {
            self::$instance = new MenuAnchorManager();
        }
        
        return self::$instance;
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void {
        // Add custom field to menu items
        add_action('wp_nav_menu_item_custom_fields', [$this->service, 'addAnchorField'], 10, 4);
        
        // Save custom field data
        add_action('wp_update_nav_menu_item', [$this->service, 'saveAnchor'], 10, 2);
        
        // Modify menu item URLs before display
        add_filter('wp_setup_nav_menu_item', [$this->service, 'updateMenuItemUrl'], 10, 1);
        
        // Handle menu output
        add_filter('wp_nav_menu_objects', [$this->service, 'handleBulkUpdate'], 10, 1);
        
        // Add admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
        
        // Load text domain for translations
        add_action('plugins_loaded', [$this, 'loadTextDomain']);
        
        // Add plugin description translation
        add_filter('plugin_row_meta', [$this, 'translatePluginMeta'], 10, 2);
        
        // Add changelog and additional plugin links
        add_filter('plugin_row_meta', [$this, 'addPluginLinks'], 10, 2);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'addActionLinks']);
    }
    
    /**
     * Translate plugin description in plugins list
     */
    public function translatePluginMeta(array $plugin_meta, string $plugin_file): array {
        if (plugin_basename(__FILE__) === $plugin_file) {
            $plugin_meta[1] = __('Add dynamic anchors to WordPress menu items with automatic URL updates', 'menu-anchor-manager');
        }
        
        return $plugin_meta;
    }
    
    /**
     * Add custom links to plugin row (changelog, support, etc.)
     */
    public function addPluginLinks(array $plugin_meta, string $plugin_file): array {
        if (plugin_basename(__FILE__) === $plugin_file) {
            $plugin_meta[] = '<a href="#" onclick="MenuAnchorManager.showChangelog(); return false;">' . __('View Changelog', 'menu-anchor-manager') . '</a>';
            $plugin_meta[] = '<a href="https://github.com/Hylbee/menu-anchor-manager/issues" target="_blank">' . __('Support', 'menu-anchor-manager') . '</a>';
            $plugin_meta[] = '<a href="https://github.com/Hylbee/menu-anchor-manager" target="_blank">' . __('GitHub', 'menu-anchor-manager') . '</a>';
        }
        
        return $plugin_meta;
    }
    
    /**
     * Add action links (Settings, etc.)
     */
    public function addActionLinks(array $links): array {
        $action_links = [
            'settings' => '<a href="' . admin_url('nav-menus.php') . '">' . __('Configure Menus', 'menu-anchor-manager') . '</a>'
        ];
        
        return array_merge($action_links, $links);
    }
    
    /**
     * Get plugin changelog data
     */
    public function getChangelog(): array {
        return [
            '1.1.0' => [
                'date' => '2025-07-24',
                'changes' => [
                    'changed' => [
                        __('Enhanced anchor sanitization with improved security', 'menu-anchor-manager'),
                        __('Case sensitivity preserved - anchors maintain original capitalization', 'menu-anchor-manager'),
                        __('Added comprehensive WordPress plugin headers', 'menu-anchor-manager')
                    ],
                    'security' => [
                        __('Protection against path traversal and query parameter injection', 'menu-anchor-manager'),
                        __('Strengthened input validation and character filtering', 'menu-anchor-manager')
                    ]
                ]
            ],
            '1.0.0' => [
                'date' => '2025-07-24',
                'changes' => [
                    'added' => [
                        __('Initial release with dynamic anchor management', 'menu-anchor-manager'),
                        __('Domain-driven architecture with clean separation of concerns', 'menu-anchor-manager'),
                        __('Automatic URL generation that updates when page slugs change', 'menu-anchor-manager'),
                        __('Multi-language support (English, French, Spanish)', 'menu-anchor-manager'),
                        __('Security features with input sanitization and XSS protection', 'menu-anchor-manager')
                    ]
                ]
            ]
        ];
    }
    
    /**
     * Enqueue admin styles for better UX
     */
    public function enqueueAdminStyles($hook): void {
        if ($hook !== 'nav-menus.php' && $hook !== 'plugins.php') {
            return;
        }
        
        $css = "
        .field-anchor .description {
            font-style: italic;
            color: #666;
            margin-top: 5px;
        }
        .field-anchor input {
            margin-top: 5px;
        }
        
        /* Changelog modal styles */
        #menu-anchor-changelog-modal {
            display: none;
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        #menu-anchor-changelog-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            border-radius: 5px;
        }
        
        .menu-anchor-changelog-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .menu-anchor-changelog-close:hover {
            color: #000;
        }
        
        .changelog-version {
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            padding-bottom: 15px;
        }
        
        .changelog-version h3 {
            margin: 0;
            color: #23282d;
        }
        
        .changelog-date {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .changelog-changes ul {
            margin: 5px 0;
        }
        
        .changelog-changes .change-type {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.85em;
            color: #0073aa;
        }
        ";
        
        wp_add_inline_style('nav-menu', $css);
        
        // Add JavaScript for changelog modal
        if ($hook === 'plugins.php') {
            $this->enqueueChangelogScript();
        }
    }
    
    /**
     * Enqueue changelog JavaScript
     */
    private function enqueueChangelogScript(): void {
        $changelog = $this->getChangelog();
        
        $js = "
        window.MenuAnchorManager = {
            showChangelog: function() {
                const modal = document.createElement('div');
                modal.id = 'menu-anchor-changelog-modal';
                modal.innerHTML = `
                    <div id='menu-anchor-changelog-content'>
                        <span class='menu-anchor-changelog-close'>&times;</span>
                        <h2>" . __('Menu Anchor Manager - Changelog', 'menu-anchor-manager') . "</h2>
                        " . $this->generateChangelogHTML($changelog) . "
                    </div>
                `;
                
                document.body.appendChild(modal);
                modal.style.display = 'block';
                
                // Close modal events
                modal.querySelector('.menu-anchor-changelog-close').onclick = function() {
                    document.body.removeChild(modal);
                };
                
                modal.onclick = function(event) {
                    if (event.target === modal) {
                        document.body.removeChild(modal);
                    }
                };
            }
        };
        ";
        
        wp_add_inline_script('jquery', $js);
    }
    
    /**
     * Generate HTML for changelog
     */
    private function generateChangelogHTML(array $changelog): string {
        $html = '';
        
        foreach ($changelog as $version => $data) {
            $html .= "<div class='changelog-version'>";
            $html .= "<h3>Version {$version}</h3>";
            $html .= "<div class='changelog-date'>" . sprintf(__('Released: %s', 'menu-anchor-manager'), $data['date']) . "</div>";
            
            foreach ($data['changes'] as $type => $changes) {
                $html .= "<div class='changelog-changes'>";
                $html .= "<div class='change-type'>" . ucfirst($type) . "</div>";
                $html .= "<ul>";
                foreach ($changes as $change) {
                    $html .= "<li>" . esc_html($change) . "</li>";
                }
                $html .= "</ul>";
                $html .= "</div>";
            }
            
            $html .= "</div>";
        }
        
        return $html;
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function loadTextDomain(): void {
        load_plugin_textdomain('menu-anchor-manager', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Initialize the plugin
add_action('init', function() {
    MenuAnchorManager::getInstance();
});

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    // Nothing specific needed for activation
    // The plugin works immediately after activation
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clean up is optional since we're using WordPress meta system
    // Meta data will remain in case user reactivates
});

// Helper function for developers (optional)
if (!function_exists('get_menu_item_anchor')) {
    /**
     * Get anchor for a specific menu item
     * 
     * @param int $menu_item_id
     * @return string|null
     */
    function get_menu_item_anchor(int $menu_item_id): ?string {
        $repository = new MenuAnchorRepository();
        return $repository->getAnchor($menu_item_id);
    }
}