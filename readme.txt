=== Anchor Dynamic URL ===
Tags: menu, anchor, url, dynamic, elementor
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.5.1
Version: 1.5.1
Requires PHP: 7.4
Author: Hylbee
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
This plugin allows you to add a anchor to menu element with a dynamic URL for WordPress menu items and for Elementor element.

== Description ==
Anchor Dynamic URL lets you add anchor targets to WordPress menu items with dynamic URLs that update automatically when page slugs change. It also extends Elementor with a dedicated "Target Anchor" field on every URL control, supporting widgets, sections, columns, and containers with link wrapping.

All anchor inputs are strictly sanitized (letters, numbers, hyphens, underscores only) and the plugin follows WordPress coding standards throughout.

**WordPress menu features**
* "Anchor (optional)" field on every menu item
* URL auto-updates when the linked page slug changes
* Anchor is stripped on delete / deactivation

**Elementor integration**
* "Target Anchor" field added to every URL control in the editor
* Supports widgets, sections, columns, and linked containers (`<a>` tag)
* Works with internal URLs, external URLs, and empty `#` links
* Output-buffer-safe rewriting with `ob_get_level()` verification

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/anchor-dynamic-url` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Appearance > Menus to start adding anchors to your menu items.
4. For Elementor users, anchor options will automatically appear in URL controls.

== Frequently Asked Questions ==

= How do I add an anchor to a menu item? =

Go to Appearance > Menus, edit any menu item, and you'll see a new "Anchor (optional)" field. Enter your anchor ID without the # symbol.

= What happens if I change my page slug? =

The plugin automatically updates the URL to maintain the correct anchor link, so your anchors will continue to work even if page slugs change.

= Does this work with Elementor? =

Yes! The plugin extends Elementor's URL controls to include anchor targeting options for all elements.

= Is the plugin secure? =

Yes, all anchor inputs are sanitized and validated to prevent XSS attacks and ensure URL safety.

== Screenshots ==

1. Menu editor showing the new anchor field
2. Elementor integration with anchor options
3. Frontend anchor links in action

== Changelog ==

= 1.5.1 - 2026-05-19 =
* Fixed: Use fully qualified \stdClass in AnchorDynamicUrlUpdater compatibility array to prevent class resolution issues in namespaced contexts

= 1.5.0 - 2026-05-19 =
* Added: Extended Elementor URL control — new AnchorUrlControl class embeds the anchor_target field natively inside the URL control
* Refactored: Elementor integration rewritten with cleaner architecture using the extended URL control approach
* Fixed: Update notifications no longer fire during AJAX requests or WP-Cron jobs
* Fixed: Elementor integration file renamed to class-elementor-integration.php; loader reference updated
* Compatibility: Tested on WordPress 7.0 prerelease

= 1.4.0 - 2025-09-16 =
* Added: Elementor container link rewriting via output buffering (supports HTML tag = 'a' containers)
* Added: Widget render_content filter to rewrite all URL controls in rendered HTML, including HTML-encoded URL variants
* Added: Fallback template injection for Extended_URL_Control when Elementor template structure changes
* Added: ob_get_level() safety guard to prevent output buffer corruption
* Improved: anchor_target option injection via before_section_end for all existing URL controls
* Security: Replaced preg_replace with preg_replace_callback for container link rewriting — prevents backreference injection
* Security: Added current_user_can('edit_theme_options') check in save_anchor() alongside existing nonce verification

= 1.3.2 - 2025-09-15 =
* Fixed: Removed post-installation cleanup handling from AnchorDynamicUrlUpdater class
* Removed: Add anchor ID control to all elements (Advanced tab) - this was causing confusion

= 1.3.1 - 2025-08-01 =
* Fixed: WordPress Coding Standards compliance - resolved all Plugin Checker warnings
* Fixed: Text domain mismatches (changed 'elementor' to 'menu-anchor-manager')
* Fixed: Input validation and sanitization for $_SERVER['REQUEST_URI']
* Fixed: Missing nonce verification in menu save operations
* Fixed: Replaced restricted date() function with WordPress wp_date()
* Fixed: Slow database queries by removing inefficient meta_query usage
* Improved: Security with enhanced input validation throughout the plugin
* Improved: Performance with optimized database operations
* Enhanced: Complete internationalization system with comprehensive translator comments
* Enhanced: Documentation with extensive English comments throughout codebase
* Enhanced: French translation (fr_FR) completed with all new strings
* Technical: Full compliance with WordPress Plugin Directory standards
* Technical: Zero Plugin Checker warnings or errors

= 1.3.0 - 2025-07-31 =
* Added: Elementor Integration with anchor support for all elements
* Added: New input field for all elements with URL controls
* Added: Support for both Elementor's native ID and custom anchors
* Added: Support for all URL types (internal, external, custom)

= 1.2.3 - 2025-07-24 =
* Improved: Code cleanup and simplified architecture
* Improved: Better user experience with direct GitHub integration
* Changed: Changelog links now redirect to GitHub CHANGELOG.md
* Removed: Internal changelog modal system
* Fixed: PHP syntax error in plugin links

= 1.2.2 - 2025-07-24 =
* Fixed: Anchor sanitization not applying during menu save
* Improved: Code quality with refactored sanitization logic
* Technical: Introduced AnchorSanitizer utility class

= 1.2.1 - 2025-07-24 =
* Fixed: Critical fatal error with plugin activation hooks
* Fixed: Compatibility issues with anonymous functions
* Technical: Improved WordPress core compatibility

= 1.2.0 - 2025-07-24 =
* Added: Automatic update system with GitHub integration
* Added: Enhanced plugin lifecycle management
* Improved: Plugin architecture and version management
* Technical: Added AnchorDynamicUrlUpdater class

= 1.1.0 - 2025-07-24 =
* Changed: Enhanced anchor sanitization for improved security
* Security: Strengthened input validation
* Technical: Better WordPress Plugin Directory compliance

= 1.0.0 - 2025-07-24 =
* Initial release
* Added: Core dynamic anchor management functionality
* Added: Domain-driven architecture with clean separation
* Added: Admin interface with custom anchor field
* Added: Automatic URL generation with slug change support
* Added: Internationalization support (English, French, Spanish)
* Added: Developer helper functions
* Security: Input sanitization and XSS protection