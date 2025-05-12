<?php
/**
 * BC Assistant - Admin Notices
 * 
 * Ten plik obsługuje powiadomienia administracyjne dla wtyczki BC Assistant.
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wyświetl informację o zapisanych ustawieniach
 */
function bc_assistant_settings_saved_notice() {
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] && isset($_GET['page']) && $_GET['page'] === 'bc-assistant') {
        $use_shadow_dom = BC_Assistant_Config::get('use_shadow_dom');
        $shadow_dom_value = $use_shadow_dom ? 'true' : 'false';
        
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p>' . __('Ustawienia BC Assistant zostały zapisane.', 'bc-assistant') . '</p>';
        
        // Zawsze pokazuj wartość Shadow DOM dla debugowania
        echo '<p><strong>' . __('Wartość Shadow DOM: ', 'bc-assistant') . '</strong>' . $shadow_dom_value . '</p>';
        echo '<p><strong>Raw value:</strong> ' . var_export(get_option('bc_assistant_use_shadow_dom'), true) . '</p>';
        
        echo '</div>';
    }
}
add_action('admin_notices', 'bc_assistant_settings_saved_notice');

/**
 * Wyświetl powiadomienie o brakującym kluczu API
 */
function bc_assistant_api_key_notice() {
    $api_key = BC_Assistant_Config::get('api_key');
    
    if (empty($api_key) && current_user_can('manage_options')) {
        $settings_url = admin_url('admin.php?page=bc-assistant');
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>';
        echo '<strong>BC Assistant:</strong> ';
        echo __('Configure API key to use the assistant.', 'bc-assistant');
        echo ' <a href="' . esc_url($settings_url) . '">' . __('Go to settings', 'bc-assistant') . '</a>';
        echo '</p>';
        echo '</div>';
    }
}
add_action('admin_notices', 'bc_assistant_api_key_notice');