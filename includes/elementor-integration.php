<?php
/**
 * Elementor Integration - Add dynamic anchor system to Elementor URL controls
 * URL control extension to include anchor fields
 */

// Prevent direct access to this file for security
// WordPress defines ABSPATH constant when properly loaded
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly without WordPress
}

// Check if Elementor is available before proceeding
// Ensure Elementor has loaded or the Plugin class exists
if (!did_action('elementor/loaded') && !class_exists('\Elementor\Plugin')) {
    return; // Exit early if Elementor is not available
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
        return [
            'url' => '', // The actual URL/link
            'is_external' => '', // Whether link opens in new window
            'nofollow' => '', // Whether to add nofollow attribute
            'custom_attributes' => '', // Custom HTML attributes
            'anchor_target' => '', // Our new field: target element ID for scrolling
        ];
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
            'anchor_target' // Enable anchor target selection
        ]);
        
        return $settings;
    }

    /**
     * Custom template that adds our fields after custom_attributes
     * Renders the HTML template for the control in Elementor's editor
     * This template uses Underscore.js templating syntax
     */
    public function content_template() {
        // Start PHP template output
        ?>
        <!-- Main URL control container with conditional visibility -->
        <div class="elementor-control-field elementor-control-url-external-{{{ ( data.options.length || data.show_external ) ? 'show' : 'hide' }}}">
            <!-- Control label -->
            <label for="<?php $this->print_control_uid(); ?>" class="elementor-control-title">{{{ data.label }}}</label>
            
            <!-- Input wrapper with dynamic switcher support -->
            <div class="elementor-control-input-wrapper elementor-control-dynamic-switcher-wrapper">
                <!-- Loading spinner for URL autocomplete -->
                <i class="elementor-control-url-autocomplete-spinner eicon-loading eicon-animation-spin" aria-hidden="true"></i>
                
                <!-- Main URL input field -->
                <input id="<?php $this->print_control_uid(); ?>" class="elementor-control-tag-area elementor-input" data-setting="url" placeholder="{{ view.getControlPlaceholder() }}" />
                
                <!-- WordPress nonce for internal link suggestions security -->
                <?php // PHPCS - Nonces don't require escaping. ?>
                <input id="_ajax_linking_nonce" type="hidden" value="<?php echo wp_create_nonce( 'internal-linking' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
                
                <!-- Options toggle button (gear icon) -->
                <# if ( !! data.options ) { #>
                <button class="elementor-control-url-more tooltip-target elementor-control-unit-1" data-tooltip="<?php
                /* translators: Button tooltip text for link options */
                echo esc_attr__( 'Link Options', 'anchor-dynamic-url' );
                ?>" aria-label="<?php
                /* translators: Button aria-label for link options */
                echo esc_attr__( 'Link Options', 'anchor-dynamic-url' );
                ?>">
                    <i class="eicon-cog" aria-hidden="true"></i>
                </button>
                <# } #>
            </div>
            <!-- Expandable options panel -->
            <# if ( !! data.options ) { #>
            <div class="elementor-control-url-more-options">
                
                <?php // Original Elementor options ?>
                <!-- External link option -->
                <# if ( -1 !== data.options.indexOf( 'is_external' ) ) { #>
                <div class="elementor-control-url-option">
                    <input id="<?php $this->print_control_uid( 'is_external' ); ?>" type="checkbox" class="elementor-control-url-option-input" data-setting="is_external">
                    <label for="<?php $this->print_control_uid( 'is_external' ); ?>"><?php
                    /* translators: Label for external link checkbox */
                    echo esc_html__( 'Open in new window', 'anchor-dynamic-url' );
                    ?></label>
                </div>
                <# } #>
                
                <!-- Nofollow option -->
                <# if ( -1 !== data.options.indexOf( 'nofollow' ) ) { #>
                <div class="elementor-control-url-option">
                    <input id="<?php $this->print_control_uid( 'nofollow' ); ?>" type="checkbox" class="elementor-control-url-option-input" data-setting="nofollow">
                    <label for="<?php $this->print_control_uid( 'nofollow' ); ?>"><?php
                    /* translators: Label for nofollow link checkbox */
                    echo esc_html__( 'Add nofollow', 'anchor-dynamic-url' );
                    ?></label>
                </div>
                <# } #>
                
                <!-- Custom attributes option -->
                <# if ( -1 !== data.options.indexOf( 'custom_attributes' ) ) { #>
                <div class="elementor-control-url__custom-attributes">
                    <label for="<?php $this->print_control_uid( 'custom_attributes' ); ?>" class="elementor-control-url__custom-attributes-label"><?php
                    /* translators: Label for custom attributes input field */
                    echo esc_html__( 'Custom Attributes', 'anchor-dynamic-url' );
                    ?></label>
                    <input type="text" id="<?php $this->print_control_uid( 'custom_attributes' ); ?>" class="elementor-control-unit-5" placeholder="<?php
                    /* translators: Placeholder text for custom attributes input */
                    esc_attr_e( 'key|value', 'anchor-dynamic-url' );
                    ?>" data-setting="custom_attributes">
                </div>
                <!-- Custom attributes description if available -->
                <# if ( ( data.options && -1 !== data.options.indexOf( 'custom_attributes' ) ) && data.custom_attributes_description ) { #>
                <div class="elementor-control-field-description">{{{ data.custom_attributes_description }}}</div>
                <# } #>
                <# } #>
                
                <?php // New options for anchor behavior ?>
                <!-- Custom anchor targeting section -->
                <div class="elementor-label-inline elementor-control-url-anchor-options">
                    <!-- Anchor target field -->
                    <# if ( -1 !== data.options.indexOf( 'anchor_target' ) ) { #>
                    <div class="elementor-control-content">
                        <div class="elementor-control-field">
                            <!-- Anchor target label -->
                            <label for="<?php $this->print_control_uid( 'anchor_target' ); ?>" class="elementor-control-title">
                                <?php
                                /* translators: Label for the anchor target input field */
                                echo esc_html__('Target Anchor', 'anchor-dynamic-url');
                                ?>
                            </label>
                            <!-- Anchor target input -->
                            <div class="elementor-control-input-wrapper elementor-control-unit-5">
                                <input type="text" id="<?php $this->print_control_uid( 'anchor_target' ); ?>" placeholder="<?php
                                /* translators: Placeholder text for anchor target input */
                                esc_attr_e( 'section-id', 'anchor-dynamic-url' );
                                ?>" data-setting="anchor_target">
                            </div>
                        </div>
                        <!-- Help text for anchor target -->
                        <div class="elementor-control-field-description">
                            <?php
                            /* translators: Help text explaining how to use the anchor target field */
                            echo esc_html__('ID of the target element for scrolling (without #). Leave blank to use the normal link.', 'anchor-dynamic-url');
                            ?>
                        </div>
                    </div>
                    <# } #>
                </div>
                
            </div>
            <# } #>
        </div>
        
        <!-- Control description if provided -->
        <# if ( data.description ) { #>
        <div class="elementor-control-field-description">{{{ data.description }}}</div>
        <# } #>
        <?php
        // End PHP template output
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
 * Modify element before render - simplified approach
 * This function runs before each Elementor element is rendered on the frontend
 * It adds the anchor ID as an HTML id attribute to make elements targetable
 * 
 * @param object $element The Elementor element instance being rendered
 */
function menu_anchor_before_render($element) {
    // Get element settings configured in the editor
    $settings = $element->get_settings_for_display();
    
    // Handle anchor ID (element can be targeted by anchor links)
    if (!empty($settings['anchor_id'])) {
        // Sanitize the anchor ID to ensure it's valid for HTML/CSS
        $anchor_id = AnchorSanitizerForElementor::sanitize($settings['anchor_id']);
        
        // Only add the ID if sanitization was successful
        if ($anchor_id) {
            // Add the ID attribute to the element's wrapper div
            // This makes the element targetable by anchor links (#anchor_id)
            $element->add_render_attribute('_wrapper', 'id', $anchor_id);
        }
    }
}

/**
 * Hook to modify HTML content generated by widgets
 * This filter processes widget content to replace URLs with anchor targets
 * when the anchor_target field is specified in URL controls
 */
add_filter('elementor/widget/render_content', function($content, $widget) {
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
                    $current_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
                    $new_url = home_url($current_uri) . '#' . $anchor_target;
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
                
                // Log the modification for debugging purposes
                // Log modification for debugging if WP_DEBUG is enabled
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log('Menu Anchor Manager - Modified widget content for: ' . $setting_key . ' -> ' . $new_url);
                }
            }
        }
    }
    
    return $content; // Return the modified content
}, 10, 2); // Priority 10, accept 2 parameters (content, widget)

