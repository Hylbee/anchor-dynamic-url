<?php
/**
 * Elementor Integration - Add dynamic anchor system to Elementor URL controls.
 *
 * URL control extension to include anchor fields.
 *
 * @package AnchorDynamicUrl
 * @since 1.0.0
 */

// Prevent direct access to this file for security.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard: this file must only be loaded after Elementor is available.
if ( ! class_exists( '\Elementor\Plugin' ) ) {
	return;
}

/**
 * Class to handle anchor ID sanitization
 */
class AnchorSanitizerForElementor {
    /**
     * Sanitize anchor ID to ensure it's valid for HTML and CSS
     * 
     * @param string $anchor_id The raw anchor ID input
     * @return string Sanitized anchor ID safe for use in HTML/CSS
     */
    public static function sanitize($anchor_id) {
        // Remove spaces, special characters, and ensure valid CSS ID format
        // Only allow alphanumeric characters, underscores, and hyphens
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($anchor_id));
        
        // Ensure it doesn't start with a number (invalid CSS selector)
        // Prepend 'anchor-' prefix if it starts with a digit
        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = 'anchor-' . $sanitized;
        }
        
        return $sanitized;
    }
}

/**
 * Function to create our extended URL control class
 * The class will only be created when Elementor is fully loaded
 */
function create_extended_url_control() {
    // Ensure Elementor's URL control class is available before extending it
    if (!class_exists('\Elementor\Control_URL')) {
        return false; // Return false if base class doesn't exist
    }
    
    /**
     * Extended URL control class that adds anchor options
     */
    class Extended_URL_Control extends \Elementor\Control_URL {
    
        /**
         * Extended default values with our new fields
         * Extends parent default values to include anchor targeting
         * 
         * @return array Default values for the URL control
         */
        public function get_default_value() {
            $parent_defaults = parent::get_default_value();
            return array_merge($parent_defaults, [
                'anchor_target' => '',
            ]);
        }

        /**
         * Extended default settings with our new options
         * Merges parent settings with our anchor options
         * 
         * @return array Extended settings including anchor options
         */
        protected function get_default_settings() {
            // Get the parent class default settings first
            $settings = parent::get_default_settings();
            
            // Add our new option to the available options array
            // This makes the anchor_target field available in the control
            $settings['options'] = array_merge($settings['options'], [
                'anchor_target',
            ]);
            
            return $settings;
        }

        /**
         * Custom template that adds our fields after custom_attributes
         * Renders the HTML template for the control in Elementor's editor
         * This template uses Underscore.js templating syntax
         */
        public function content_template() {
            // Get parent template
            ob_start();
            parent::content_template();
            $parent_template = ob_get_clean();

            // Create anchor field template using the same structure as Elementor's URL options
            ob_start();
            ?>
            <# if ( -1 !== data.options.indexOf( 'anchor_target' ) ) { #>
                <div class="elementor-control-url__custom-attributes" style="margin-top: 10px;">
                    <label for="<?php $this->print_control_uid( 'anchor_target' ); ?>" class="elementor-control-url__custom-attributes-label"><?php echo esc_html__( 'Target Anchor', 'anchor-dynamic-url' ); ?></label>
                    <input type="text" id="<?php $this->print_control_uid( 'anchor_target' ); ?>" class="elementor-control-unit-5" placeholder="<?php esc_attr_e( 'section-id', 'anchor-dynamic-url' ); ?>" data-setting="anchor_target">
                </div>
                <div class="elementor-control-field-description"><?php echo esc_html__( 'ID of the target element for scrolling (without #). Leave blank to use the normal link.', 'anchor-dynamic-url' ); ?></div>
            <# } #>
            <?php
            $anchor_field = ob_get_clean();

            // Insert our field before the closing div of elementor-control-url-more-options
            // Look for the pattern that closes the more-options section
            $search_pattern = '<# if ( ( data.options && -1 !== data.options.indexOf( \'custom_attributes\' ) ) && data.custom_attributes_description ) { #>
                    <div class="elementor-control-field-description">{{{ data.custom_attributes_description }}}</div>
                    <# } #>';

            $replacement = '<# if ( ( data.options && -1 !== data.options.indexOf( \'custom_attributes\' ) ) && data.custom_attributes_description ) { #>
                    <div class="elementor-control-field-description">{{{ data.custom_attributes_description }}}</div>
                    <# } #>
                    ' . $anchor_field;

            $modified_template = str_replace($search_pattern, $replacement, $parent_template);

            echo $modified_template;
        }

    }

    // Return the class name for registration
    return 'Extended_URL_Control';
}

