<?php
/**
 * Elementor Integration - Add dynamic anchor system to Elementor URL controls.
 *
 * @package AnchorDynamicUrl\Elementor
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Elementor;

use AnchorDynamicUrl\Entity\AnchorItem;
use AnchorDynamicUrl\Utils\AnchorSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges the plugin's anchor system with Elementor's URL control.
 *
 * Responsibilities:
 * - Replaces Elementor's built-in URL control with an extended version that
 *   exposes an "anchor_target" field in the editor.
 * - Adds an `id` attribute to rendered elements so they can be targeted.
 * - Rewrites link `href` values (containers and widgets) to include the
 *   correct fragment before the page is sent to the browser.
 *
 * @since 1.0.0
 */
class ElementorIntegration {

	/**
	 * Register WordPress/Elementor hooks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'elementor/controls/controls_registered', array( $this, 'register_extended_url_control' ) );
		add_action( 'elementor/init', array( $this, 'on_elementor_init' ) );
		add_action( 'elementor/frontend/before_render', array( $this, 'add_anchor_id_to_element' ) );
		add_action( 'elementor/frontend/container/before_render', array( $this, 'rewrite_container_link' ), 9 );
		add_filter( 'elementor/widget/render_content', array( $this, 'rewrite_widget_links' ), 10, 2 );
	}

	/**
	 * Replace Elementor's URL control with our extended version.
	 *
	 * Fires on `elementor/controls/controls_registered`.
	 *
	 * @since 1.0.0
	 *
	 * @param \Elementor\Controls_Manager $controls_manager Elementor's controls registry.
	 */
	public function register_extended_url_control( $controls_manager ) {
		if ( ! class_exists( '\Elementor\Control_URL' ) ) {
			return;
		}

		// Load the global-namespace class here: this hook fires after Elementor
		// has registered all its own controls, so Control_URL is guaranteed to exist.
		require_once __DIR__ . '/class-elementor-url-control.php';

		$extended_control = new \Anchor_Dynamic_URL_Extended_URL_Control();

		// unregister()/register() replaced the deprecated pair in Elementor 3.5.
		if ( method_exists( $controls_manager, 'unregister' ) ) {
			$controls_manager->unregister( 'url' );
			$controls_manager->register( $extended_control );
		} else {
			$controls_manager->unregister_control( 'url' );
			$controls_manager->register_control( 'url', $extended_control );
		}
	}

	/**
	 * Hook into Elementor after it has fully initialised.
	 *
	 * @since 1.0.0
	 */
	public function on_elementor_init() {
		add_action( 'elementor/element/before_section_end', array( $this, 'inject_anchor_option_into_url_controls' ), 10, 2 );
	}

	/**
	 * Ensure anchor_target is listed in the options of every URL control.
	 *
	 * @since 1.0.0
	 *
	 * @param \Elementor\Element_Base $element    The element whose section is ending.
	 * @param string                  $section_id The section ID.
	 */
	public function inject_anchor_option_into_url_controls( $element, $section_id ) {
		foreach ( $element->get_controls() as $control_id => $control_data ) {
			if ( ! isset( $control_data['type'] ) || \Elementor\Controls_Manager::URL !== $control_data['type'] ) {
				continue;
			}

			$existing = $element->get_controls( $control_id );
			$options  = $this->normalize_options( $existing['options'] ?? false );

			if ( ! in_array( 'anchor_target', $options, true ) ) {
				$element->update_control( $control_id, array(
					'options' => array_merge( $options, array( 'anchor_target' ) ),
				) );
			}
		}
	}

	/**
	 * Add an HTML `id` attribute to any element that carries an anchor_target setting.
	 *
	 * @since 1.0.0
	 *
	 * @param \Elementor\Element_Base $element The element about to be rendered.
	 */
	public function add_anchor_id_to_element( $element ) {
		$settings  = $element->get_settings_for_display();
		$anchor_id = AnchorSanitizer::sanitize( $settings['anchor_target'] ?? '' );

		if ( $anchor_id ) {
			$element->add_render_attribute( '_wrapper', 'id', $anchor_id );
		}
	}

	/**
	 * Rewrite the link href of a container element before it is rendered.
	 *
	 * @since 1.0.0
	 *
	 * @param \Elementor\Element_Base $element The container element.
	 */
	public function rewrite_container_link( $element ) {
		$settings = $element->get_settings_for_display();

		if ( empty( $settings['link']['anchor_target'] ) ) {
			return;
		}

		$new_url = $this->build_url( $settings['link']['url'] ?? '', $settings['link']['anchor_target'] );

		if ( ! $new_url ) {
			return;
		}

		$link        = $settings['link'];
		$link['url'] = $new_url;
		$element->add_link_attributes( '_wrapper', $link );
	}

	/**
	 * Rewrite all anchor-target URLs inside rendered widget HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string                 $content The rendered widget HTML.
	 * @param \Elementor\Widget_Base $widget  The widget instance.
	 * @return string Modified HTML.
	 */
	public function rewrite_widget_links( $content, $widget ) {
		foreach ( $widget->get_settings_for_display() as $setting_value ) {
			if ( ! is_array( $setting_value )
				|| ! isset( $setting_value['url'] )
				|| empty( $setting_value['anchor_target'] ) ) {
				continue;
			}

			$original_url = $setting_value['url'];
			$new_url      = $this->build_url( $original_url, $setting_value['anchor_target'] );

			if ( ! $new_url ) {
				continue;
			}

			if ( empty( $original_url ) || '#' === $original_url ) {
				$content = preg_replace_callback(
					'/(<a[^>]*href=["\']?)(#?)(["\'][^>]*>)/i',
					static function ( $matches ) use ( $new_url ) {
						if ( '' === $matches[2] || '#' === $matches[2] ) {
							return $matches[1] . $new_url . $matches[3];
						}
						return $matches[0];
					},
					$content
				);
			} else {
				$encoded_url = htmlspecialchars( $original_url, ENT_QUOTES, 'UTF-8' );
				$url_pattern = preg_quote( $original_url, '/' );
				if ( $encoded_url !== $original_url ) {
					$url_pattern = '(?:' . $url_pattern . '|' . preg_quote( $encoded_url, '/' ) . ')';
				}

				$content = preg_replace_callback(
					'/(<a[^>]*href=["\']?)' . $url_pattern . '(["\'][^>]*>)/i',
					static function ( $matches ) use ( $new_url ) {
						return $matches[1] . $new_url . $matches[2];
					},
					$content
				);
			}
		}

		return $content;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the final URL by appending the anchor fragment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $original_url The base URL (may be empty).
	 * @param string $raw_anchor   Raw anchor_target value from the control.
	 * @return string URL with fragment, or empty string on failure.
	 */
	private function build_url( $original_url, $raw_anchor ) {
		if ( ! AnchorSanitizer::sanitize( $raw_anchor ) ) {
			return '';
		}

		$item = new AnchorItem( 0, $raw_anchor, null, $original_url );
		return $item->generate_url();
	}

	/**
	 * Normalise an `options` value that Elementor may return as false.
	 *
	 * @since 1.0.0
	 *
	 * @param array|false $options Value from control settings.
	 * @return array
	 */
	private function normalize_options( $options ) {
		return is_array( $options ) ? $options : array( 'url', 'is_external', 'nofollow', 'custom_attributes' );
	}
}

// Bootstrap: register hooks so Elementor's own action hooks do the real work.
( new ElementorIntegration() )->init();
