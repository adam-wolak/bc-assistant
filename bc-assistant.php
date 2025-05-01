<?php
/**
 * Plugin Name: BC Assistant for WordPress
 * Description: ChatGPT integration for your WordPress site
 * Version: 1.0.6
 * Author: Adam Wolak
 * Text Domain: bc-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BC_ASSISTANT_VERSION')) {
    define('BC_ASSISTANT_VERSION', '1.0.6');
}
if (!defined('BC_ASSISTANT_PATH')) {
    define('BC_ASSISTANT_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BC_ASSISTANT_URL')) {
    define('BC_ASSISTANT_URL', plugin_dir_url(__FILE__));
}

// Load configuration class
require_once BC_ASSISTANT_PATH . 'includes/config.php';

/**
 * Load environment variables from .env file (suppress warnings)
 */
function bc_assistant_load_env() {
    $dotenv = BC_ASSISTANT_PATH . '.env';
    if (file_exists($dotenv)) {
        $vars = @parse_ini_file($dotenv, false, INI_SCANNER_RAW);
        if (is_array($vars)) {
            foreach ($vars as $key => $value) {
                // Strip surrounding quotes if present
                $value = trim($value, "'\"");
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                }
            }
        }
    }
}
add_action('init', 'bc_assistant_load_env');

/**
 * Main plugin class
 */
class BC_Assistant {
    public function __construct() {
        // Register settings group
        add_action('admin_init', array($this, 'register_settings'));
        // Add admin menu
        add_action('admin_menu', array($this, 'register_admin_page'));
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        // Render chat bubble
        add_action('wp_footer', array($this, 'render_chat'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('bc_assistant_options', 'bc_assistant_options');
    }

    /**
     * Add top-level admin menu page
     */
    public function register_admin_page() {
        add_menu_page(
            __('BC Assistant', 'bc-assistant'),
            __('BC Assistant', 'bc-assistant'),
            'manage_options',
            'bc-assistant',
            array($this, 'admin_page_html'),
            'dashicons-format-chat',
            26
        );
    }

    /**
     * Render admin settings page
     */
    public function admin_page_html() {
        if (!current_user_can('manage_options')) {
            return;
        }
        // Fetch existing options or defaults
        $options = get_option('bc_assistant_options', array());
        include BC_ASSISTANT_PATH . 'templates/admin.php';
    }

    /**
     * Enqueue front-end styles and scripts
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'bc-assistant-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION
        );
        wp_enqueue_script(
            'bc-assistant-script',
            BC_ASSISTANT_URL . 'assets/js/bc-assistant-script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );
        wp_enqueue_script(
            'bc-assistant-init',
            BC_ASSISTANT_URL . 'assets/js/script.js',
            array('bc-assistant-script'),
            BC_ASSISTANT_VERSION,
            true
        );
        wp_localize_script('bc-assistant-init', 'BC_Assistant_Settings', array(
            'ajax_url'     => admin_url('admin-ajax.php'),
            'assistant_id' => getenv('OPENAI_ASSISTANT_ID'),
        ));
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'bc-assistant-admin-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION
        );
        wp_enqueue_script(
            'bc-assistant-admin-script',
            BC_ASSISTANT_URL . 'assets/js/bc-assistant-script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );
    }

    /**
     * Render chat bubble in footer
     */
    public function render_chat() {
        // Prepare template variables
        $config = new BC_Assistant_Config();
        $theme = $config->get_theme();
        $page_context = $config->get_page_context();
        include BC_ASSISTANT_PATH . 'templates/assistant-bubble.php';
    }
}

/**
 * Bootstrap plugin
 */
add_action('plugins_loaded', function() {
    if (class_exists('BC_Assistant')) {
        new BC_Assistant();
    }
});
