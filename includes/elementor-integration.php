<?php
/**
 * Elementor Integration - Add dynamic anchor system to Elementor URL controls
 * Extension du contrôle URL pour inclure les champs d'ancrage
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if Elementor is available
if (!did_action('elementor/loaded') && !class_exists('\Elementor\Plugin')) {
    return;
}

/**
 * Classe pour gérer la sanitation des IDs d'ancres
 */
class AnchorSanitizerForElementor {
    public static function sanitize($anchor_id) {
        // Remove spaces, special characters, and ensure valid CSS ID format
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', trim($anchor_id));
        
        // Ensure it doesn't start with a number
        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = 'anchor-' . $sanitized;
        }
        
        return $sanitized;
    }
}

/**
 * Fonction pour créer notre classe étendue du contrôle URL
 * La classe sera créée seulement quand Elementor sera complètement chargé
 */
function create_extended_url_control() {
    if (!class_exists('\Elementor\Control_URL')) {
        return false;
    }
    
    /**
     * Classe étendue du contrôle URL qui ajoute les options d'ancrage
     */
    class Extended_URL_Control extends \Elementor\Control_URL {
    
    /**
     * Valeurs par défaut étendues avec nos nouveaux champs
     */
    public function get_default_value() {
        return [
            'url' => '',
            'is_external' => '',
            'nofollow' => '',
            'custom_attributes' => '',
            'anchor_target' => '',
        ];
    }

    /**
     * Paramètres par défaut étendus avec nos nouvelles options
     */
    protected function get_default_settings() {
        $settings = parent::get_default_settings();
        
        // Ajouter notre nouvelle option
        $settings['options'] = array_merge($settings['options'], [
            'anchor_target'
        ]);
        
        return $settings;
    }

    /**
     * Template personnalisé qui ajoute nos champs après custom_attributes
     */
    public function content_template() {
        ?>
        <div class="elementor-control-field elementor-control-url-external-{{{ ( data.options.length || data.show_external ) ? 'show' : 'hide' }}}">
            <label for="<?php $this->print_control_uid(); ?>" class="elementor-control-title">{{{ data.label }}}</label>
            <div class="elementor-control-input-wrapper elementor-control-dynamic-switcher-wrapper">
                <i class="elementor-control-url-autocomplete-spinner eicon-loading eicon-animation-spin" aria-hidden="true"></i>
                <input id="<?php $this->print_control_uid(); ?>" class="elementor-control-tag-area elementor-input" data-setting="url" placeholder="{{ view.getControlPlaceholder() }}" />
                <?php // PHPCS - Nonces don't require escaping. ?>
                <input id="_ajax_linking_nonce" type="hidden" value="<?php echo wp_create_nonce( 'internal-linking' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" />
                <# if ( !! data.options ) { #>
                <button class="elementor-control-url-more tooltip-target elementor-control-unit-1" data-tooltip="<?php echo esc_attr__( 'Link Options', 'elementor' ); ?>" aria-label="<?php echo esc_attr__( 'Link Options', 'elementor' ); ?>">
                    <i class="eicon-cog" aria-hidden="true"></i>
                </button>
                <# } #>
            </div>
            <# if ( !! data.options ) { #>
            <div class="elementor-control-url-more-options">
                
                <!-- Options originales d'Elementor -->
                <# if ( -1 !== data.options.indexOf( 'is_external' ) ) { #>
                <div class="elementor-control-url-option">
                    <input id="<?php $this->print_control_uid( 'is_external' ); ?>" type="checkbox" class="elementor-control-url-option-input" data-setting="is_external">
                    <label for="<?php $this->print_control_uid( 'is_external' ); ?>"><?php echo esc_html__( 'Open in new window', 'elementor' ); ?></label>
                </div>
                <# } #>
                
                <# if ( -1 !== data.options.indexOf( 'nofollow' ) ) { #>
                <div class="elementor-control-url-option">
                    <input id="<?php $this->print_control_uid( 'nofollow' ); ?>" type="checkbox" class="elementor-control-url-option-input" data-setting="nofollow">
                    <label for="<?php $this->print_control_uid( 'nofollow' ); ?>"><?php echo esc_html__( 'Add nofollow', 'elementor' ); ?></label>
                </div>
                <# } #>
                
                <# if ( -1 !== data.options.indexOf( 'custom_attributes' ) ) { #>
                <div class="elementor-control-url__custom-attributes">
                    <label for="<?php $this->print_control_uid( 'custom_attributes' ); ?>" class="elementor-control-url__custom-attributes-label"><?php echo esc_html__( 'Custom Attributes', 'elementor' ); ?></label>
                    <input type="text" id="<?php $this->print_control_uid( 'custom_attributes' ); ?>" class="elementor-control-unit-5" placeholder="key|value" data-setting="custom_attributes">
                </div>
                <# if ( ( data.options && -1 !== data.options.indexOf( 'custom_attributes' ) ) && data.custom_attributes_description ) { #>
                <div class="elementor-control-field-description">{{{ data.custom_attributes_description }}}</div>
                <# } #>
                <# } #>
                
                <div class="elementor-label-inline elementor-control-url-anchor-options">
                    <!-- NOTRE NOUVEAU CHAMP D'ANCRAGE -->
                    <# if ( -1 !== data.options.indexOf( 'anchor_target' ) ) { #>
                    <div class="elementor-control-content">
                        <div class="elementor-control-field">
                            <label for="<?php $this->print_control_uid( 'anchor_target' ); ?>" class="elementor-control-title">
                                <?php echo __('Target Anchor', 'menu-anchor-manager'); ?>
                            </label>
                            <div class="elementor-control-input-wrapper elementor-control-unit-5">
                                <input type="text" id="<?php $this->print_control_uid( 'anchor_target' ); ?>" placeholder="section-id" data-setting="anchor_target">
                            </div>
                        </div>
                        <div class="elementor-control-field-description">
                            <?php echo __('ID of the target element for scrolling (without #). Leave blank to use the normal link.', 'menu-anchor-manager'); ?>
                        </div>
                    </div>
                    <# } #>
                </div>
                
            </div>
            <# } #>
        </div>
        <# if ( data.description ) { #>
        <div class="elementor-control-field-description">{{{ data.description }}}</div>
        <# } #>
        <?php
    }
}

    return 'Extended_URL_Control';
}

