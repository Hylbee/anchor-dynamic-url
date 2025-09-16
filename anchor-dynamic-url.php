<?php
/**
 * Plugin Name: Anchor Dynamic URL
 * Plugin URI: https://www.hylbee.fr/
 * Description: This plugin allows you to add a anchor to menu element with a dynamic URL for WordPress menu items and for Elementor element.
 * Version: 1.3.1
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
 */

// Prevent direct access to this file for security
// WordPress defines ABSPATH constant when properly loaded
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly without WordPress
}

// Define plugin constants for use throughout the plugin
define('ANCHOR_DYNAMIC_URL_VERSION', '1.3.1');              // Current plugin version
define('ANCHOR_DYNAMIC_URL_FILE', __FILE__);                 // Full path to this main plugin file
define('ANCHOR_DYNAMIC_URL_PATH', plugin_dir_path(__FILE__)); // Plugin directory path with trailing slash
define('ANCHOR_DYNAMIC_URL_URL', plugin_dir_url(__FILE__));   // Plugin directory URL with trailing slash

/**
 * GitHub Updater Class - Handles automatic updates from GitHub releases
 * This class provides automatic plugin updates by checking GitHub releases
 * and integrating with WordPress's built-in update system
 */
class AnchorDynamicUrlUpdater {
    private string $plugin_file;   // Plugin basename (folder/file.php)
    private string $plugin_slug;   // Plugin directory name
    private string $version;       // Current plugin version
    private string $github_repo = 'Hylbee/anchor-dynamic-url'; // GitHub repository identifier
    private array $plugin_data;    // WordPress plugin header data
    
    /**
     * Initialize the updater with plugin information
     * 
     * @param string $plugin_file Full path to the main plugin file
     * @param string $version Current plugin version
     */
    public function __construct(string $plugin_file, string $version) {
        // Convert full path to plugin basename (folder/file.php format)
        $this->plugin_file = plugin_basename($plugin_file);
        // Extract plugin directory name from basename
        $this->plugin_slug = dirname($this->plugin_file);
        $this->version = $version;
        
        // Get plugin header data for update information
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data = get_plugin_data($plugin_file);
        
        // Initialize WordPress hooks for update system integration
        $this->initHooks();
    }
    
    /**
     * Initialize WordPress hooks for update system
     * Connects our custom updater to WordPress's plugin update mechanisms
     */
    private function initHooks(): void {
        // Hook into plugin update check transient to inject our update info
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkForUpdate']);
        // Handle plugin information popup in admin when "View details" is clicked
        add_filter('plugins_api', [$this, 'pluginPopup'], 10, 3);
        // Show update notification in admin dashboard
        add_action('admin_init', [$this, 'showUpgradeNotification']);
    }
    
