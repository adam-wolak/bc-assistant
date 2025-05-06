<?php
/**
 * Plugin Name: BC Assistant for WordPress
 * Description: ChatGPT and Claude integration for your WordPress site
 * Version: 1.0.7
 * Author: Adam Wolak
 * Text Domain: bc-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('BC_ASSISTANT_VERSION')) {
    define('BC_ASSISTANT_VERSION', '1.0.7');
}
if (!defined('BC_ASSISTANT_PATH')) {
    define('BC_ASSISTANT_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BC_ASSISTANT_URL')) {
    define('BC_ASSISTANT_URL', plugin_dir_url(__FILE__));
}

// Load configuration class
require_once BC_ASSISTANT_PATH . 'includes/config.php';

// Load helper class
require_once BC_ASSISTANT_PATH . 'includes/helper.php';

// Load AJAX functions
require_once BC_ASSISTANT_PATH . 'includes/ajax-functions.php';

// Load activation script
require_once BC_ASSISTANT_PATH . 'includes/activation.php';

// Load database migrations
require_once BC_ASSISTANT_PATH . 'includes/migrations.php';

// Load diagnostic tool
require_once BC_ASSISTANT_PATH . 'includes/diagnostic.php';

/**
 * Load environment variables from .env file (suppress warnings)
 */
function bc_assistant_load_env() {
    $dotenv = BC_ASSISTANT_PATH . '.env';
    
    // Check if file exists and is readable
    if (file_exists($dotenv) && is_readable($dotenv)) {
        // Log success for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            BC_Assistant_Helper::log('.env file found and readable at: ' . $dotenv);
        }
        
        // Read file line by line
        $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            BC_Assistant_Helper::log('Error reading .env file');
            return;
        }
        
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (empty($line) || strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value format
            list($key, $value) = explode('=', $line, 2);
            if (empty($key) || !isset($value)) {
                continue;
            }
            
            // Clean up key and value
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable
            if (!getenv($key) && !empty($key)) {
                putenv("{$key}={$value}");
                
                // Log for debugging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    BC_Assistant_Helper::log("Set environment variable: {$key}");
                }
            }
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!file_exists($dotenv)) {
                BC_Assistant_Helper::log('.env file NOT found at: ' . $dotenv);
            } elseif (!is_readable($dotenv)) {
                BC_Assistant_Helper::log('.env file exists but is not readable at: ' . $dotenv);
            }
        }
    }
}
add_action('init', 'bc_assistant_load_env');

/**
 * Main plugin class
 */
