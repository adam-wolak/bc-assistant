<?php
/**
 * Plugin Name: BC Assistant for WordPress
 * Description: ChatGPT and Claude integration for your WordPress site
 * Version: 2.0.0
 * Author: Adam Wolak
 * Text Domain: bc-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class BC_Assistant_Core {
    /**
     * Plugin version
     */
    const VERSION = '2.0.0';
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Plugin path
     */
    private $plugin_path;
    
    /**
     * Plugin URL
     */
    private $plugin_url;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        $this->define_constants();
        $this->load_dependencies();
        $this->init_hooks();
        
        // Log initialization for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            BC_Assistant_Helper::log('Plugin initialized');
        }
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        if (!defined('BC_ASSISTANT_VERSION')) {
            define('BC_ASSISTANT_VERSION', self::VERSION);
        }
        if (!defined('BC_ASSISTANT_PATH')) {
            define('BC_ASSISTANT_PATH', $this->plugin_path);
        }
        if (!defined('BC_ASSISTANT_URL')) {
            define('BC_ASSISTANT_URL', $this->plugin_url);
        }
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        // Core classes
        require_once BC_ASSISTANT_PATH . 'includes/config.php';
        require_once BC_ASSISTANT_PATH . 'includes/helper.php';
        require_once BC_ASSISTANT_PATH . 'includes/ajax-functions.php';
        require_once BC_ASSISTANT_PATH . 'includes/activation.php';
        require_once BC_ASSISTANT_PATH . 'includes/migrations.php';
        require_once BC_ASSISTANT_PATH . 'includes/diagnostic.php';
        
        // Additional modules can be loaded conditionally here
    }
    
    /**
     * Initialize plugin hooks
     */
    private function init_hooks() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, 'bc_assistant_activate');
        register_deactivation_hook(__FILE__, 'bc_assistant_deactivate');
        
        // Load environment variables
        add_action('init', array($this, 'load_env_variables'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'register_admin_page'));
        
        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register shortcode
        add_shortcode('bc_assistant', array($this, 'shortcode_handler'));
        
        // Render chat component
        add_action('wp_footer', array($this, 'render_chat_component'));
        
        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Check for required settings
        add_action('admin_init', array($this, 'check_required_settings'));
        
        // Initialize default settings on first activation
        add_action('plugins_loaded', array($this, 'initialize_settings'));
    }
    
    /**
     * Load environment variables from .env file
     */
    public function load_env_variables() {
        $dotenv = BC_ASSISTANT_PATH . '.env';
        
        // Check if file exists and is readable
        if (file_exists($dotenv) && is_readable($dotenv)) {
            // Log success for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                BC_Assistant_Helper::log('.env file found and readable');
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
                
                // Set environment variable if not already set
                if (!getenv($key) && !empty($key)) {
                    putenv("{$key}={$value}");
                    
                    // Log for debugging
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        BC_Assistant_Helper::log("Set environment variable: {$key}");
                    }
                }
            }
        } else {
            // Log error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (!file_exists($dotenv)) {
                    BC_Assistant_Helper::log('.env file NOT found');
                } elseif (!is_readable($dotenv)) {
                    BC_Assistant_Helper::log('.env file exists but is not readable');
                }
            }
        }
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Core settings
        register_setting('bc_assistant_settings', 'bc_assistant_model', 
            ['sanitize_callback' => array('BC_Assistant_Config', 'sanitize_model'), 'default' => 'gpt-4o']);
        register_setting('bc_assistant_settings', 'bc_assistant_api_key', 
            ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('bc_assistant_settings', 'bc_assistant_system_message_default', 
            ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('bc_assistant_settings', 'bc_assistant_welcome_message_default', 
            ['sanitize_callback' => 'sanitize_text_field']);
        
        // Display settings
        register_setting('bc_assistant_settings', 'bc_assistant_display_mode', 
            ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('bc_assistant_settings', 'bc_assistant_bubble_icon', 
            ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('bc_assistant_settings', 'bc_assistant_bubble_position', 
            ['sanitize_callback' => 'sanitize_text_field', 'default' => 'bottom-right']);
        register_setting('bc_assistant_settings', 'bc_assistant_theme', 
            ['sanitize_callback' => 'sanitize_text_field', 'default' => 'light']);
        
        // Advanced settings
        register_setting('bc_assistant_settings', 'bc_assistant_use_shadow_dom', 
            ['sanitize_callback' => 'rest_sanitize_boolean', 'default' => true]);
    }
    
    /**
     * Register admin menu page
     */
    public function register_admin_page() {
        add_menu_page(
            __('BC Assistant', 'bc-assistant'),
            __('BC Assistant', 'bc-assistant'),
            'manage_options',
            'bc-assistant',
            array($this, 'render_admin_page'),
            'dashicons-format-chat',
            26
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include BC_ASSISTANT_PATH . 'templates/admin.php';
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Font Awesome for icons
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
        
        // Main stylesheet
        wp_enqueue_style(
            'bc-assistant-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION
        );
        
        // Main script - use shadow DOM version if enabled
        $use_shadow_dom = BC_Assistant_Config::get('use_shadow_dom');
        
        $script_file = $use_shadow_dom ? 'shadow-dom.js' : 'script.js';
        
        wp_enqueue_script(
            'bc-assistant-script',
            BC_ASSISTANT_URL . 'assets/js/' . $script_file,
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );
        
        // Get configuration data
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
            'useShadowDOM' => $use_shadow_dom,
        ));
    }
    
    /**
     * Enqueue admin assets
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
     * Render chat component in footer
     */
    public function render_chat_component() {
        static $already_rendered = false;
        
        // Prevent multiple renderings
        if ($already_rendered || BC_Assistant_Config::get('display_mode') !== 'bubble') {
            return;
        }
        
        // Get configuration
        $config = BC_Assistant_Config::get_all();
        
        // Use shadow DOM if enabled
        $use_shadow_dom = BC_Assistant_Config::get('use_shadow_dom');
        
        if ($use_shadow_dom) {
            // For shadow DOM, we just need to add a custom element
            echo '<bc-assistant-widget></bc-assistant-widget>';
        } else {
            // For traditional DOM, include the template
            include BC_ASSISTANT_PATH . 'templates/assistant-wrapper.php';
        }
        
        $already_rendered = true;
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
    
    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=bc-assistant') . '">' . __('Settings', 'bc-assistant') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Check for required settings
     */
    public function check_required_settings() {
        $api_key = BC_Assistant_Config::get('api_key');
        
        if (empty($api_key) && current_user_can('manage_options')) {
            add_action('admin_notices', array($this, 'display_api_key_notice'));
        }
    }
    
    /**
     * Display API key notice
     */
    public function display_api_key_notice() {
        $settings_url = admin_url('admin.php?page=bc-assistant');
        
        ?>
        <div class="notice notice-warning">
            <p>
                <strong>BC Assistant:</strong> 
                <?php _e('Configure API key to use the assistant.', 'bc-assistant'); ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php _e('Go to settings', 'bc-assistant'); ?></a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Initialize settings on first activation
     */
    public function initialize_settings() {
        BC_Assistant_Config::set_defaults();
    }
}

// Initialize plugin
function bc_assistant_init() {
    return BC_Assistant_Core::get_instance();
}

// Start the plugin
bc_assistant_init();