    /**
     * Check for plugin updates by comparing local and remote versions
     * This method is called by WordPress during its regular update checks
     * 
     * @param object $transient The update transient object from WordPress
     * @return object Modified transient with our plugin update info if available
     */
    public function checkForUpdate(object $transient): object {
        // Skip if no plugins are being checked
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Get the latest version from GitHub
        $remote_version = $this->getRemoteVersion();
        
        // If remote version is newer, add update information to transient
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_file] = (object) [
                'slug' => $this->plugin_slug,                    // Plugin directory name
                'plugin' => $this->plugin_file,                  // Plugin basename
                'new_version' => $remote_version,                // New version available
                'url' => "https://github.com/{$this->github_repo}", // Plugin homepage
                'package' => $this->getDownloadUrl($remote_version), // Download URL
                'icons' => [],                                   // Plugin icons (empty for now)
                'banners' => [],                                 // Plugin banners (empty for now)
                'banners_rtl' => [],                            // RTL banners (empty for now)
                'tested' => $this->plugin_data['RequiresWP'] ?? '6.4', // WordPress compatibility
                'requires_php' => $this->plugin_data['RequiresPHP'] ?? '7.4', // PHP requirement
                'compatibility' => new stdClass()               // Compatibility data
            ];
        }
        
        return $transient;
    }
    
    /**
     * Get remote version from GitHub API with caching
     * Fetches the latest release version from GitHub and caches it to reduce API calls
     * 
     * @return string The remote version number, or current version if fetch fails
     */
    private function getRemoteVersion(): string {
        $transient_key = 'anchor_dynamic_url_remote_version';
        // Check if we have a cached version first
        $cached_version = get_transient($transient_key);
        
        if ($cached_version !== false) {
            return $cached_version; // Return cached version to avoid API calls
        }
        
        // Make API request to GitHub for latest release information
        $request = wp_remote_get("https://api.github.com/repos/{$this->github_repo}/releases/latest", [
            'timeout' => 10, // 10 second timeout
            'headers' => [
                'Accept' => 'application/vnd.github+json', // GitHub API v3 format
                // Proper User-Agent for GitHub API (required)
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            ]
        ]);
        
        // Process successful API response
        if (!is_wp_error($request) && wp_remote_retrieve_response_code($request) === 200) {
            $body = wp_remote_retrieve_body($request);
            $data = json_decode($body, true);
            
            // Extract version from tag_name (e.g., "v1.3.0" -> "1.3.0")
            if (isset($data['tag_name'])) {
                $version = ltrim($data['tag_name'], 'v'); // Remove 'v' prefix if present
                // Cache for 12 hours to reduce API calls
                set_transient($transient_key, $version, 12 * HOUR_IN_SECONDS);
                return $version;
            }
        }
        
        // Return current version if API call fails
        return $this->version;
    }
    
    /**
     * Get download URL for specific version from GitHub releases
     * Constructs the direct download URL for the plugin ZIP file
     * 
     * @param string $version The version to download
     * @return string Direct download URL for the plugin ZIP
     */
    private function getDownloadUrl(string $version): string {
        return "https://github.com/{$this->github_repo}/releases/download/v{$version}/anchor-dynamic-url.zip";
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
            /* translators: Error message when changelog cannot be fetched from GitHub */
            return __('Unable to fetch changelog.', 'anchor-dynamic-url');
        }
        
        $body = wp_remote_retrieve_body($request);
        $releases = json_decode($body, true);
        
        if (!is_array($releases)) {
            /* translators: Message shown when no changelog data is available */
            return __('No changelog available.', 'anchor-dynamic-url');
        }
        
        $changelog = '<div class="menu-anchor-changelog">';
        
        foreach (array_slice($releases, 0, 5) as $release) { // Show last 5 releases
            $version = ltrim($release['tag_name'], 'v');
            $date = wp_date('F j, Y', strtotime($release['published_at']));
            /* translators: Message shown when a release has no notes */
            $body = $release['body'] ? wp_kses_post($release['body']) : __('No release notes available.', 'anchor-dynamic-url');
            
            $changelog .= '<h4>' . sprintf(
                /* translators: 1: version number, 2: release date */
                esc_html__('Version %1$s - %2$s', 'anchor-dynamic-url'),
                esc_html($version),
                esc_html($date)
            ) . '</h4>';
            $changelog .= '<div>' . wp_kses_post($body) . '</div>';
        }
        
        $changelog .= '</div>';
        
        return $changelog;
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
                /* translators: 1: version number, 2: update URL */
                __('Anchor Dynamic URL version %1$s is available. <a href="%2$s">Update now</a>.', 'anchor-dynamic-url'),
                $remote_version,
                wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . $this->plugin_file), 'upgrade-plugin_' . $this->plugin_file)
            );
            
            echo '<div class="notice notice-warning is-dismissible"><p>' . wp_kses_post($message) . '</p></div>';
        }
    }
}

/**
 * Utility class for anchor sanitization
 * Provides secure sanitization of anchor strings to prevent XSS and ensure valid HTML/CSS identifiers
 */
class AnchorSanitizer {
    /**
     * Sanitize anchor to be URL-safe and secure
     * Converts user input into a safe anchor identifier for use in URLs and HTML
     * 
     * @param string|null $anchor Raw anchor input from user
     * @return string|null Sanitized anchor or null if invalid/empty
     */
    public static function sanitize(?string $anchor): ?string {
        if (empty($anchor)) {
            return null;
        }
        
        // Remove everything after dangerous characters for security
        // This prevents path traversal and query injection attacks
        $anchor = trim($anchor);
        $anchor = preg_split('/[?\/\\\\]/', $anchor)[0]; // Split on ?, /, \\ and take first part
        
        // Convert whitespace characters to hyphens for URL compatibility
        $anchor = str_replace([' ', '\t', '\n', '\r'], '-', $anchor);
        
        // Remove any characters that aren't valid in HTML IDs or CSS selectors
        // Only allow letters, numbers, hyphens, and underscores
        $anchor = preg_replace('/[^a-zA-Z0-9\-_]/', '', $anchor);
        
        // Clean up multiple consecutive hyphens (keep case sensitivity)
        $anchor = preg_replace('/-+/', '-', $anchor);
        
        // Remove leading/trailing hyphens for cleaner anchors
        $anchor = trim($anchor, '-');
        
        // Return null if empty after sanitization (invalid input)
        return !empty($anchor) ? $anchor : null;
    }
}

