<?php
/**
 * Elementor Integration - Add dynamic anchor system to Elementor URL controls.
 *
 * @package AnchorDynamicUrl\Elementor
 * @since 1.0.0
 */

namespace AnchorDynamicUrl\Elementor;

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
 * - Rewrites link `href` values (containers and widgets, including links
 *   stored in repeater items) to include the correct fragment before the
 *   page is sent to the browser.
 *
 * @since 1.0.0
 */
class ElementorIntegration {

	/**
	 * Output buffers opened for link containers, keyed by spl_object_id().
	 *
	 * Each entry holds the buffer level before ob_start() and the sanitized anchor.
	 *
	 * @since 1.5.2
	 *
	 * @var array<int, array{level: int, anchor: string}>
	 */
	private $container_buffers = array();

	/**
	 * Register WordPress/Elementor hooks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'elementor/controls/controls_registered', array( $this, 'register_extended_url_control' ) );
		add_action( 'elementor/init', array( $this, 'on_elementor_init' ) );
		add_action( 'elementor/frontend/before_render', array( $this, 'add_anchor_id_to_element' ) );
		add_action( 'elementor/frontend/container/before_render', array( $this, 'start_container_buffer' ) );
		add_action( 'elementor/frontend/container/after_render', array( $this, 'rewrite_container_link' ) );
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
	 * Start buffering a link container that carries an anchor_target.
	 *
	 * Elementor's Container::before_render() adds the link attributes itself and
	 * prints the opening tag immediately, so adding an `href` from a hook would
	 * produce a duplicated value (`href="url#anchor url"`). Instead, the output
	 * is captured here and the container's own href is rewritten in
	 * rewrite_container_link().
	 *
	 * @since 1.5.2
	 *
	 * @param \Elementor\Element_Base $element The container element.
	 */
	public function start_container_buffer( $element ) {
		$link   = $element->get_settings_for_display( 'link' );
		$anchor = is_array( $link ) ? AnchorSanitizer::sanitize( $link['anchor_target'] ?? '' ) : null;

		if ( ! $anchor ) {
			return;
		}

		$this->container_buffers[ spl_object_id( $element ) ] = array(
			'level'  => ob_get_level(),
			'anchor' => $anchor,
		);

		ob_start();
	}

	/**
	 * Rewrite the href of a buffered link container after it has been rendered.
	 *
	 * @since 1.0.0
	 * @since 1.5.2 Runs after render on the buffered output instead of adding a second href.
	 *
	 * @param \Elementor\Element_Base $element The container element.
	 */
	public function rewrite_container_link( $element ) {
		$key = spl_object_id( $element );

		if ( ! isset( $this->container_buffers[ $key ] ) ) {
			return;
		}

		$buffer = $this->container_buffers[ $key ];
		unset( $this->container_buffers[ $key ] );

		// Only close our own buffer, and only if it is on top of the stack:
		// if something else unbalanced the stack, leave the output untouched.
		if ( ob_get_level() !== $buffer['level'] + 1 ) {
			return;
		}

		$content = ob_get_clean();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor's own rendered HTML; the rewritten href is escaped in append_fragment().
		echo $this->rewrite_wrapper_href( $content, (string) $element->get_id(), $buffer['anchor'] );
	}

