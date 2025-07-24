# Changelog

All notable changes to Menu Anchor Manager will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
- No changes yet.

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
  - `MenuItemAnchor` entity for business logic
  - `MenuAnchorRepository` for data persistence
  - `MenuAnchorService` for application logic
  - `MenuAnchorManager` as main orchestrator
- **Admin interface**: Custom anchor field in menu item edit form
- **Automatic URL generation**: Dynamic URLs that update when page slugs change
- **Basic security features**:
  - Input sanitization and XSS protection
  - Safe character filtering for URL anchors
- **Internationalization support**:
  - English as base language
  - French translation (`fr_FR`)
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
git clone https://github.com/Hylbee/menu-anchor-manager

# Install in WordPress plugins directory
cp -r menu-anchor-manager /path/to/wordpress/wp-content/plugins/

# Generate translation files
msgfmt languages/menu-anchor-manager-fr_FR.po -o languages/menu-anchor-manager-fr_FR.mo
```

---

## License

This plugin is licensed under GPL v2 or later.

---

## Acknowledgments

- Built with domain-driven design principles
- Inspired by modern web development practices
- Thanks to the WordPress community for best practices guidance