/**
 * Domain Entity - Represents a menu item with anchor capability
 * This class encapsulates the data and behavior of a menu item that can have an anchor
 * It follows Domain-Driven Design principles for clean architecture
 */
class AnchorItem {
    private int $menuItemId;      // WordPress menu item ID
    private ?string $anchor;      // Sanitized anchor string (without #)
    private ?int $targetPageId;   // Target page ID if linking to a specific page
    private string $originalUrl;  // Original URL before anchor modification
    
    /**
     * Create a new menu item anchor entity
     * 
     * @param int $menuItemId WordPress menu item ID
     * @param string|null $anchor Raw anchor string (will be sanitized)
     * @param int|null $targetPageId Target page ID for dynamic URL generation
     * @param string $originalUrl Original menu item URL
     */
    public function __construct(int $menuItemId, ?string $anchor = null, ?int $targetPageId = null, string $originalUrl = '') {
        $this->menuItemId = $menuItemId;
        $this->anchor = AnchorSanitizer::sanitize($anchor); // Sanitize anchor on creation
        $this->targetPageId = $targetPageId;
        $this->originalUrl = $originalUrl;
    }
    
    /**
     * Generate the final URL with anchor if present
     * This method constructs the complete URL including the anchor fragment
     * 
     * @return string Complete URL with anchor, or original URL if no anchor
     */
    public function generateUrl(): string {
        // Return original URL if no anchor is set
        if (empty($this->anchor)) {
            return $this->originalUrl;
        }
        
        // If we have a target page, use its permalink for dynamic URLs
        // This ensures the URL stays current even if the page slug changes
        if ($this->targetPageId) {
            $baseUrl = get_permalink($this->targetPageId);
        } else {
            // Remove existing anchor from original URL to avoid duplication
            $baseUrl = strtok($this->originalUrl, '#');
        }
        
        // Combine base URL with anchor fragment
        return $baseUrl . '#' . $this->anchor;
    }
    
    /**
     * Get the anchor value
     * 
     * @return string|null The sanitized anchor string, or null if not set
     */
    public function getAnchor(): ?string {
        return $this->anchor;
    }
}

/**
 * Repository - Handles data persistence for menu anchors
 * This class follows the Repository pattern to abstract database operations
 * It provides a clean interface for storing and retrieving menu anchor data
 */
class AnchorRepository {
    private const META_KEY = '_menu_item_anchor'; // WordPress meta key for storing anchor data
    
    /**
     * Save anchor for a menu item
     * Stores the anchor data in WordPress post meta table
     * 
     * @param int $menuItemId Menu item ID to save anchor for
     * @param string|null $anchor Anchor string to save, or null to delete
     * @return bool True on success, false on failure
     */
    public function saveAnchor(int $menuItemId, ?string $anchor): bool {
        // Delete meta if anchor is empty
        if (empty($anchor)) {
            return delete_post_meta($menuItemId, self::META_KEY);
        }
        
        // Save sanitized anchor to post meta
        return update_post_meta($menuItemId, self::META_KEY, sanitize_text_field($anchor));
    }
    
    /**
     * Get anchor for a menu item
     * Retrieves anchor data from WordPress post meta table
     * 
     * @param int $menuItemId Menu item ID to get anchor for
     * @return string|null Anchor string or null if not set
     */
    public function getAnchor(int $menuItemId): ?string {
        $anchor = get_post_meta($menuItemId, self::META_KEY, true);
        return !empty($anchor) ? $anchor : null;
    }
    
    /**
     * Create MenuItemAnchor entity from menu item
     * Factory method to create domain entity from WordPress menu item object
     * 
     * @param object $menuItem WordPress menu item object
     * @return AnchorItem Domain entity representing the menu item with anchor
     */
    public function createEntityFromMenuItem(object $menuItem): AnchorItem {
        // Get stored anchor data for this menu item
        $anchor = $this->getAnchor($menuItem->ID);
        $targetPageId = null;
        
        // Get target page ID if this menu item links to a page
        // This enables dynamic URL generation when page slugs change
        if ($menuItem->object === 'page' && !empty($menuItem->object_id)) {
            $targetPageId = (int) $menuItem->object_id;
        }
        
        // Create and return domain entity
        return new AnchorItem(
            $menuItem->ID,
            $anchor,
            $targetPageId,
            $menuItem->url
        );
    }
}