/**
 * Replace URL control with our extended version
 * Wait for Elementor controls to be available
 * 
 * This hook fires when all Elementor controls have been registered
 * allowing us to safely replace the default URL control
 */
add_action('elementor/controls/controls_registered', function($controls_manager) {
    // Create our extended class
    $extended_class = create_extended_url_control();
    
    // Only proceed if our extended class was created successfully
    if ($extended_class) {
        // Unregister existing URL control to replace it
        $controls_manager->unregister_control('url');
        
        // Create an instance of our extended control
        $extended_control = new $extended_class();
        
        // Register our extended URL control with the same 'url' identifier
        // This ensures all existing URL controls use our extended version
        $controls_manager->register_control('url', $extended_control);
    }
});

/**
 * Ensure the plugin only loads when Elementor is ready
 * This hook fires when Elementor has finished initializing
 */
add_action('elementor/init', function() {
    
    // Now we can safely load our hooks that depend on Elementor being fully loaded
    
    /**
     * Modify all existing widgets to include our new default options
     * This ensures that all URL controls in existing widgets get our anchor options
     */
    add_action('elementor/element/before_section_end', function($element, $section_id) {
        // Get all controls from current section
        $controls = $element->get_controls();
        
        // Loop through controls to find URL type ones
        foreach ($controls as $control_id => $control_data) {
            // Check if this is a URL control type
            if (isset($control_data['type']) && $control_data['type'] === \Elementor\Controls_Manager::URL) {
                // Get existing control configuration
                $existing_control = $element->get_controls($control_id);
                
                // If control doesn't already have our options, add them
                if (isset($existing_control['options']) && !in_array('anchor_target', $existing_control['options'])) {
                    // Update control options to include our anchor functionality
                    $element->update_control($control_id, [
                        'options' => array_merge($existing_control['options'], [
                            'anchor_target',    // Target element ID
                            'anchor_behavior',  // Scroll behavior options
                            'anchor_offset'     // Scroll offset adjustment
                        ])
                    ]);
                }
            }
        }
    }, 10, 2); // Priority 10, accept 2 parameters
    
});

/**
 * Add ID attribute to elements for anchor targeting
 * This allows any element to be targeted by anchor links
 */
add_action('elementor/frontend/before_render', 'menu_anchor_before_render', 10, 1);

/**
 * @param object $element The Elementor element instance being rendered
 */
function menu_anchor_before_render($element) {
    // Get element settings configured in the editor
    $settings = $element->get_settings_for_display();

    // Handle anchor ID (element can be targeted by anchor links)
    // This works for widgets, sections, columns, but NOT for container links
    if (!empty($settings['anchor_target'])) {
        // Sanitize the anchor ID to ensure it's valid for HTML/CSS
        $anchor_target = AnchorSanitizerForElementor::sanitize($settings['anchor_target']);

        // Only add the ID if sanitization was successful
        if ($anchor_target) {
            // Add the ID attribute to the element's wrapper div
            // This makes the element targetable by anchor links (#anchor_target)
            $element->add_render_attribute('_wrapper', 'id', $anchor_target);
        }
    }
}

/**
 * Handle containers with link wrapping (HTML tag = 'a')
 * Capture container output and modify link href after rendering
 * This uses output buffering to modify the HTML after it's generated
 */
