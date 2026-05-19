<?php
/**
 * Extended Elementor URL Control - Adds anchor_target field.
 *
 * Declared in the global namespace (no namespace statement) so Elementor's
 * controls registry can resolve it by bare class name. Loaded only after
 * `elementor/loaded` fires, which guarantees \Elementor\Control_URL exists.
 *
 * @package AnchorDynamicUrl
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Anchor_Dynamic_URL_Extended_URL_Control' ) ) {
	return;
}

/**
 * Extends Elementor's URL control to expose an anchor_target field.
 *
 * @since 1.0.0
 */
class Anchor_Dynamic_URL_Extended_URL_Control extends \Elementor\Control_URL {

	/**
	 * Extended default values with the anchor_target field.
	 *
	 * @return array
	 */
	public function get_default_value() {
		return array_merge( parent::get_default_value(), array(
			'anchor_target' => '',
		) );
	}

	/**
	 * Extended default settings that include anchor_target in the options list.
	 *
	 * @return array
	 */
	protected function get_default_settings() {
		$settings = parent::get_default_settings();

		$options             = is_array( $settings['options'] ?? false )
			? $settings['options']
			: array( 'url', 'is_external', 'nofollow', 'custom_attributes' );
		$settings['options'] = array_merge( $options, array( 'anchor_target' ) );

		return $settings;
	}

	/**
	 * Render the Underscore.js template, injecting our anchor field
	 * inside the .elementor-control-url-more-options block.
	 */
	public function content_template() {
		ob_start();
		parent::content_template();
		$parent_template = ob_get_clean();

		$anchor_field = '<# if ( data.options && -1 !== data.options.indexOf( \'anchor_target\' ) ) { #>'
			. '<div class="elementor-control-url__custom-attributes elementor-control-direction-ltr" style="margin-top:8px">'
			. '<label for="' . esc_attr( $this->get_control_uid( 'anchor_target' ) ) . '" class="elementor-control-url__custom-attributes-label">'
			. esc_html__( 'Target Anchor', 'anchor-dynamic-url' )
			. '</label>'
			. '<input type="text" id="' . esc_attr( $this->get_control_uid( 'anchor_target' ) ) . '" class="elementor-control-unit-5"'
			. ' placeholder="' . esc_attr__( 'section-id', 'anchor-dynamic-url' ) . '" data-setting="anchor_target">'
			. '</div>'
			. '<div class="elementor-control-field-description">'
			. esc_html__( 'ID of the target element for scrolling (without #). Leave blank to use the normal link.', 'anchor-dynamic-url' )
			. '</div>'
			. '<# } #>';

		// Inject just before the closing </div> of .elementor-control-url-more-options.
		// Walk open/close <div> tags to find the real closing tag of the block
		// rather than the first inner one (handles nested divs from other plugins).
		$marker   = 'elementor-control-url-more-options';
		$pos      = strrpos( $parent_template, $marker );
		$modified = $parent_template;

		if ( false !== $pos ) {
			$depth    = 0;
			$search   = substr( $parent_template, $pos );
			$offset   = 0;
			$found_at = false;

			while ( $offset < strlen( $search ) ) {
				$open  = strpos( $search, '<div', $offset );
				$close = strpos( $search, '</div>', $offset );

				if ( false === $close ) {
					break;
				}

				if ( false !== $open && $open < $close ) {
					$depth++;
					$offset = $open + 4;
				} else {
					if ( 0 === $depth ) {
						$found_at = $pos + $close;
						break;
					}
					$depth--;
					$offset = $close + 6;
				}
			}

			if ( false !== $found_at ) {
				$modified = substr( $parent_template, 0, $found_at )
					. $anchor_field
					. substr( $parent_template, $found_at );
			}
		}

		// Fallback: marker not found (Elementor restructured its template).
		if ( $modified === $parent_template ) {
			$modified = $parent_template . $anchor_field;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		// $parent_template is Elementor's own Underscore.js template (trusted).
		// $anchor_field is built entirely from esc_html__/esc_attr__ calls above.
		echo $modified;
	}
}
