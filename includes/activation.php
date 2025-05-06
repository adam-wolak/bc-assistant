<?php
/**
 * BC Assistant - Activation and Deactivation Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add code to your plugin activation function to check and create .env file if needed
function bc_assistant_check_env_file() {
    $env_file = ABSPATH . '.env';
    
    if (!file_exists($env_file)) {
        $env_content = "# BC Assistant Environment Variables\n";
        $env_content .= "OPENAI_MODEL=gpt-4o\n";
        $env_content .= "OPENAI_ASSISTANT_ID=" . esc_attr(BC_Assistant_Config::get('assistant_id')) . "\n";
        
        // Try to create the file
        $result = @file_put_contents($env_file, $env_content);
        
        if ($result === false) {
            BC_Assistant_Helper::log('Failed to create .env file at ' . $env_file);
        } else {
            BC_Assistant_Helper::log('Created .env file at ' . $env_file);
        }
    }
}
add_action('admin_init', 'bc_assistant_check_env_file');

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