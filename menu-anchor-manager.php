<?php
/**
 * Plugin Name: Menu Anchor Manager
 * Plugin URI: https://www.hylbee.fr/
 * Description: Add dynamic anchors to WordPress menu items with automatic URL updates
 * Version: 1.2.0
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
 * Update URI: https://github.com/Hylbee/menu-anchor-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MENU_ANCHOR_MANAGER_VERSION', '1.2.0');
define('MENU_ANCHOR_MANAGER_FILE', __FILE__);
define('MENU_ANCHOR_MANAGER_PATH', plugin_dir_path(__FILE__));
define('MENU_ANCHOR_MANAGER_URL', plugin_dir_url(__FILE__));

/**
 * GitHub Updater Class - Handles automatic updates from GitHub releases
 */
class MenuAnchorUpdater {
    private string $plugin_file;
    private string $plugin_slug;
    private string $version;
    private string $github_repo = 'Hylbee/menu-anchor-manager';
    private array $plugin_data;
    
    public function __construct(string $plugin_file, string $version) {
        $this->plugin_file = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_file);
        $this->version = $version;
        
        // Get plugin data
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data = get_plugin_data($plugin_file);
        
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks for update system
     */
    private function initHooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        add_filter('plugins_api', [$this, 'pluginPopup'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'afterInstall'], 10, 3);
        add_action('admin_init', [$this, 'showUpgradeNotification']);
    }
    
    /**
     * Check for plugin updates
     */
    public function checkForUpdate(object $transient): object {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->getRemoteVersion();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_file] = (object) [
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_file,
                'new_version' => $remote_version,
                'url' => "https://github.com/{$this->github_repo}",
                'package' => $this->getDownloadUrl($remote_version),
                'icons' => [],
                'banners' => [],
                'banners_rtl' => [],
                'tested' => $this->plugin_data['RequiresWP'] ?? '6.4',
                'requires_php' => $this->plugin_data['RequiresPHP'] ?? '7.4',
                'compatibility' => new stdClass()
            ];
        }
        
        return $transient;
    }
    
    /**
     * Get remote version from GitHub API
     */
    private function getRemoteVersion(): string {
        $transient_key = 'menu_anchor_manager_remote_version';
        $cached_version = get_transient($transient_key);
        
        if ($cached_version !== false) {
            return $cached_version;
        }
        
        $request = wp_remote_get("https://api.github.com/repos/{$this->github_repo}/releases/latest", [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            ]
        ]);
        
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            if (isset($data['tag_name'])) {
                $version = ltrim($data['tag_name'], 'v');
                // Cache for 12 hours
                set_transient($transient_key, $version, 12 * HOUR_IN_SECONDS);
                return $version;
            }
        }
        
        return $this->version;
    }
    
    /**
     * Get download URL for specific version
     */
    private function getDownloadUrl(string $version): string {
        return "https://github.com/{$this->github_repo}/releases/download/v{$version}/menu-anchor-manager.zip";
    }
    
    /**
     * Handle plugin information popup
     */
    public function pluginPopup($result, string $action, object $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->plugin_slug) {
            return $result;
        }
        
        $remote_version = $this->getRemoteVersion();
        $changelog = $this->getRemoteChangelog();
        
        return (object) [
            'name' => $this->plugin_data['Name'],
            'slug' => $this->plugin_slug,
            'version' => $remote_version,
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'requires' => $this->plugin_data['RequiresWP'] ?? '5.0',
            'tested' => $this->plugin_data['Tested'] ?? '6.4',
            'requires_php' => $this->plugin_data['RequiresPHP'] ?? '7.4',
            'sections' => [
                'description' => $this->plugin_data['Description'],
                'changelog' => $changelog
            ],
            'homepage' => "https://github.com/{$this->github_repo}",
            'download_link' => $this->getDownloadUrl($remote_version),
            'trunk' => $this->getDownloadUrl($remote_version)
        ];
    }
    
    /**
     * Get remote changelog from GitHub
     */
    private function getRemoteChangelog(): string {
        $request = wp_remote_get("https://api.github.com/repos/{$this->github_repo}/releases", [
            'timeout' => 10
        ]);
        
        if (is_wp_error($request)) {
            return __('Unable to fetch changelog.', 'menu-anchor-manager');
        }
        
        $body = wp_remote_retrieve_body($request);
        $releases = json_decode($body, true);
        
        if (!is_array($releases)) {
            return __('No changelog available.', 'menu-anchor-manager');
        }
        
        $changelog = '<div class="menu-anchor-changelog">';
        
        foreach (array_slice($releases, 0, 5) as $release) { // Show last 5 releases
            $version = ltrim($release['tag_name'], 'v');
            $date = date('F j, Y', strtotime($release['published_at']));
            $body = $release['body'] ? wp_kses_post($release['body']) : __('No release notes available.', 'menu-anchor-manager');
            
            $changelog .= "<h4>Version {$version} - {$date}</h4>";
            $changelog .= "<div>{$body}</div>";
        }
        
        $changelog .= '</div>';
        
        return $changelog;
    }
    
    /**
     * Handle post-installation cleanup
     */
    public function afterInstall(bool $response, array $hook_extra, array $result): bool {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->plugin_file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($this->plugin_file) {
            activate_plugin($this->plugin_file);
        }
        
        return $response;
    }
    
    /**
     * Show upgrade notification in admin
     */
    public function showUpgradeNotification(): void {
        if (!current_user_can('update_plugins')) {
            return;
        }
        
        $remote_version = $this->getRemoteVersion();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $message = sprintf(
                __('Menu Anchor Manager version %s is available. <a href="%s">Update now</a>.', 'menu-anchor-manager'),
                $remote_version,
                wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_file), 'upgrade-plugin_' . $this->plugin_file)
            );
            
            echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
        }
    }
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
    private MenuAnchorUpdater $updater;
    private static ?MenuAnchorManager $instance = null;
    
    private function __construct() {
        $this->service = new MenuAnchorService();
        $this->updater = new MenuAnchorUpdater(MENU_ANCHOR_MANAGER_FILE, MENU_ANCHOR_MANAGER_VERSION);
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
        add_filter('plugin_action_links_' . plugin_basename(MENU_ANCHOR_MANAGER_FILE), [$this, 'addActionLinks']);
    }
    
    /**
     * Translate plugin description in plugins list
     */
    public function translatePluginMeta(array $plugin_meta, string $plugin_file): array {
        if (plugin_basename(MENU_ANCHOR_MANAGER_FILE) === $plugin_file) {
            $plugin_meta[1] = __('Add dynamic anchors to WordPress menu items with automatic URL updates', 'menu-anchor-manager');
        }
        
        return $plugin_meta;
    }
    
    /**
     * Add custom links to plugin row (changelog, support, etc.)
     */
    public function addPluginLinks(array $plugin_meta, string $plugin_file): array {
        if (plugin_basename(MENU_ANCHOR_MANAGER_FILE) === $plugin_file) {
            $plugin_meta[] = '<a href="#" onclick="MenuAnchorManager.showChangelog(); return false;">' . __('View Changelog', 'menu-anchor-manager') . '</a>';
            $plugin_meta[] = '<a href="https://www.hylbee.fr/support" target="_blank">' . __('Support', 'menu-anchor-manager') . '</a>';
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
            '1.2.0' => [
                'date' => '2025-07-24',
                'changes' => [
                    'added' => [
                        __('Automatic update system via GitHub releases', 'menu-anchor-manager'),
                        __('Smart caching system for update checks (12-hour cache)', 'menu-anchor-manager'),
                        __('Admin notifications for available updates', 'menu-anchor-manager'),
                        __('Plugin information popup with GitHub changelog integration', 'menu-anchor-manager'),
                        __('Comprehensive error handling for network requests', 'menu-anchor-manager')
                    ],
                    'improved' => [
                        __('Enhanced plugin architecture with constants and better organization', 'menu-anchor-manager'),
                        __('Added activation/deactivation/uninstall hooks with proper cleanup', 'menu-anchor-manager'),
                        __('Better version checking and compatibility validation', 'menu-anchor-manager')
                    ]
                ]
            ],
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
        
        /* Update notification styles */
        .menu-anchor-update-notice {
            background: #fff;
            border-left: 4px solid #00a0d2;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
            margin: 5px 15px 2px;
            padding: 1px 12px;
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
        load_plugin_textdomain('menu-anchor-manager', false, dirname(plugin_basename(MENU_ANCHOR_MANAGER_FILE)) . '/languages');
    }
}

// Initialize the plugin
add_action('init', function() {
    MenuAnchorManager::getInstance();
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

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function() {
    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Menu Anchor Manager requires WordPress 5.0 or higher.', 'menu-anchor-manager'));
    }
    
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Menu Anchor Manager requires PHP 7.4 or higher.', 'menu-anchor-manager'));
    }
    
    // Clear update transients
    delete_transient('menu_anchor_manager_remote_version');
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clear update transients
    delete_transient('menu_anchor_manager_remote_version');
});

/**
 * Plugin uninstall hook (in separate uninstall.php file if needed)
 */
register_uninstall_hook(__FILE__, function() {
    // Clean up plugin data if user chooses to delete plugin
    global $wpdb;
    
    // Remove all menu anchor meta data
    $wpdb->delete(
        $wpdb->postmeta,
        ['meta_key' => '_menu_item_anchor'],
        ['%s']
    );
    
    // Clear transients
    delete_transient('menu_anchor_manager_remote_version');
});