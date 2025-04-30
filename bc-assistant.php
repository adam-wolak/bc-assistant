<?php
/**
 * Plugin Name: BC Assistant for WordPress
 * Description: ChatGPT integration for your WordPress site
 * Version: 1.0.5
 * Author: Adam Wolak
 * Text Domain: bc-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('BC_ASSISTANT_VERSION', '1.0.0');
define('BC_ASSISTANT_PATH', plugin_dir_path(__FILE__));
define('BC_ASSISTANT_URL', plugin_dir_url(__FILE__));

// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'bc_assistant_activate');
register_deactivation_hook(__FILE__, 'bc_assistant_deactivate');

/**
 * Plugin activation
 */
function bc_assistant_activate() {
    // Create default options
    add_option('bc_assistant_api_key', '');
    add_option('bc_assistant_model', 'gpt-3.5-turbo');
    add_option('bc_assistant_max_tokens', 500);
    add_option('bc_assistant_temperature', 0.7);
    add_option('bc_assistant_system_prompt', 'Jesteś pomocnym asystentem Bielsko Clinic, specjalizującym się w medycynie estetycznej. Odpowiadaj krótko i na temat. Jeśli nie znasz odpowiedzi, powiedz "Nie posiadam informacji na ten temat, polecam bezpośredni kontakt z kliniką."');
    add_option('bc_assistant_welcome_message', 'Witaj! Jestem asystentem Bielsko Clinic. W czym mogę pomóc?');
    add_option('bc_assistant_save_history', '1');
    add_option('bc_assistant_site_prompt', 'Informacje o Bielsko Clinic i dostępnych usługach...');
    add_option('bc_assistant_contraindications', 'Lista przeciwskazań do zabiegów...');
    add_option('bc_assistant_prices', 'Informacje o cenach zabiegów...');
}

/**
 * Plugin deactivation
 */
function bc_assistant_deactivate() {
    // Cleanup if needed
}

/**
 * Main plugin class
 */
class BC_Assistant {
    private $plugin_path;
    private $plugin_url;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        
        // Init actions
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Ajax handlers
        add_action('wp_ajax_bc_assistant_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_nopriv_bc_assistant_send_message', array($this, 'ajax_send_message'));
        add_action('wp_ajax_bc_assistant_transcribe_audio', array($this, 'ajax_transcribe_audio'));
        add_action('wp_ajax_nopriv_bc_assistant_transcribe_audio', array($this, 'ajax_transcribe_audio'));
        
        // Add shortcode
        add_shortcode('bc_assistant', array($this, 'render_shortcode'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('bc-assistant', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize assistant
        $this->initialize_assistant();
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Enqueue styles
        wp_enqueue_style(
            'bc-assistant-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION
        );
        
        // Enqueue Font Awesome
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'bc-assistant-script',
            BC_ASSISTANT_URL . 'assets/js/script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );
        
        // Add localized script data
        wp_localize_script(
            'bc-assistant-script',
            'bcAssistantData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'welcomeMessage' => get_option('bc_assistant_welcome_message', 'Witaj! Jestem asystentem Bielsko Clinic. W czym mogę pomóc?'),
                'nonce' => wp_create_nonce('bc_assistant_nonce')
            )
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'BC Assistant',
            'BC Assistant',
            'manage_options',
            'bc-assistant',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('bc_assistant_options', 'bc_assistant_api_key');
        register_setting('bc_assistant_options', 'bc_assistant_model');
        register_setting('bc_assistant_options', 'bc_assistant_max_tokens');
        register_setting('bc_assistant_options', 'bc_assistant_temperature');
        register_setting('bc_assistant_options', 'bc_assistant_system_prompt');
        register_setting('bc_assistant_options', 'bc_assistant_welcome_message');
        register_setting('bc_assistant_options', 'bc_assistant_save_history');
        register_setting('bc_assistant_options', 'bc_assistant_site_prompt');
        register_setting('bc_assistant_options', 'bc_assistant_contraindications');
        register_setting('bc_assistant_options', 'bc_assistant_prices');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        include($this->plugin_path . 'templates/admin.php');
    }
    
    /**
     * Initialize assistant
     */
    public function initialize_assistant() {
        // Add assistant bubble to footer
        add_action('wp_footer', array($this, 'render_assistant_bubble'));
    }
    
    /**
     * Render assistant bubble
     */
    public function render_assistant_bubble() {
        include($this->plugin_path . 'templates/bubble.php');
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Wpisz swoje pytanie...',
            'button_text' => 'Wyślij',
        ), $atts);
        
        ob_start();
        include($this->plugin_path . 'templates/shortcode.php');
        return ob_get_clean();
    }
    