class BC_Assistant {
    private $plugin_url;
    

public function __construct() {
    $this->plugin_url = BC_ASSISTANT_URL;
    
    // Register settings group
    add_action('admin_init', array($this, 'register_settings'));
    
    // Add admin menu
    add_action('admin_menu', array($this, 'register_admin_page'));
    
    // Enqueue assets
    add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    
    // Render chat bubble
    add_action('wp_footer', array($this, 'render_chat'));
    
    // Register shortcode
    add_shortcode('bc_assistant', array($this, 'shortcode_handler'));
    
    // Add UI positioning script
    BC_Assistant_Helper::add_ui_positioning();
}

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Registration of individual settings
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_model',
            [
                'sanitize_callback' => array('BC_Assistant_Config', 'sanitize_model'),
                'default' => 'gpt-4o',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_api_key',
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_system_message_default',
            [
                'sanitize_callback' => 'sanitize_textarea_field',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_welcome_message_default',
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_display_mode',
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_bubble_icon',
            [
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_bubble_position',
            [
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'bottom-right',
            ]
        );
        
        register_setting(
            'bc_assistant_settings',
            'bc_assistant_theme',
            [
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'light',
            ]
        );
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
        
        include BC_ASSISTANT_PATH . 'templates/admin.php';
    }

    /**
     * Enqueue front-end styles and scripts
     */
    public function enqueue_assets() {
        // Load Font Awesome if needed
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
        
        // Enqueue main styles
        wp_enqueue_style(
            'bc-assistant-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION . '.' . time() // Add timestamp to force refresh
        );
        
        // Enqueue fixed main script
        wp_enqueue_script(
            'bc-assistant-script',
            BC_ASSISTANT_URL . 'assets/js/script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION . '.' . time(), // Add timestamp to force refresh
            true
        );
        
        // Get configuration
        $config = BC_Assistant_Config::get_all();
        
        // Pass data to script
        wp_localize_script('bc-assistant-script', 'bcAssistantData', array(
            'model' => $config['model'],
            'position' => isset($config['bubble_position']) ? $config['bubble_position'] : 'bottom-right',
            'title' => 'Asystent Bielsko Clinic',
            'welcomeMessage' => $config['welcome_message_default'],
            'apiEndpoint' => admin_url('admin-ajax.php'),
            'action' => 'bc_assistant_send_message',
            'nonce' => wp_create_nonce('bc_assistant_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
            'displayMode' => $config['display_mode'],
            'theme' => $config['theme'],
            'assistant_id' => getenv('OPENAI_ASSISTANT_ID'),
        ));
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets() {
        wp_enqueue_style(
            'bc-assistant-admin-style',
            BC_ASSISTANT_URL . 'assets/css/admin.css',
            array(),
            BC_ASSISTANT_VERSION
        );
        
        wp_enqueue_script(
            'bc-assistant-admin-script',
            BC_ASSISTANT_URL . 'assets/js/admin.js',
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );
    }

    /**
     * Render chat bubble in footer
     */
public function render_chat() {
    static $already_rendered = false;
    
    if ($already_rendered) {
        return; // Only render once per page load
    }
    
    $already_rendered = true; // Mark as rendered
    // Get configuration
    $config = BC_Assistant_Config::get_all();
    
    // Skip if display mode is not bubble
    if ($config['display_mode'] !== 'bubble') {
        return;
    }
    
    // Make sure all required config variables are set with defaults
    if (!isset($config['theme'])) {
        $config['theme'] = 'light';
    }
    
    if (!isset($config['bubble_position'])) {
        $config['bubble_position'] = 'bottom-right';
    }
    
    if (!isset($config['bubble_icon'])) {
        $config['bubble_icon'] = 'chat';
    }
    
    if (!isset($config['button_text'])) {
        $config['button_text'] = 'Zapytaj asystenta';
    }
    
    // Include the template
    include BC_ASSISTANT_PATH . 'templates/assistant-wrapper.php';
}
    
    /**
     * Handle shortcode
     */
    public function shortcode_handler($atts) {
        // Set default attributes
        $atts = shortcode_atts(array(
            'title' => 'Asystent BC',
            'placeholder' => 'Wpisz swoje pytanie...',
            'welcome_message' => '',
            'button_text' => 'Wyślij',
            'context' => 'default',
            'procedure_name' => '',
            'theme' => 'light',
        ), $atts, 'bc_assistant');
        
        // Get configuration
        $config = BC_Assistant_Config::get_all();
        
        // Get page context
        $page_context = BC_Assistant_Helper::get_page_context();
        
        // Set welcome message if not provided
        if (empty($atts['welcome_message'])) {
            $atts['welcome_message'] = BC_Assistant_Helper::get_welcome_message($page_context);
        }
        
        // Buffer output
        ob_start();
        
        // Include embedded template
        include BC_ASSISTANT_PATH . 'templates/assistant-embedded.php';
        
        // Return buffered content
        return ob_get_clean();
    }
}

/**
 * Bootstrap plugin
 */
add_action('plugins_loaded', function() {
    // Set default configuration during first activation
    BC_Assistant_Config::set_defaults();
    
    // Initialize plugin
    new BC_Assistant();
});

/**
 * Register activation and deactivation hooks
 */
register_activation_hook(__FILE__, 'bc_assistant_activate');
register_deactivation_hook(__FILE__, 'bc_assistant_deactivate');

/**
 * Add link to settings on plugin page
 */
function bc_assistant_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=bc-assistant') . '">Ustawienia</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bc_assistant_plugin_action_links');

/**
 * Check for required API keys
 */
function bc_assistant_check_settings() {
    $api_key = BC_Assistant_Config::get('api_key');
    
    if (empty($api_key) && current_user_can('manage_options')) {
        add_action('admin_notices', 'bc_assistant_api_key_notice');
    }
}
add_action('admin_init', 'bc_assistant_check_settings');

/**
 * Display API key notice
 */
function bc_assistant_api_key_notice() {
    $settings_url = admin_url('admin.php?page=bc-assistant');
    
    ?>
    <div class="notice notice-warning">
        <p>
            <strong>BC Assistant:</strong> 
            Skonfiguruj klucz API aby móc korzystać z asystenta. 
            <a href="<?php echo esc_url($settings_url); ?>">Przejdź do ustawień</a>
        </p>
    </div>
    <?php
}

/**
 * Add defer attribute to JS files
 */
function bc_assistant_defer_scripts($tag, $handle, $src) {
    $defer_scripts = array(
        'bc-assistant-script',
    );
    
    if (in_array($handle, $defer_scripts)) {
        return str_replace(' src', ' defer src', $tag);
    }
    
    return $tag;
}
add_filter('script_loader_tag', 'bc_assistant_defer_scripts', 10, 3);