add_action( 'elementor/frontend/container/before_render', function( $element ) {
	$settings = $element->get_settings_for_display();

	if ( ! empty( $settings['link']['anchor_target'] ) && ! empty( $settings['link']['url'] ) ) {
		// Store the current ob level on the element so after_render can verify it.
		$element->anchor_ob_level = ob_get_level();
		ob_start();
	}
} );

add_action( 'elementor/frontend/container/after_render', function( $element ) {
	$settings = $element->get_settings_for_display();

	if ( empty( $settings['link']['anchor_target'] ) || empty( $settings['link']['url'] ) ) {
		return;
	}

	// Only call ob_get_clean() if the buffer we opened is still on the stack.
	if ( ! isset( $element->anchor_ob_level ) || ob_get_level() <= $element->anchor_ob_level ) {
		return;
	}

	$content = ob_get_clean();

	$anchor_target = AnchorSanitizerForElementor::sanitize( $settings['link']['anchor_target'] );

	if ( $anchor_target ) {
		$original_url = $settings['link']['url'];

		if ( empty( $original_url ) || '#' === $original_url ) {
			$new_url = '#' . $anchor_target;
		} else {
			$base_url = strtok( $original_url, '#' );
			$new_url  = $base_url . '#' . $anchor_target;
		}

		$content = preg_replace(
			'/(<a[^>]*href=["\'])' . preg_quote( $original_url, '/' ) . '(["\'])/i',
			'$1' . $new_url . '$2',
			$content
		);
	}

	echo $content;
} );

/**
 * Hook to modify HTML content generated by widgets
 * This filter processes widget content to replace URLs with anchor targets
 * when the anchor_target field is specified in URL controls
 */
add_filter('elementor/widget/render_content', 'menu_anchor_render_content', 10, 2);

function menu_anchor_render_content($content, $widget) {
    // Get widget settings as they appear on the frontend
    $settings = $widget->get_settings_for_display();

    // Search for URL controls with anchor_target specified
    foreach ($settings as $setting_key => $setting_value) {
        // Check if this setting is a URL control with anchor target
        if (is_array($setting_value)
            && isset($setting_value['url'])           // Has URL field
            && isset($setting_value['anchor_target']) // Has our anchor target field
            && !empty($setting_value['anchor_target'])) { // Anchor target is not empty
            // Sanitize the anchor target to ensure it's valid
            $anchor_target = AnchorSanitizerForElementor::sanitize($setting_value['anchor_target']);

            if ($anchor_target) {

                // Build new URL with anchor fragment
                $original_url = $setting_value['url'];

                // Handle different URL scenarios
                if (empty($original_url) || $original_url === '#') {
                    // If no URL specified, create anchor link to current page
                    $new_url = '#' . $anchor_target;
                } else {
                    // If URL specified, remove existing fragment and add our anchor
                    $base_url = strtok($original_url, '#'); // Remove existing fragment
                    $new_url = $base_url . '#' . $anchor_target;
                }

                // Use regex to find and modify links in the widget's HTML content
                $content = preg_replace_callback(
                    '/(<a[^>]*href=["\']?)' . preg_quote($original_url, '/') . '(["\'][^>]*>)/i',
                    function($matches) use ($new_url) {
                        $link_start = $matches[1]; // Opening <a href="
                        $link_end = $matches[2];   // Closing " and attributes>

                        // Replace with new URL containing anchor
                        return $link_start . $new_url . $link_end;
                    },
                    $content
                );

                // Special handling for empty or # URLs
                if (empty($original_url) || $original_url === '#') {
                    $content = preg_replace_callback(
                        '/(<a[^>]*href=["\']?)(#?)(["\'][^>]*>)/i',
                        function($matches) use ($new_url) {
                            // Only replace if href is empty or just #
                            if ($matches[2] === '#' || empty($matches[2])) {
                                $link_start = $matches[1];
                                $link_end = $matches[3];

                                return $link_start . $new_url . $link_end;
                            }
                            return $matches[0]; // Return unchanged if not matching
                        },
                        $content
                    );
                }
            }
        }
    }

    return $content; // Return the modified content
}; // Priority 10, accept 2 parameters (content, widget)