    /**
     * Ajax handler for sending messages
     */
    public function ajax_send_message() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Get message and context
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
        $voice_mode = isset($_POST['voice_mode']) ? (bool)$_POST['voice_mode'] : false;
        
        if (empty($message)) {
            wp_send_json_error('Message is empty');
            return;
        }
        
        // Get conversation history
        $conversation = $this->get_conversation($conversation_id);
        
        // Add user message to conversation
        $conversation[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Get API response
        $response = $this->get_openai_response($conversation, $page_url, $page_title);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        // Add assistant response to conversation
        $conversation[] = array(
            'role' => 'assistant',
            'content' => $response
        );
        
        // Save conversation if enabled
        if (get_option('bc_assistant_save_history', '1') === '1') {
            $this->save_conversation($conversation_id, $conversation);
        }
        
        // Send response
        wp_send_json_success(array(
            'message' => $response,
            'conversation_id' => $conversation_id ?: wp_generate_uuid4()
        ));
    }
    
    /**
     * Ajax handler for transcribing audio
     */
    public function ajax_transcribe_audio() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check if audio file was uploaded
        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('No audio file uploaded');
            return;
        }
        
        $api_key = get_option('bc_assistant_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('API key is not configured');
            return;
        }
        
        // Upewnij się, że klucz API jest poprawnie sformatowany
        $api_key = trim($api_key); // Usuń białe znaki
        
        // Send audio to OpenAI API
        $response = wp_remote_post('https://api.openai.com/v1/audio/transcriptions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key
            ),
            'body' => array(
                'file' => new CURLFile($_FILES['audio']['tmp_name']),
                'model' => 'whisper-1'
            ),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : 'Unknown error';
            wp_send_json_error($error_message);
            return;
        }
        
        wp_send_json_success(array(
            'text' => $response_body['text']
        ));
    }
    
    /**
     * Get OpenAI API response
     */
    private function get_openai_response($conversation, $page_url = '', $page_title = '') {
        $api_key = get_option('bc_assistant_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('api_key_missing', 'API key is not configured');
        }
        
        // Upewnij się, że klucz API jest poprawnie sformatowany
        $api_key = trim($api_key);
        
        $model = get_option('bc_assistant_model', 'gpt-3.5-turbo');
        $max_tokens = intval(get_option('bc_assistant_max_tokens', 500));
        $temperature = floatval(get_option('bc_assistant_temperature', 0.7));
        
        // Pobierz system prompt z ustawień
        $system_prompt = get_option('bc_assistant_system_prompt', 'Jesteś pomocnym asystentem Bielsko Clinic.');
        
        // Dodaj ostrzeżenie o konfabulacji tylko jeśli nie jest już zawarte w promptcie
        if (strpos($system_prompt, 'konfabulacji') === false && 
            strpos($system_prompt, 'nie wymyślaj') === false) {
            $system_prompt .= "\n\nWażne: Nie wymyślaj informacji o zabiegach, lekach czy procedurach. Jeśli nie znasz odpowiedzi, przyznaj to i zasugeruj kontakt z kliniką.";
        }
        
        // Dodaj kontekst strony
        if (!empty($page_url)) {
            $system_prompt .= "\n\nUżytkownik jest obecnie na stronie: " . $page_url;
            
            if (!empty($page_title)) {
                $system_prompt .= "\nTytuł strony: " . $page_title;
            }
            
            // Dodaj treść strony
            $page_content = $this->get_page_content($page_url);
            if (!empty($page_content)) {
                $system_prompt .= "\n\nZawartość strony:\n" . $page_content;
            }
        }
        
        // Dodaj pozostałe informacje z zakładek panelu
        $site_prompt = get_option('bc_assistant_site_prompt', '');
        $contraindications = get_option('bc_assistant_contraindications', '');
        $prices = get_option('bc_assistant_prices', '');
        
        if (!empty($site_prompt)) {
            $system_prompt .= "\n\nInformacje o klinice:\n" . $site_prompt;
        }
        
        if (!empty($contraindications)) {
            $system_prompt .= "\n\nPrzeciwskazania do zabiegów:\n" . $contraindications;
        }
        
        if (!empty($prices)) {
            $system_prompt .= "\n\nCennik zabiegów:\n" . $prices;
        }
        
        // Prepare messages for API
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            )
        );
        
        // Add conversation history (skip system message)
        foreach ($conversation as $message) {
            if ($message['role'] !== 'system') {
                $messages[] = $message;
            }
        }
        
        // Prepare request body
        $request_body = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
        );
        
        // Send request to OpenAI API
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code !== 200) {
            $error_message = isset($response_body['error']['message']) ? $response_body['error']['message'] : 'Unknown error';
            return new WP_Error('api_error', $error_message);
        }
        
        return $response_body['choices'][0]['message']['content'];
    }
    
    /**
     * Get conversation history
     */
    private function get_conversation($conversation_id) {
        if (empty($conversation_id) || get_option('bc_assistant_save_history', '1') !== '1') {
            return array();
        }
        
        $conversations = get_option('bc_assistant_conversations', array());
        
        return isset($conversations[$conversation_id]) ? $conversations[$conversation_id] : array();
    }
    
    /**
     * Save conversation history
     */
    private function save_conversation($conversation_id, $conversation) {
        if (empty($conversation_id)) {
            $conversation_id = wp_generate_uuid4();
        }
        
        $conversations = get_option('bc_assistant_conversations', array());
        $conversations[$conversation_id] = $conversation;
        
        // Limit the number of saved conversations (keep only the last 50)
        if (count($conversations) > 50) {
            $conversations = array_slice($conversations, -50, 50, true);
        }
        
        update_option('bc_assistant_conversations', $conversations);
        
        return $conversation_id;
    }
    
    /**
     * Get page content for better context
     */
    private function get_page_content($url) {
        // Jeśli URL jest wewnętrzny, spróbuj pobrać post
        $post_id = url_to_postid($url);
        if ($post_id) {
            $post = get_post($post_id);
            if ($post) {
                // Pobierz treść posta
                $content = $post->post_content;
                // Usuń tagi HTML
                $content = wp_strip_all_tags($content, true);
                // Skróć do rozsądnej długości
                return substr($content, 0, 2000) . (strlen($content) > 2000 ? '...' : '');
            }
        }
        
        // Jeśli nie możemy pobrać treści za pomocą WP API, spróbuj pobrać stronę
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; BC Assistant/1.0)');
            $html = curl_exec($ch);
            curl_close($ch);
            
            if ($html) {
                // Usuń tagi HTML
                $content = wp_strip_all_tags($html, true);
                // Usuń nadmiarowe białe znaki
                $content = preg_replace('/\s+/', ' ', $content);
                // Skróć do rozsądnej długości
                return substr($content, 0, 2000) . (strlen($content) > 2000 ? '...' : '');
            }
        }
        
        return '';
    }
}

// Initialize plugin
function BC_Assistant() {
    static $instance = null;
    if ($instance === null) {
        $instance = new BC_Assistant();
    }
    return $instance;
}

// Start the plugin
BC_Assistant();