/**
 * Service - Handles the business logic for menu anchors
 * This class implements the Service pattern to coordinate between UI and data layers
 * It contains the core business logic for managing menu anchors
 */
class AnchorService {
    private AnchorRepository $repository; // Data access layer
    
    /**
     * Initialize the service with its dependencies
     */
    public function __construct() {
        $this->repository = new AnchorRepository();
    }
    
    /**
     * Add anchor field to menu item edit form
     * This method is called by WordPress when rendering menu item edit forms
     * It adds our custom anchor input field to each menu item
     * 
     * @param int $item_id Menu item ID
     * @param object $item Menu item object
     * @param int $depth Menu item depth (for nested menus)
     * @param object $args Additional arguments from WordPress
     */
    public function addAnchorField(int $item_id, object $item, int $depth, object $args): void {
        // Get existing anchor value for this menu item
        $anchor = $this->repository->getAnchor($item_id);
        
        // Output HTML for the anchor input field
        ?>
        <p class="field-anchor description description-wide">
            <label for="edit-menu-item-anchor-<?php echo esc_attr($item_id); ?>">
                <?php
                /* translators: Label for the anchor input field in menu editor */
                esc_html_e('Anchor (optional)', 'anchor-dynamic-url');
                ?><br>
                <!-- Anchor input field -->
                <input type="text" 
                       id="edit-menu-item-anchor-<?php echo esc_attr($item_id); ?>" 
                       class="widefat edit-menu-item-anchor" 
                       name="menu-item-anchor[<?php echo esc_attr($item_id); ?>]" 
                       value="<?php echo esc_attr($anchor ?? ''); ?>"
                       placeholder="<?php
                       /* translators: Example placeholder text for anchor input field */ 
                       esc_attr_e('e.g: contact-section', 'anchor-dynamic-url');
                       ?>">
            </label>
            <!-- Help text explaining the feature -->
            <span class="description">
                <?php
                /* translators: Help text explaining what the anchor field does */
                esc_html_e('Adds #anchor to the URL. Updates automatically if the page slug changes.', 'anchor-dynamic-url');
                ?>
            </span>
        </p>
        <?php
    }
    