/**
 * Remplacer le contrôle URL par notre version étendue
 * Attendre que les contrôles Elementor soient disponibles
 */
add_action('elementor/controls/controls_registered', function($controls_manager) {
    // Créer notre classe étendue
    $extended_class = create_extended_url_control();
    
    if ($extended_class) {
        // Désenregistrer le contrôle URL existant
        $controls_manager->unregister_control('url');
        
        // Créer une instance de notre contrôle étendu
        $extended_control = new $extended_class();
        
        // Enregistrer notre contrôle URL étendu
        $controls_manager->register_control('url', $extended_control);
    }
});

/**
 * S'assurer que le plugin ne se charge que si Elementor est prêt
 */
add_action('elementor/init', function() {
    
    // Maintenant on peut charger en toute sécurité nos hooks qui dépendent d'Elementor
    
    /**
     * Modifier tous les widgets existants pour inclure nos nouvelles options par défaut
     */
    add_action('elementor/element/before_section_end', function($element, $section_id) {
        // Récupérer tous les contrôles de la section courante
        $controls = $element->get_controls();
        
        // Parcourir les contrôles pour trouver ceux de type URL
        foreach ($controls as $control_id => $control_data) {
            if (isset($control_data['type']) && $control_data['type'] === \Elementor\Controls_Manager::URL) {
                // Obtenir le contrôle existant
                $existing_control = $element->get_controls($control_id);
                
                // Si le contrôle n'a pas déjà nos options, les ajouter
                if (isset($existing_control['options']) && !in_array('anchor_target', $existing_control['options'])) {
                    // Mettre à jour les options du contrôle
                    $element->update_control($control_id, [
                        'options' => array_merge($existing_control['options'], [
                            'anchor_target',
                            'anchor_behavior',
                            'anchor_offset'
                        ])
                    ]);
                }
            }
        }
    }, 10, 2);
    
});

/**
 * Ajouter le contrôle d'ID d'ancre à tous les éléments (onglet Avancé)
 */
