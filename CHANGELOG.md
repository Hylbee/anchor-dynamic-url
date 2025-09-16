# Changelog

All notable changes to Anchor Dynamic URL will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Nothing yet

## [1.3.2] - 2025-09-16

### Fixed
- **Critical Security Fix**: Removed dangerous `afterInstall` function that was causing file system corruption
  - Fixed issue where the plugin would randomly move and deactivate other WordPress plugins
  - Removed erroneous `upgrader_post_install` filter hook that was interfering with WordPress core update system
  - Eliminated file movement operations that could corrupt plugin installations

### Removed
- Add anchor ID control to all elements (Advanced tab) 
  - Removed due to duplicate functionality with Elementor's native ID field
  - Simplified user interface by avoiding redundancy

## [1.3.1] - 2025-08-01

### Fixed
- **WordPress Coding Standards**: Fixed all Plugin Checker warnings and errors
  - Fixed text domain mismatches (changed 'elementor' to 'anchor-dynamic-url')
  - Added proper input validation and sanitization for `$_SERVER['REQUEST_URI']`
  - Fixed missing nonce verification in menu save operations
  - Replaced restricted `date()` function with WordPress `wp_date()`
  - Fixed slow database queries by removing inefficient meta_query usage
  - Added proper escaping for all output functions

### Improved
- **Security**: Enhanced input validation and sanitization throughout the plugin
  - Added `wp_unslash()` and `sanitize_text_field()` for all user inputs
  - Implemented proper nonce verification for form submissions
  - Added `isset()` checks for superglobal array access
- **Performance**: Optimized database operations
  - Replaced slow `meta_query` with direct WordPress functions
  - Improved plugin uninstall process for better performance
- **Code Quality**: Enhanced error handling and debugging
  - Added conditional `error_log()` only in WP_DEBUG mode
  - Added proper phpcs ignore comments for development functions

### Enhanced
- **Internationalization**: Complete translation system overhaul
  - Added comprehensive translator comments for all strings
  - Fixed missing translation strings throughout the plugin
  - Updated POT file with all current translatable strings
  - Completed French translation (fr_FR) with all new strings
  - Removed discouraged `load_plugin_textdomain()` (WordPress handles automatically)
- **Documentation**: Added extensive English comments throughout codebase
  - Added detailed function and class documentation
  - Explained complex logic with inline comments
  - Added parameter descriptions and return types
  - Enhanced code readability for future developers

### Technical
- **WordPress Compliance**: Full compliance with WordPress Plugin Directory standards
- **Plugin Checker**: Resolved all warnings and errors from WordPress Plugin Checker
- **Translation Ready**: Plugin now fully ready for WordPress.org translation system
- **Maintainability**: Improved code organization with comprehensive documentation

## [1.3.0] - 2025-07-31

### Added
- **Elementor Integration**: Added anchor support for Elementor elements
  - New input field for all elements with URL controls
  - Supports both Elementor's native ID and custom anchors
  - Supports all URL types (internal, external, custom)

## [1.2.3] - 2025-07-24

### Improved
- **Code cleanup**: Removed duplicate changelog functionality in favor of GitHub integration
- **Simplified architecture**: Eliminated unused modal scripts and CSS for changelog display
- **Better user experience**: Direct links to GitHub changelog instead of modal popup
- **Performance**: Reduced plugin size by removing unnecessary JavaScript and CSS

### Changed
- **Changelog links**: Now redirect directly to GitHub CHANGELOG.md for the specific version
- **Support links**: Consolidated all support to GitHub Issues
- **Admin interface**: Simplified plugin row links with direct GitHub integration

### Removed
- Internal changelog modal system (redundant with GitHub)
- Modal JavaScript and CSS styles
- `getChangelog()`, `enqueueChangelogScript()`, and `generateChangelogHTML()` methods
- Duplicate changelog maintenance burden

### Technical
- Fixed PHP syntax error in plugin links (backticks → proper concatenation)
- Streamlined `enqueueAdminStyles()` to only handle menu styles
- Reduced code complexity and maintenance overhead

## [1.2.2] - 2025-07-24

### Fixed
- **Sanitization**: Fixed anchor sanitization not applying during menu save
- **User Experience**: Anchors are now properly sanitized immediately when saving menus
- **Consistency**: Users now see sanitized anchors directly in the interface after saving

### Improved
- **Code Quality**: Refactored sanitization logic to eliminate code duplication
- **Architecture**: Created `AnchorSanitizer` utility class for better code organization
- **Maintainability**: Enhanced domain-driven architecture with shared utilities
- **DRY Principle**: Single sanitization method used throughout the plugin

### Technical
- Introduced `AnchorSanitizer` utility class with static `sanitize()` method
- Removed duplicated sanitization code from `AnchorItem` and `AnchorService`
- Improved separation of concerns with dedicated utility classes

## [1.2.1] - 2025-07-24

### Fixed
- **Critical**: Fixed fatal error with plugin activation hooks
- **Compatibility**: Replaced anonymous functions with named functions for WordPress compatibility
- **Stability**: Resolved Closure serialization error that prevented plugin activation