    /**
     * Save anchor when menu item is updated
     * This method is called by WordPress when a menu is saved
     * It processes and saves the anchor data for each menu item
     * 
     * @param int $menu_id Menu ID (not used but required by WordPress hook)
     * @param int $menu_item_db_id Menu item ID being saved
     */
    public function saveAnchor(int $menu_id, int $menu_item_db_id): void {
        // Verify nonce for security
        if (!isset($_POST['update-nav-menu-nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['update-nav-menu-nonce'])), 'update-nav_menu')) {
            return;
        }
        
        // Check if anchor data was submitted for this menu item
        if (!isset($_POST['menu-item-anchor'][$menu_item_db_id])) {
            return;
        }
        
        // Get the submitted anchor value with proper sanitization
        $anchor = sanitize_text_field(wp_unslash($_POST['menu-item-anchor'][$menu_item_db_id]));
        
        // Additional sanitization for anchor format
        $sanitized_anchor = AnchorSanitizer::sanitize($anchor);
        
        // Save to database through repository
        $this->repository->saveAnchor($menu_item_db_id, $sanitized_anchor);
    }
    
    /**
     * Update menu item URL with anchor
     * This method is called for each menu item before display
     * It modifies the URL to include the anchor if one is set
     * 
     * @param object $menu_item WordPress menu item object
     * @return object Modified menu item with updated URL
     */
    public function updateMenuItemUrl(object $menu_item): object {
        // Create domain entity from menu item
        $anchorItem = $this->repository->createEntityFromMenuItem($menu_item);
        // Update the menu item's URL with anchor
        $menu_item->url = $anchorItem->generateUrl();
        
        return $menu_item;
    }
    
    /**
     * Handle bulk menu updates
     * This method processes all menu items at once for efficiency
     * Called when WordPress prepares menu objects for display
     * 
     * @param array $menu_items Array of WordPress menu item objects
     * @return array Array of modified menu items with updated URLs
     */
    public function handleBulkUpdate(array $menu_items): array {
        // Process each menu item to add anchors
        foreach ($menu_items as &$menu_item) {
            $menu_item = $this->updateMenuItemUrl($menu_item);
        }
        
        return $menu_items;
    }
}

/**
 * Main Plugin Class - Orchestrates the entire system
 * This is the main controller class that coordinates all plugin functionality
 * It implements the Singleton pattern to ensure only one instance exists
 */
class AnchorDynamicUrlManager {
    private AnchorService $service;   // Business logic service
    private AnchorDynamicUrlUpdater $updater;   // GitHub update handler
    private static ?AnchorDynamicUrlManager $instance = null; // Singleton instance
    
    /**
     * Private constructor to enforce singleton pattern
     * Initializes all dependencies and WordPress hooks
     */
    private function __construct() {
        $this->service = new AnchorService();
        $this->updater = new AnchorDynamicUrlUpdater(ANCHOR_DYNAMIC_URL_FILE, ANCHOR_DYNAMIC_URL_VERSION);
        $this->initHooks();
    }
    
    /**
     * Get the singleton instance of the plugin
     * 
     * @return AnchorDynamicUrlManager The plugin instance
     */
    public static function getInstance(): AnchorDynamicUrlManager {
        if (self::$instance === null) {
            self::$instance = new AnchorDynamicUrlManager();
        }
        
        return self::$instance;
    }
    
    /**
     * Initialize WordPress hooks
     * Connects all plugin functionality to WordPress action and filter hooks
     */
    private function initHooks(): void {
        // Core menu functionality hooks
        // Add custom anchor field to menu item edit forms
        add_action('wp_nav_menu_item_custom_fields', [$this->service, 'addAnchorField'], 10, 4);
        
        // Save anchor field data when menu is updated
        add_action('wp_update_nav_menu_item', [$this->service, 'saveAnchor'], 10, 2);
        
        // Modify individual menu item URLs before display (single item processing)
        add_filter('wp_setup_nav_menu_item', [$this->service, 'updateMenuItemUrl'], 10, 1);
        
        // Handle complete menu output (bulk processing for efficiency)
        add_filter('wp_nav_menu_objects', [$this->service, 'handleBulkUpdate'], 10, 1);
        
        // Admin interface hooks
        // Add custom CSS styles for admin menu interface
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminStyles']);
        
        // Internationalization is handled automatically for WordPress.org hosted plugins
        // No need to manually load text domain since WordPress 4.6+
        
        // Plugin management hooks
        // Add additional links in plugin list (changelog, support, etc.)
        add_filter('plugin_row_meta', [$this, 'addPluginLinks'], 10, 2);
        
        // Add action links in plugin list (settings, etc.)
        add_filter('plugin_action_links_' . plugin_basename(ANCHOR_DYNAMIC_URL_FILE), [$this, 'addActionLinks']);

        // Initialize additional plugin components
        $this->initIncludes();
    }

    /**
     * Initialize additional plugin components and integrations
     * Loads optional features and third-party integrations
     */
    private function initIncludes(): void {
        // Include additional files if needed

        // Elementor integration - adds anchor functionality to Elementor page builder
        // Only load if the file exists to prevent errors
        if (file_exists(ANCHOR_DYNAMIC_URL_PATH . 'includes/elementor-integration.php')) {
			require_once ANCHOR_DYNAMIC_URL_PATH . 'includes/elementor-integration.php';
		}
    }
    
    /**
     * Add custom links to plugin row (changelog, support, etc.)
     * Adds helpful links below the plugin description in the admin plugins list
     * 
     * @param array $plugin_meta Existing plugin meta links
     * @param string $plugin_file Current plugin file being processed
     * @return array Modified plugin meta links
     */
    public function addPluginLinks(array $plugin_meta, string $plugin_file): array {
        // Only add links for our plugin
        if (plugin_basename(ANCHOR_DYNAMIC_URL_FILE) === $plugin_file) {
            // Add changelog link
            /* translators: Link text for viewing the plugin changelog */
            $plugin_meta[] = '<a href="https://github.com/Hylbee/anchor-dynamic-url/blob/v' . ANCHOR_DYNAMIC_URL_VERSION . '/CHANGELOG.md" target="_blank"> ' . esc_html__('View Changelog', 'anchor-dynamic-url') . '</a>';
            // Add support link
            /* translators: Link text for getting support */
            $plugin_meta[] = '<a href="https://github.com/Hylbee/anchor-dynamic-url/issues" target="_blank">' . esc_html__('Support', 'anchor-dynamic-url') . '</a>';
            // Add GitHub repository link
            /* translators: Link text for GitHub repository */
            $plugin_meta[] = '<a href="https://github.com/Hylbee/anchor-dynamic-url" target="_blank">' . esc_html__('GitHub', 'anchor-dynamic-url') . '</a>';
        }
        
        return $plugin_meta;
    }
    
    /**
     * Add action links (Settings, etc.)
     * Adds action links next to "Activate/Deactivate" in the plugins list
     * 
     * @param array $links Existing action links
     * @return array Modified action links with our additions
     */
    public function addActionLinks(array $links): array {
        $action_links = [
            // Add link to menu configuration page
            /* translators: Link text for configuring menus */
            'settings' => '<a href="' . esc_url(admin_url('nav-menus.php')) . '">' . esc_html__('Configure Menus', 'anchor-dynamic-url') . '</a>'
        ];
        
        // Prepend our links to the existing ones
        return array_merge($action_links, $links);
    }
    
    /**
     * Enqueue admin styles for better UX
     * Adds custom CSS to improve the appearance of anchor fields in menu editor
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueueAdminStyles($hook): void {
        // Only load styles on the nav-menus.php page
        if ($hook !== 'nav-menus.php') {
            return;
        }
        
        // Custom CSS for anchor field styling
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
        
        // Add CSS inline to the nav-menu stylesheet
        wp_add_inline_style('nav-menu', $css);
    }
    
    // Text domain loading removed - WordPress handles this automatically for .org hosted plugins since 4.6+
}

// Initialize the plugin when WordPress is ready
// Using 'init' hook ensures WordPress core is fully loaded
add_action('init', function() {
    AnchorDynamicUrlManager::getInstance(); // Get singleton instance and start the plugin
});

// Helper function for developers (optional)
// Only define if not already defined to prevent conflicts
if (!function_exists('get_menu_item_anchor')) {
    /**
     * Get anchor for a specific menu item
     * Public API function for theme and plugin developers
     * 
     * @param int $menu_item_id WordPress menu item ID
     * @return string|null Anchor string or null if not set
     */
    function get_menu_item_anchor(int $menu_item_id): ?string {
        $repository = new AnchorRepository();
        return $repository->getAnchor($menu_item_id);
    }
}

/**
 * Plugin activation hook
 * Runs when the plugin is activated to check system requirements
 */
register_activation_hook(__FILE__, 'anchor_dynamic_url_activate');

/**
 * Plugin activation callback
 * Verifies system requirements and performs initial setup
 */
function anchor_dynamic_url_activate() {
    // Check WordPress version compatibility
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        /* translators: Error message shown when WordPress version is too old */
        wp_die(esc_html__('Anchor Dynamic URL requires WordPress 5.0 or higher.', 'anchor-dynamic-url'));
    }
    
    // Check PHP version compatibility
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        /* translators: Error message shown when PHP version is too old */
        wp_die(esc_html__('Anchor Dynamic URL requires PHP 7.4 or higher.', 'anchor-dynamic-url'));
    }
    
