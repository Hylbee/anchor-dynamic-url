<?php
/**
 * PSR-4 Autoloader for AnchorDynamicUrl Plugin
 *
 * This autoloader automatically loads classes based on their namespace structure.
 * It follows the PSR-4 specification for autoloading PHP classes.
 *
 * @package AnchorDynamicUrl
 * @since 1.0.0
 */

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register PSR-4 autoloader for AnchorDynamicUrl namespace.
 *
 * Derives the file path from the class name automatically, so new classes
 * under AnchorDynamicUrl are resolved without touching this file.
 * A short-name map covers existing files whose names differ from the
 * full class name (e.g. class-anchor-manager.php instead of
 * class-anchor-dynamic-url-manager.php).
 *
 * @since 1.0.0
 */
spl_autoload_register(
	function ( $class ) {
		$namespace = 'AnchorDynamicUrl\\';

		if ( strpos( $class, $namespace ) !== 0 ) {
			return;
		}

		// Strip root namespace → e.g. "Plugin\AnchorDynamicUrlManager".
		$relative = substr( $class, strlen( $namespace ) );

		$parts      = explode( '\\', $relative );
		$class_name = array_pop( $parts );
		$sub_dir    = ! empty( $parts ) ? strtolower( implode( '/', $parts ) ) . '/' : '';

		// Convert CamelCase to kebab-case (e.g. AnchorItem → anchor-item).
		$kebab    = ltrim( strtolower( preg_replace( '/([A-Z])/', '-$1', $class_name ) ), '-' );
		$filename = 'class-' . $kebab . '.php';

		// Short-name overrides for existing files whose names don't match the full class name.
		$overrides = array(
			'plugin/class-anchor-dynamic-url-manager.php'  => 'plugin/class-anchor-manager.php',
			'admin/class-anchor-dynamic-url-updater.php'   => 'admin/class-anchor-updater.php',
		);

		$relative_path = $sub_dir . $filename;
		if ( isset( $overrides[ $relative_path ] ) ) {
			$relative_path = $overrides[ $relative_path ];
		}

		$file_path = ANCHOR_DYNAMIC_URL_PATH . 'includes/' . $relative_path;

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);