function menu_anchor_add_id_control($element, $args) {
    $element->start_controls_section(
        'menu_anchor_id_section',
        [
            'label' => __('ID Anchor Menu', 'menu-anchor-manager'),
            'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
        ]
    );
    
    $element->add_control(
        'anchor_id',
        [
            'label' => __('Element ID', 'menu-anchor-manager'),
            'type' => \Elementor\Controls_Manager::TEXT,
            'placeholder' => __('my-section', 'menu-anchor-manager'),
            'description' => __('Define an ID for this element that can be targeted from menus or other elements.', 'menu-anchor-manager'),
            'frontend_available' => true,
        ]
    );
    
    $element->end_controls_section();
}

/**
 * Modifier l'élément avant le rendu - approche simplifiée
 */
function menu_anchor_before_render($element) {
    $settings = $element->get_settings_for_display();
    
    // Gérer l'ID d'ancre (élément peut être ciblé)
    if (!empty($settings['anchor_id'])) {
        $anchor_id = AnchorSanitizerForElementor::sanitize($settings['anchor_id']);
        if ($anchor_id) {
            $element->add_render_attribute('_wrapper', 'id', $anchor_id);
        }
    }
}

/**
 * Hook pour modifier le contenu HTML généré par les widgets
 */
add_filter('elementor/widget/render_content', function($content, $widget) {
    $settings = $widget->get_settings_for_display();
    
    // Chercher les contrôles URL avec anchor_target
    foreach ($settings as $setting_key => $setting_value) {
        if (is_array($setting_value) 
            && isset($setting_value['url']) 
            && isset($setting_value['anchor_target']) 
            && !empty($setting_value['anchor_target'])) {
            
            $anchor_target = AnchorSanitizerForElementor::sanitize($setting_value['anchor_target']);
            if ($anchor_target) {
                
                // Construire la nouvelle URL avec l'ancre
                $original_url = $setting_value['url'];
                if (empty($original_url) || $original_url === '#') {
                    $new_url = home_url($_SERVER['REQUEST_URI']) . '#' . $anchor_target;
                } else {
                    $base_url = strtok($original_url, '#');
                    $new_url = $base_url . '#' . $anchor_target;
                }
                
                // Regex pour trouver et modifier les liens dans le contenu
                $content = preg_replace_callback(
                    '/(<a[^>]*href=["\']?)' . preg_quote($original_url, '/') . '(["\'][^>]*>)/i',
                    function($matches) use ($new_url) {
                        $link_start = $matches[1];
                        $link_end = $matches[2];
                        
                        // Remplacer par la nouvelle URL avec l'ancre
                        return $link_start . $new_url . $link_end;
                    },
                    $content
                );
                
                // Si l'URL était vide ou #, chercher les liens vides aussi
                if (empty($original_url) || $original_url === '#') {
                    $content = preg_replace_callback(
                        '/(<a[^>]*href=["\']?)(#?)(["\'][^>]*>)/i',
                        function($matches) use ($new_url) {
                            if ($matches[2] === '#' || empty($matches[2])) {
                                $link_start = $matches[1];
                                $link_end = $matches[3];
                                
                                return $link_start . $new_url . $link_end;
                            }
                            return $matches[0];
                        },
                        $content
                    );
                }
                
                error_log('Menu Anchor Manager - Modified widget content for: ' . $setting_key . ' -> ' . $new_url);
            }
        }
    }
    
    return $content;
}, 10, 2);

/**
 * Ajouter le contrôle d'ID d'ancre à tous les éléments
 */
add_action('elementor/element/common/_section_style/after_section_end', 'menu_anchor_add_id_control', 10, 2);

/**
 * Fonctionnalité frontend : modifier les éléments avant rendu
 */
add_action('elementor/frontend/widget/before_render', 'menu_anchor_before_render', 10, 1);

/**
 * Styles pour l'éditeur Elementor
 */
add_action('elementor/editor/after_enqueue_styles', function() {
    $css = "
    .elementor-control-url-anchor-options {
        margin-top: 15px;
    }

    .elementor-control-url-anchor-options .elementor-control-content {
        margin-bottom: 10px;
    }
    ";
    
    wp_add_inline_style('elementor-editor', $css);
});