/**
 * Add anchor ID control to all elements
 * Hook into the common element's style section to add our anchor ID control
 * This ensures all Elementor elements (sections, columns, widgets) get the anchor ID option
 */
add_action('elementor/element/common/_section_style/after_section_end', 'menu_anchor_add_id_control', 10, 2);

/**
 * Frontend functionality: modify elements before render
 * Hook into widget rendering to add anchor IDs to elements on the frontend
 * This makes elements targetable by anchor links
 */
add_action('elementor/frontend/widget/before_render', 'menu_anchor_before_render', 10, 1);

/**
 * Styles for Elementor editor
 * Add custom CSS to improve the appearance of our anchor options in the editor
 * This runs after Elementor's editor styles are loaded
 */
add_action('elementor/editor/after_enqueue_styles', function() {
    // Custom CSS for our anchor options styling
    $css = "
    /* Anchor options container spacing */
    .elementor-control-url-anchor-options {
        margin-top: 15px; /* Add space above anchor options */
    }

    /* Individual anchor option spacing */
    .elementor-control-url-anchor-options .elementor-control-content {
        margin-bottom: 10px; /* Add space between anchor options */
    }
    ";
    
    // Add the CSS inline to the Elementor editor stylesheet
    wp_add_inline_style('elementor-editor', $css);
});