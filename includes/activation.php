<?php
/**
 * BC Assistant - Activation and Deactivation Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Actions to perform on plugin activation
 */
function bc_assistant_activate() {
    // Initialize database tables
    BC_Assistant_Helper::initialize_database();
    
    // Set default configuration
    BC_Assistant_Config::set_defaults();
    
    // Log activation
    BC_Assistant_Helper::log('Plugin activated');
    
    // Flag that activation has run
    update_option('bc_assistant_activation_run', true);
    update_option('bc_assistant_version', BC_ASSISTANT_VERSION);
}

/**
 * Actions to perform on plugin deactivation
 */
function bc_assistant_deactivate() {
    // Log deactivation
    BC_Assistant_Helper::log('Plugin deactivated');
}

/**
 * Check if plugin needs to be updated
 */
function bc_assistant_check_update() {
    $current_version = get_option('bc_assistant_version', '0.0.0');
    
    // If version hasn't changed, no update needed
    if (version_compare($current_version, BC_ASSISTANT_VERSION, '>=')) {
        return;
    }
    
    // Log update
    BC_Assistant_Helper::log('Updating plugin from ' . $current_version . ' to ' . BC_ASSISTANT_VERSION);
    
    // Check if database tables need to be created or updated
    if (!BC_Assistant_Helper::check_tables_exist()) {
        BC_Assistant_Helper::initialize_database();
    }
    
    // Update version option
    update_option('bc_assistant_version', BC_ASSISTANT_VERSION);
}
add_action('plugins_loaded', 'bc_assistant_check_update');

/**
 * Add activation and deactivation hooks to bc-assistant.php file
 */
// register_activation_hook(__FILE__, 'bc_assistant_activate');
// register_deactivation_hook(__FILE__, 'bc_assistant_deactivate');