	/**
	 * Rewrite all anchor-target URLs inside rendered widget HTML.
	 *
	 * URL controls are collected recursively, so links stored in repeater items
	 * (Icon List, Social Icons, Price List, ...) are handled as well as
	 * top-level ones.
	 *
	 * @since 1.0.0
	 * @since 1.5.2 Handles repeater items and several links sharing the same URL.
	 *
	 * @param string                 $content The rendered widget HTML.
	 * @param \Elementor\Widget_Base $widget  The widget instance.
	 * @return string Modified HTML.
	 */
	public function rewrite_widget_links( $content, $widget ) {
		$links = array();
		$this->collect_url_settings( $widget->get_settings_for_display(), $links );

		// Group the widget's URL controls by URL, keeping their settings order.
		$anchors_by_url = array();
		foreach ( $links as $link ) {
			$anchors_by_url[ $link['url'] ][] = $link['anchor'];
		}

		// Offset of the href value in $content => array( original value, anchor ).
		$replacements = array();

		foreach ( $anchors_by_url as $url => $anchors ) {
			if ( ! array_filter( $anchors ) ) {
				continue;
			}

			// When every control using this URL asks for the same anchor, rewrite
			// every matching link (a widget may print the same link twice, e.g. an
			// Image Box links both its image and its title). Otherwise map the n-th
			// matching link to the n-th control (one link per repeater item).
			$uniform = 1 === count( array_unique( $anchors ) );

			foreach ( $this->find_href_values( $content, (string) $url ) as $index => $occurrence ) {
				$anchor = $uniform ? $anchors[0] : ( $anchors[ $index ] ?? null );

				if ( $anchor && ! isset( $replacements[ $occurrence['offset'] ] ) ) {
					$replacements[ $occurrence['offset'] ] = array( $occurrence['value'], $anchor );
				}
			}
		}

		// Apply from the end of the string so earlier offsets stay valid.
		krsort( $replacements );

		foreach ( $replacements as $offset => $replacement ) {
			list( $value, $anchor ) = $replacement;
			$content = substr_replace( $content, $this->append_fragment( $value, $anchor ), $offset, strlen( $value ) );
		}

		return $content;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Collect every URL control value found in a settings array, recursively.
	 *
	 * @since 1.5.2
	 *
	 * @param array $settings Element settings, a repeater list or a repeater item.
	 * @param array $links    Collected links, appended in settings order as
	 *                        array( 'url' => string, 'anchor' => string|null ).
	 */
	private function collect_url_settings( array $settings, array &$links ) {
		foreach ( $settings as $key => $value ) {
			if ( ! is_array( $value ) || '__dynamic__' === $key || '__globals__' === $key ) {
				continue;
			}

			if ( $this->is_url_control_value( $value ) ) {
				$url     = (string) $value['url'];
				$links[] = array(
					// href="" and href="#" both mean "current page".
					'url'    => '#' === $url ? '' : $url,
					'anchor' => AnchorSanitizer::sanitize( $value['anchor_target'] ?? '' ),
				);
				continue;
			}

			// A repeater (list of items) or a repeater item: look one level deeper.
			$this->collect_url_settings( $value, $links );
		}
	}

	/**
	 * Whether a setting value has the shape of an Elementor URL control value.
	 *
	 * Media controls also carry a `url` key, so a URL-control-specific key is required.
	 *
	 * @since 1.5.2
	 *
	 * @param array $value Setting value.
	 * @return bool
	 */
	private function is_url_control_value( array $value ) {
		return isset( $value['url'] ) && is_string( $value['url'] )
			&& ( array_key_exists( 'anchor_target', $value )
				|| array_key_exists( 'is_external', $value )
				|| array_key_exists( 'nofollow', $value ) );
	}

	/**
	 * Find the href values of <a> tags pointing to a given URL.
	 *
	 * Elementor escapes URLs before printing them, so the raw, HTML-encoded and
	 * esc_url() forms of the URL are all matched.
	 *
	 * @since 1.5.2
	 *
	 * @param string $html Rendered HTML.
	 * @param string $url  URL from the control settings ('' for the current page).
	 * @return array[] Occurrences in document order, as array( 'value' => string, 'offset' => int ).
	 */
	private function find_href_values( $html, $url ) {
		if ( '' === $url ) {
			$value_pattern = '#?';
		} else {
			$protocols = array_merge( wp_allowed_protocols(), array( 'skype', 'viber' ) );
			$forms     = array_unique( array_filter( array(
				$url,
				htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ),
				esc_url( $url, $protocols ),
				esc_attr( esc_url( $url, $protocols ) ),
			) ) );

			$value_pattern = implode( '|', array_map(
				static function ( $form ) {
					return preg_quote( $form, '/' );
				},
				$forms
			) );
		}

		// Tag and attribute names are case-insensitive; the URL itself is not.
		$pattern = '/(?i:<a\b[^>]*?\shref=)(["\'])(' . $value_pattern . ')\1/';

		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return array();
		}

		return array_map(
			static function ( $match ) {
				return array(
					'value'  => $match[2][0],
					'offset' => $match[2][1],
				);
			},
			$matches
		);
	}

	/**
	 * Rewrite the href of an element's own <a> wrapper tag.
	 *
	 * The wrapper is identified by its `data-id` attribute so links printed by
	 * child elements are never touched.
	 *
	 * @since 1.5.2
	 *
	 * @param string $html       Rendered HTML of the element.
	 * @param string $element_id Elementor element ID.
	 * @param string $anchor     Sanitized anchor.
	 * @return string Modified HTML.
	 */
	private function rewrite_wrapper_href( $html, $element_id, $anchor ) {
		$tag_pattern = '/(?i:<a\b[^>]*\sdata-id=)(["\'])' . preg_quote( $element_id, '/' ) . '\1[^>]*>/';

		if ( ! preg_match( $tag_pattern, $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		$tag     = $match[0][0];
		$new_tag = preg_replace_callback(
			'/(?i:\shref=)(["\'])(.*?)\1/s',
			function ( $href ) use ( $anchor ) {
				return ' href=' . $href[1] . $this->append_fragment( $href[2], $anchor ) . $href[1];
			},
			$tag,
			1,
			$count
		);

		// Elementor omits href when the URL is empty: link to the anchor on the current page.
		if ( 0 === $count ) {
			$new_tag = substr( $tag, 0, 2 ) . ' href="' . $this->append_fragment( '', $anchor ) . '"' . substr( $tag, 2 );
		}

		return substr_replace( $html, $new_tag, $match[0][1], strlen( $tag ) );
	}

	/**
	 * Replace (or add) the fragment of an href value already printed by Elementor.
	 *
	 * The value is decoded first so that entities such as `&#038;` are not
	 * mistaken for a fragment, then re-escaped for the attribute.
	 *
	 * @since 1.5.2
	 *
	 * @param string $href   Href attribute value as found in the HTML.
	 * @param string $anchor Sanitized anchor.
	 * @return string Escaped href value ending with #anchor.
	 */
	private function append_fragment( $href, $anchor ) {
		$decoded = html_entity_decode( $href, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$base    = explode( '#', $decoded, 2 )[0];

		return esc_attr( $base . '#' . $anchor );
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
