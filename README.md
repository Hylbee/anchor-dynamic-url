# Anchor Dynamic URL

A WordPress plugin that adds dynamic anchor management to menu items with automatic URL updates when page slugs change.

[![WordPress](https://img.shields.io/badge/WordPress-5.3%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.5.2-orange.svg)](https://github.com/Hylbee/anchor-dynamic-url/releases)

## Description

Anchor Dynamic URL solves a common WordPress problem: broken anchor links when page slugs change. Instead of manually writing URLs like `https://example.com/contact#coordinates`, you simply select a page and add an anchor. The plugin automatically generates the correct URL and updates it if the page slug changes.

## Features

### ✨ Core Features
- **Dynamic anchor management** for WordPress menu items
- **Automatic URL updates** when page slugs change
- **GitHub-based update system** with automatic notifications
- **Security-first approach** with input sanitization
- **Case-sensitive anchors** preserved as entered
- **Clean, intuitive interface** integrated into WordPress menus
- **Full Elementor integration**: widgets, sections, columns, and linked containers

### 🛡️ Security
- Protection against path traversal and query parameter injection
- XSS prevention through proper output escaping
- Safe character filtering for URL anchors (`a-zA-Z0-9-_` only)
- Input validation and sanitization on all user inputs
- Nonce verification + capability check (`edit_theme_options`) on menu saves
- `preg_replace_callback` used throughout to prevent regex backreference injection

### 🌍 Internationalization
- **Multi-language support** out of the box
- Available in: English (default), French, Spanish
- Translation-ready with complete `.pot` template
- RTL languages supported

### 🏗️ Developer-Friendly
- **Domain-driven architecture** with clean separation of concerns
- WordPress coding standards compliant
- Extensible design with proper hooks
- Helper functions for developers
- Comprehensive documentation

## Installation

### From WordPress Admin
1. Go to **Plugins > Add New**
2. Search for "Anchor Dynamic URL"
3. Install and activate the plugin

### Manual Installation
1. Download the plugin zip file
2. Upload to `/wp-content/plugins/anchor-dynamic-url/`
3. Activate through the WordPress plugins screen

### From Source
```bash
# Clone the repository
git clone https://github.com/Hylbee/anchor-dynamic-url.git

# Copy the plugin folder to the WordPress plugins directory
cp -r anchor-dynamic-url/anchor-dynamic-url /path/to/wordpress/wp-content/plugins/

# Generate translation files (optional)
cd anchor-dynamic-url/anchor-dynamic-url/languages
msgfmt anchor-dynamic-url-fr_FR.po -o anchor-dynamic-url-fr_FR.mo
```

## Usage

### Basic Usage
1. Go to **Appearance > Menus** in WordPress admin
2. Edit or create a menu item
3. In the menu item details, you'll see a new **"Anchor (optional)"** field
4. Enter your anchor (e.g., `contact-section`)
5. Save the menu

The plugin will automatically generate the URL: `https://yoursite.com/page#contact-section`

### Elementor Integration

**Widgets, sections & columns**
1. Edit an Elementor page
2. Select any element that has a URL control (button, heading, icon, etc.)
3. Open the URL field settings
4. Enter your anchor in the **"Target Anchor"** field (e.g., `contact-section`)
5. Save — the `#anchor` is appended to the URL at render time

Links inside repeater items (Icon List, Social Icons, Price List, etc.) work the same way: each item has its own **"Target Anchor"** field. Several items may point to the same page with different anchors.

**Containers with link wrapping**
1. Select a Container element
2. Set **HTML Tag** to `a` and enter a URL in the link field
3. Enter the anchor in the **"Target Anchor"** field
4. The container's `href` is rewritten via output buffering at render time

### Advanced Usage

#### Anchor Naming Conventions
The plugin supports various naming conventions:
- **camelCase**: `contactSection`
- **PascalCase**: `ContactSection`
- **kebab-case**: `contact-section`
- **snake_case**: `contact_section`

#### Security Features
Input is automatically sanitized:
- `"Contact Section"` → `"Contact-Section"`
- `"section?param=1"` → `"section"`
- `"test/path"` → `"test"`
- `"anchor\\injection"` → `"anchor"`

#### Developer Helper
```php
// Get anchor for a specific menu item
$anchor = get_menu_item_anchor($menu_item_id);
if ($anchor) {
    echo "Anchor: " . $anchor;
}
```

## Architecture

The plugin follows domain-driven design principles:

```
┌─────────────────────┐
│ AnchorDynamicUrlManager │  ← Main orchestrator
└─────────┬───────────┘
          │
┌─────────▼───────────┐
│    AnchorService    │  ← Business logic
└─────────┬───────────┘
          │
┌─────────▼───────────┐
│  AnchorRepository   │  ← Data persistence
└─────────┬───────────┘
          │
┌─────────▼───────────┐
│   MenuItemAnchor    │  ← Domain entity
└─────────────────────┘
```

### Component Responsibilities

- **`AnchorItem`** - Domain entity containing business rules
- **`AnchorRepository`** - Data access layer using WordPress meta API
- **`AnchorService`** - Application logic and UI management
- **`AnchorDynamicUrlManager`** - Main controller and WordPress integration

## Requirements

- **WordPress**: 5.3 or higher
- **PHP**: 7.4 or higher
- **Database**: Uses WordPress native meta system (no additional tables)

## Compatibility

- ✅ **WordPress**: 5.3 - 7.1 (tested up to 7.1.5)
- ✅ **PHP**: 7.4 - 8.4
- ✅ **Multisite**: Single-site installations only
- ✅ **Themes**: Compatible with all themes
- ✅ **Elementor**: 3.5+ (uses `unregister`/`register` API)
- ✅ **Caching**: Compatible with all major caching plugins

## Development

### Repository Structure

```
anchor-dynamic-url/              ← repository root: development files only
├── anchor-dynamic-url/          ← the plugin, exactly as published
│   ├── anchor-dynamic-url.php
│   ├── includes/
│   ├── languages/
│   ├── index.php
│   ├── LICENSE
│   └── readme.txt
├── .github/workflows/release.yml  ← tests + automated release
├── bin/                     ← version check, changelog extraction, ZIP build, integration runner
├── tests/integration/       ← tests run in WordPress Playground
├── CHANGELOG.md
├── README.md
├── composer.json            ← development tools (PHPCS)
└── phpcs.xml.dist           ← Plugin Check coding-standard rules
```

Anything shipped with the plugin goes in `anchor-dynamic-url/`; anything else stays at the root.

### Local Development Setup

```bash
# Clone repository
git clone https://github.com/Hylbee/anchor-dynamic-url.git

# Link the plugin folder into WordPress
ln -s /path/to/anchor-dynamic-url/anchor-dynamic-url /path/to/wordpress/wp-content/plugins/anchor-dynamic-url
```

### Tests

The GitHub Actions workflow (`.github/workflows/release.yml`) runs on every push to `main` and `release/**` and on pull requests:

1. **PHP syntax** on PHP 7.4 to 8.4
2. **Coding standards** (`phpcs.xml.dist`, the WordPress Coding Standards rules used by Plugin Check) and **version consistency** (`bin/version.php`)
3. **Build** of `anchor-dynamic-url.zip`
4. **Integration tests** of that ZIP in [WordPress Playground](https://wordpress.github.io/wordpress-playground/) (WordPress and PHP compiled to WebAssembly) with Elementor, on WordPress 6.8 / PHP 7.4 and WordPress latest / PHP 8.4:
   - Elementor frontend: linked container written once with its anchor, Icon List repeater links, several links sharing a URL, escaped query strings
   - Menu anchors, including after a page slug change
   - Updater against a simulated GitHub API: update detection, compatibility data, "View details" popup (changelog, Support tab)
   - A real update installed by WordPress into a non-standard folder name (the folder is kept, the plugin stays active)
   - No PHP warning or notice from the plugin

Run them locally (Node.js 20+, PHP with the zip extension):

```bash
composer install                         # once
composer lint                            # PHPCS
php bin/version.php                      # version consistency
bash bin/test-integration.sh             # WordPress latest / PHP 8.4
bash bin/test-integration.sh --wp 6.8 --php 7.4
```

### Releasing

Releases are published automatically:

1. On a `release/x.y.z` branch, bump the version in `anchor-dynamic-url/anchor-dynamic-url.php` (header and `ANCHOR_DYNAMIC_URL_VERSION`), `anchor-dynamic-url/readme.txt` (Stable tag, Version, changelog) and add the `## [x.y.z]` section to `CHANGELOG.md`
2. Merge the branch into `main`
3. When every test passes on `main`, the workflow creates the `vx.y.z` tag and the GitHub release, with `anchor-dynamic-url.zip` attached and the CHANGELOG section as release notes

A push to `main` whose version already has a release only runs the tests. A version lower than the latest release is rejected.

The release asset must stay named `anchor-dynamic-url.zip` with the `anchor-dynamic-url/` folder at its root: versions up to 1.5.1 download exactly this file name when updating. To build it by hand: `php bin/build-zip.php`.

### Code Standards
- Follows WordPress Coding Standards
- PSR-4 autoloading compatible
- Object-oriented architecture
- Comprehensive inline documentation

### Contributing
1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## API Reference

### Hooks

#### Actions
```php
// Before anchor field is displayed
do_action('menu_anchor_before_field', $item_id, $item, $depth, $args);

// After anchor is saved
do_action('menu_anchor_saved', $menu_item_id, $anchor_value);
```

#### Filters
```php
// Modify anchor before saving
$anchor = apply_filters('menu_anchor_sanitize', $anchor, $menu_item_id);

// Modify final URL
$url = apply_filters('menu_anchor_url', $url, $menu_item_id, $anchor);
```

### Functions

#### `get_menu_item_anchor($menu_item_id)`
Returns the anchor for a specific menu item.

**Parameters:**
- `$menu_item_id` (int) - Menu item ID

**Returns:**
- (string|null) - Anchor value or null if not set

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Frequently Asked Questions

### Q: What happens to existing menu items?
A: Existing menu items continue to work normally. The anchor field is optional and only affects items where you add anchors.

### Q: Will this break my menu if I deactivate the plugin?
A: No. Menu items will revert to their original URLs. No data is lost.

### Q: Can I use special characters in anchors?
A: For security and compatibility, only letters, numbers, hyphens, and underscores are allowed. Other characters are automatically removed.

### Q: Does this work with custom post types?
A: Yes, the plugin works with any post type that can be added to WordPress menus.

### Q: Is this compatible with multilingual sites?
A: Yes, the plugin is fully translatable and works with WPML, Polylang, and other translation plugins.

## Support

- **Documentation**: [https://github.com/Hylbee/anchor-dynamic-url/blob/main/README.md](https://github.com/Hylbee/anchor-dynamic-url/blob/main/README.md)
- **Support Forum**: [https://github.com/Hylbee/anchor-dynamic-url/issues](https://github.com/Hylbee/anchor-dynamic-url/issues)
- **Issues**: [GitHub Issues](https://github.com/Hylbee/anchor-dynamic-url/issues)

## License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

```
Anchor with dynamic URL - WordPress Plugin
Copyright (C) 2025 Hylbee

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

Developed with ❤️ by [Hylbee](https://www.hylbee.fr/)

Built using domain-driven design principles and modern WordPress development practices.