    // Clear cached update information to force fresh check
    delete_transient('anchor_dynamic_url_remote_version');
}

/**
 * Plugin deactivation hook
 * Runs when the plugin is deactivated to clean up temporary data
 */
register_deactivation_hook(__FILE__, 'anchor_dynamic_url_deactivate');

/**
 * Plugin deactivation callback
 * Cleans up temporary data but preserves user data
 */
function anchor_dynamic_url_deactivate() {
    // Clear cached update information
    delete_transient('anchor_dynamic_url_remote_version');
}

/**
 * Plugin uninstall hook
 * Runs when the plugin is completely removed from WordPress
 */
register_uninstall_hook(__FILE__, 'anchor_dynamic_url_uninstall');

/**
 * Plugin uninstall callback
 * Removes all plugin data from the database when plugin is deleted
 * This is only called when user chooses to delete the plugin permanently
 */
function anchor_dynamic_url_uninstall() {
    // Clear any remaining transient data
    delete_transient('anchor_dynamic_url_remote_version');
    
    // Remove all menu anchor meta data using WordPress functions
    // Get all nav menu items first, then check for our meta key
    $menu_items = get_posts([
        'post_type' => 'nav_menu_item',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ]);
    
    // Delete anchor meta for each menu item (WordPress will handle non-existent meta gracefully)
    foreach ($menu_items as $menu_item_id) {
        delete_post_meta($menu_item_id, '_menu_item_anchor');
    }
}