### Technical
- Converted `register_activation_hook`, `register_deactivation_hook`, and `register_uninstall_hook` to use named functions
- Improved WordPress core compatibility for hook serialization
- Enhanced plugin stability during activation/deactivation cycles

## [1.2.0] - 2025-07-24

### Added
- **Automatic update system**: Complete GitHub-based update mechanism
  - Smart caching system for update checks (12-hour cache)
  - Admin notifications for available updates
  - Plugin information popup with GitHub changelog integration
  - Comprehensive error handling for network requests
- **Enhanced plugin lifecycle management**:
  - Activation hooks with WordPress/PHP version checking
  - Deactivation hooks with proper cleanup
  - Uninstall hooks for complete data removal

### Improved
- **Plugin architecture**: Added constants and better code organization
- **Version management**: Enhanced compatibility validation system
- **Performance**: Intelligent caching to reduce API calls
- **User experience**: Seamless update notifications and one-click updates

### Technical
- Added `AnchorDynamicUrlUpdater` class with full GitHub integration
- Implemented WordPress update API compatibility
- Enhanced error handling and network request management
- Added comprehensive plugin metadata support

## [1.1.0] - 2025-07-24

### Changed
- **Enhanced anchor sanitization**: Improved security and formatting
  - Added protection against path traversal (`/`, `\`) and query parameters (`?`)
  - Automatic conversion of spaces, tabs, and line breaks to hyphens
  - Removal of multiple consecutive hyphens
  - Trimming of leading/trailing hyphens
  - **Case sensitivity preserved** - anchors now maintain original capitalization
- **Plugin metadata**: Added comprehensive WordPress plugin headers
  - WordPress version compatibility info
  - PHP requirements specification
  - Author and license details
  - Update URI for future automatic updates

### Security
- **Input validation strengthened**: Enhanced protection against injection attacks
- **Character filtering improved**: More robust sanitization while preserving functionality

### Technical
- Better compliance with WordPress Plugin Directory standards
- Enhanced plugin discoverability and metadata

## [1.0.0] - 2025-07-24

### Added
- **Core functionality**: Dynamic anchor management for WordPress menu items
- **Domain-driven architecture** with clean separation of concerns:
  - `AnchorItem` entity for business logic
  - `AnchorRepository` for data persistence
  - `AnchorService` for application logic
  - `AnchorDynamicUrlManager` as main orchestrator
- **Admin interface**: Custom anchor field in menu item edit form
- **Automatic URL generation**: Dynamic URLs that update when page slugs change
- **Basic security features**:
  - Input sanitization and XSS protection
  - Safe character filtering for URL anchors
- **Internationalization support**:
  - English as base language
  - French translation (`fr_FR`)
  - Spanish translation (`es_ES`)
  - Translation-ready template (`.pot` file)
- **Developer features**:
  - Helper function `get_menu_item_anchor()`
  - WordPress hooks integration
  - Clean uninstallation support
- **User experience**:
  - Intuitive admin interface with helpful descriptions
  - Real-time URL updates without manual intervention
  - Backward compatibility with existing menu items

### Technical Details
- **WordPress compatibility**: 5.0+
- **PHP requirements**: 7.4+
- **Database**: Uses WordPress native meta system
- **Performance**: Lightweight with minimal database queries
- **Architecture**: Object-oriented with dependency injection patterns

---

## Release Notes

### Version 1.0.0 - Initial Release

This is the first stable release of Menu Anchor Manager. The plugin provides a clean, secure, and user-friendly way to add dynamic anchors to WordPress menu items.

**Key Benefits:**
- ✅ **No more broken links** when page slugs change
- ✅ **Security-first approach** with input sanitization
- ✅ **Developer-friendly** architecture following WordPress best practices
- ✅ **Multilingual support** out of the box
- ✅ **Zero configuration** - works immediately after activation

**For Developers:**
The plugin follows domain-driven design principles and provides clean separation between business logic, data persistence, and presentation layers. It's built with extensibility in mind and follows WordPress coding standards.

**For End Users:**
Simply activate the plugin and start adding anchors to your menu items through the familiar WordPress menu interface. The plugin handles all the technical complexity behind the scenes.

---

## Upgrade Guide

### From No Plugin to 1.0.0
1. Download and install the plugin
2. Activate through WordPress admin
3. Navigate to Appearance > Menus
4. Add anchors to menu items using the new "Anchor" field
5. Save your menu - URLs will be automatically generated

---

## Support & Contributing

### Reporting Issues
Please report bugs and feature requests through the plugin's repository or support channels.

### Contributing
Contributions are welcome! Please follow these guidelines:
- Follow WordPress coding standards
- Maintain the domain-driven architecture
- Include appropriate translations
- Add tests for new functionality
- Update this changelog

### Development Setup
```bash
# Clone the repository
git clone [repository-url]

# Install in WordPress plugins directory
cp -r anchor-dynamic-url /path/to/wordpress/wp-content/plugins/

# Generate translation files
msgfmt languages/anchor-dynamic-url-fr_FR.po -o languages/anchor-dynamic-url-fr_FR.mo
```

---

## License

This plugin is licensed under GPL v2 or later.

---

## Acknowledgments

- Built with domain-driven design principles
- Inspired by modern web development practices
- Thanks to the WordPress community for best practices guidance