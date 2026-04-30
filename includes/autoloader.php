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
 * @since 1.0.0
 */
spl_autoload_register(
	function ( $class ) {
		// Base namespace for our plugin.
		$namespace = 'AnchorDynamicUrl';

		// Check if the class belongs to our namespace.
		if ( strpos( $class, $namespace . '\\' ) !== 0 ) {
			return;
		}

		// Remove the base namespace from the class name.
		$class_name = substr( $class, strlen( $namespace ) + 1 );

		// Replace namespace separators with directory separators.
		$class_path = str_replace( '\\', '/', $class_name );

		// Convert class name to filename format (CamelCase to kebab-case).
		$class_path = strtolower( preg_replace( '/([A-Z])/', '-$1', $class_path ) );
		$class_path = ltrim( $class_path, '-' );

		// Map namespace paths to actual directory paths.
		$namespace_mapping = array(
			'entity/'     => 'class-anchor-item.php',
			'utils/'      => 'class-anchor-sanitizer.php',
			'repository/' => 'class-anchor-repository.php',
			'service/'    => 'class-anchor-service.php',
			'admin/'      => 'class-anchor-updater.php',
			'plugin/'     => 'class-anchor-manager.php',
		);

		// Find the correct file path based on namespace mapping.
		foreach ( $namespace_mapping as $namespace_dir => $filename ) {
			if ( strpos( $class_path, $namespace_dir ) === 0 ) {
				$file_path = ANCHOR_DYNAMIC_URL_PATH . 'includes/' . $filename;
				break;
			}
		}

		// If no mapping found, try to construct the file path directly.
		if ( empty( $file_path ) ) {
			$file_path = ANCHOR_DYNAMIC_URL_PATH . 'includes/class-' . str_replace( '/', '-', $class_path ) . '.php';
		}

		// Include the file if it exists.
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);