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
define('BC_ASSISTANT_VERSION', '1.0.6');
define('BC_ASSISTANT_PATH', plugin_dir_path(__FILE__));
define('BC_ASSISTANT_URL', plugin_dir_url(__FILE__));

/**
 * Load environment variables from .env file
 */
function bc_assistant_load_env() {
    $env_file = plugin_dir_path(__FILE__) . '.env';
    
    if (file_exists($env_file)) {
        $env_content = file_get_contents($env_file);
        $lines = explode("\n", $env_content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pomiń puste linie i komentarze
            if (empty($line) || strpos($line, '//') === 0 || strpos($line, '#') === 0 || strpos($line, '<?php') === 0 || strpos($line, '?>') === 0) {
                continue;
            }
            
            // Znajdź i sparsuj zmienne
            if (preg_match('/([A-Za-z0-9_]+)="?([^"]*)"?/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];
                
                // Ustaw zmienną w opcjach WordPress
                if ($key === 'OPENAI_API_KEY' && !empty($value)) {
                    update_option('bc_assistant_api_key', $value);
                } elseif ($key === 'OPENAI_ASSISTANT_ID' && !empty($value)) {
                    update_option('bc_assistant_assistant_id', $value);
                }
            }
        }
    }
}

// Ładuj zmienne środowiskowe podczas aktywacji
add_action('admin_init', 'bc_assistant_load_env');


// Register activation/deactivation hooks
register_activation_hook(__FILE__, 'bc_assistant_activate');
register_deactivation_hook(__FILE__, 'bc_assistant_deactivate');

/**
 * Plugin activation
 */
function bc_assistant_activate() {
    // Create default options
    add_option('bc_assistant_api_key', '');
    add_option('bc_assistant_model', 'gpt-4o');
    add_option('bc_assistant_max_tokens', 500);
    add_option('bc_assistant_temperature', 0.7);
    add_option('bc_assistant_system_prompt', 'Jesteś pomocnym asystentem Bielsko Clinic, specjalizującym się w medycynie estetycznej. Odpowiadaj krótko i na temat. Jeśli nie znasz odpowiedzi, powiedz "Nie posiadam informacji na ten temat, polecam bezpośredni kontakt z kliniką."');
    add_option('bc_assistant_welcome_message', 'Witaj! Jestem asystentem Bielsko Clinic. W czym mogę pomóc?');
    add_option('bc_assistant_save_history', '1');
    add_option('bc_assistant_site_prompt', 'Informacje o Bielsko Clinic i dostępnych usługach...');
    add_option('bc_assistant_contraindications', 'Lista przeciwskazań do zabiegów...');
    add_option('bc_assistant_prices', 'Informacje o cenach zabiegów...');
    
    // Dodaj opcję dla ID asystenta (puste domyślnie)
    add_option('bc_assistant_assistant_id', '');
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
    // Dodaj główną pozycję menu
    add_menu_page(
        'BC Assistant', // Tytuł strony
        'BC Assistant', // Nazwa w menu
        'manage_options', // Uprawnienia
        'bc-assistant', // Slug
        array($this, 'render_admin_page'), // Funkcja renderująca stronę
        'dashicons-format-chat', // Ikona (format-chat to ikona dymka rozmowy)
        30 // Pozycja w menu
    );
    
    // Możesz również dodać podmenu (podstrony) jeśli potrzebujesz
    add_submenu_page(
        'bc-assistant', // Slug rodzica
        'Ustawienia BC Assistant', // Tytuł strony
        'Ustawienia', // Nazwa w menu
        'manage_options', // Uprawnienia
        'bc-assistant', // Slug - ten sam co rodzic dla strony głównej
        array($this, 'render_admin_page') // Funkcja renderująca stronę
    );
    
    // Przykład dodatkowej podstrony (odkomentuj jeśli potrzebujesz)
    /*
    add_submenu_page(
        'bc-assistant', // Slug rodzica
        'Logi BC Assistant', // Tytuł strony
        'Logi', // Nazwa w menu
        'manage_options', // Uprawnienia
        'bc-assistant-logs', // Unikalny slug dla tej podstrony
        array($this, 'render_logs_page') // Inna funkcja renderująca stronę
    );
    */
}

/**
 * Renderuj stronę logów 
 */
/*
public function render_logs_page() {
    include($this->plugin_path . 'templates/logs.php');
}
    
    /**
     * Register settings
     */
public function register_settings() {
    register_setting('bc_assistant_options', 'bc_assistant_api_key');
    register_setting('bc_assistant_options', 'bc_assistant_max_tokens');
    register_setting('bc_assistant_options', 'bc_assistant_temperature');
    register_setting('bc_assistant_options', 'bc_assistant_system_prompt');
    register_setting('bc_assistant_options', 'bc_assistant_welcome_message');
    register_setting('bc_assistant_options', 'bc_assistant_save_history');
    register_setting('bc_assistant_options', 'bc_assistant_site_prompt');
    register_setting('bc_assistant_options', 'bc_assistant_contraindications');
    register_setting('bc_assistant_options', 'bc_assistant_prices');
    register_setting('bc_assistant_options', 'bc_assistant_assistant_id'); // Dodane ID asystenta
    
    // Walidacja modelu - ograniczenie do dozwolonych wartości
    register_setting('bc_assistant_options', 'bc_assistant_model', function($input) {
        $allowed_models = array(
            'gpt-4o', 'gpt-4o-mini', 'o1', 'o1-mini', 'o1-pro', 'o3', 'o3-mini',
            'o3-mini-high', 'o4-mini', 'gpt-4.5', 'gpt-4.1', 'gpt-4-1106-preview'
        );
        
        if (!in_array($input, $allowed_models)) {
            return 'gpt-4o'; // Domyślny model, jeśli podano nieprawidłowy
        }
        
        return $input;
    });
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
     * Ajax handler for sending messages - używa API wątków OpenAI
     */
    public function ajax_send_message() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Get message and context
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $thread_id = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : '';
        $conversation_id = isset($_POST['conversation_id']) ? sanitize_text_field($_POST['conversation_id']) : '';
        $page_url = isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : '';
        $page_title = isset($_POST['page_title']) ? sanitize_text_field($_POST['page_title']) : '';
        $voice_mode = isset($_POST['voice_mode']) ? (bool)$_POST['voice_mode'] : false;
        
        if (empty($message)) {
            wp_send_json_error('Message is empty');
            return;
        }
        
        // Use threads API by default
        $response = $this->get_openai_threads_response($message, $thread_id, $page_url, $page_title);
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        // Send response
        wp_send_json_success(array(
            'message' => $response['message'],
            'thread_id' => $response['thread_id']
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
     * Get OpenAI response using Threads API with predefined assistant ID
     */
private function get_openai_threads_response($message, $thread_id = '', $page_url = '', $page_title = '') {
    $api_key = get_option('bc_assistant_api_key');
    // Pobierz ID asystenta z ustawień zamiast hardkodowania
    $assistant_id = get_option('bc_assistant_assistant_id', '');
    // Pobieramy wybrany model
    $selected_model = get_option('bc_assistant_model', 'gpt-4o');
    
    if (empty($api_key)) {
        return new WP_Error('api_key_missing', 'API key is not configured');
    }
    
    if (empty($assistant_id)) {
        return new WP_Error('assistant_id_missing', 'Assistant ID is not configured. Please set it in the plugin settings.');
    }
    
    // Upewnij się, że klucz API jest poprawnie sformatowany
    $api_key = trim($api_key);
        
        // 1. Utwórz lub użyj istniejący wątek
        if (empty($thread_id)) {
            // Utwórz nowy wątek
            $thread_response = wp_remote_post('https://api.openai.com/v1/threads', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v1'
                ),
                'body' => '{}', // Puste ciało dla utworzenia wątku
                'timeout' => 30
            ));
            
            if (is_wp_error($thread_response)) {
                return $thread_response;
            }
            
            $response_code = wp_remote_retrieve_response_code($thread_response);
            if ($response_code !== 200) {
                $error_message = 'Failed to create thread. Status code: ' . $response_code;
                $response_body = wp_remote_retrieve_body($thread_response);
                if (!empty($response_body)) {
                    $error_data = json_decode($response_body, true);
                    if (isset($error_data['error']['message'])) {
                        $error_message .= '. Message: ' . $error_data['error']['message'];
                    }
                }
                return new WP_Error('thread_creation_failed', $error_message);
            }
            
            $thread_data = json_decode(wp_remote_retrieve_body($thread_response), true);
            
            if (!isset($thread_data['id'])) {
                return new WP_Error('thread_creation_failed', 'Failed to create thread: No thread ID returned');
            }
            
            $thread_id = $thread_data['id'];
            $this->log_debug_info("Created new thread: " . $thread_id);
        } else {
            $this->log_debug_info("Using existing thread: " . $thread_id);
        }
        
        // Dodaj kontekst strony do wiadomości
        $content = $message;
        
        if (!empty($page_url)) {
            $content .= "\n\nKontekst: Użytkownik jest na stronie " . $page_url;
            
            if (!empty($page_title)) {
                $content .= " - \"" . $page_title . "\"";
            }
        }
        
        // 2. Dodaj wiadomość do wątku
        $message_response = wp_remote_post('https://api.openai.com/v1/threads/' . $thread_id . '/messages', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ),
            'body' => json_encode(array(
                'role' => 'user',
                'content' => $content
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($message_response)) {
            return $message_response;
        }
        
        $response_code = wp_remote_retrieve_response_code($message_response);
        if ($response_code !== 200) {
            $error_message = 'Failed to add message to thread. Status code: ' . $response_code;
            $response_body = wp_remote_retrieve_body($message_response);
            if (!empty($response_body)) {
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['message'])) {
                    $error_message .= '. Message: ' . $error_data['error']['message'];
                }
            }
            return new WP_Error('message_creation_failed', $error_message);
        }
        
        $this->log_debug_info("Added message to thread");
        
        // 3. Uruchom wątek z asystentem
        $run_data = array(
            'assistant_id' => $assistant_id
        );
        
        // Opcjonalnie możemy nadpisać model asystenta
        // $run_data['model'] = $selected_model;
        
        $run_response = wp_remote_post('https://api.openai.com/v1/threads/' . $thread_id . '/runs', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ),
            'body' => json_encode($run_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($run_response)) {
            return $run_response;
        }
        
        $response_code = wp_remote_retrieve_response_code($run_response);
        if ($response_code !== 200) {
            $error_message = 'Failed to start run. Status code: ' . $response_code;
            $response_body = wp_remote_retrieve_body($run_response);
            if (!empty($response_body)) {
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['message'])) {
                    $error_message .= '. Message: ' . $error_data['error']['message'];
                }
            }
            return new WP_Error('run_creation_failed', $error_message);
        }
        
        $run_data = json_decode(wp_remote_retrieve_body($run_response), true);
        
        if (!isset($run_data['id'])) {
            return new WP_Error('run_creation_failed', 'Failed to start assistant run: No run ID returned');
        }
        
        $run_id = $run_data['id'];
        $this->log_debug_info("Started run: " . $run_id);
        
        // 4. Sprawdzaj status uruchomienia co 1 sekundę
        $max_retries = 60; // Zwiększamy do 60 sekund czekania
        $retry_count = 0;
        $run_status = 'queued';
        
        while ($run_status !== 'completed' && $retry_count < $max_retries) {
            sleep(1);
            $retry_count++;
            
            $run_check_response = wp_remote_get('https://api.openai.com/v1/threads/' . $thread_id . '/runs/' . $run_id, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v1'
                ),
                'timeout' => 30
            ));
            
            if (is_wp_error($run_check_response)) {
                $this->log_debug_info("Error checking run status: " . $run_check_response->get_error_message());
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($run_check_response);
            if ($response_code !== 200) {
                $this->log_debug_info("Error checking run status. Status code: " . $response_code);
                continue;
            }
            
            $run_check_data = json_decode(wp_remote_retrieve_body($run_check_response), true);
            $run_status = isset($run_check_data['status']) ? $run_check_data['status'] : 'queued';
            
            $this->log_debug_info("Run status: " . $run_status . " (attempt " . $retry_count . ")");
            
            if ($run_status === 'failed' || $run_status === 'cancelled' || $run_status === 'expired') {
                $error_message = 'Assistant run failed with status: ' . $run_status;
                if (isset($run_check_data['last_error']['message'])) {
                    $error_message .= '. Error: ' . $run_check_data['last_error']['message'];
                }
                return new WP_Error('run_failed', $error_message);
            }
            
            if ($run_status === 'completed') {
                break;
            }
        }
        
        if ($run_status !== 'completed') {
            return new WP_Error('run_timeout', 'Assistant run timed out after ' . $max_retries . ' seconds');
        }
        
        // 5. Pobierz ostatnią wiadomość od asystenta
        $messages_response = wp_remote_get('https://api.openai.com/v1/threads/' . $thread_id . '/messages', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($messages_response)) {
            return $messages_response;
        }
        
        $response_code = wp_remote_retrieve_response_code($messages_response);
        if ($response_code !== 200) {
            $error_message = 'Failed to retrieve messages. Status code: ' . $response_code;
            $response_body = wp_remote_retrieve_body($messages_response);
            if (!empty($response_body)) {
                $error_data = json_decode($response_body, true);
                if (isset($error_data['error']['message'])) {
                    $error_message .= '. Message: ' . $error_data['error']['message'];
                }
            }
            return new WP_Error('messages_retrieval_failed', $error_message);
        }
        
        $messages_data = json_decode(wp_remote_retrieve_body($messages_response), true);
        
        if (!isset($messages_data['data']) || empty($messages_data['data'])) {
            return new WP_Error('no_messages', 'No messages found in thread');
        }
        
        // Znajdź ostatnią wiadomość od asystenta
        $assistant_message = null;
        
        foreach ($messages_data['data'] as $msg) {
            if ($msg['role'] === 'assistant') {
                $assistant_message = $msg;
                break;
            }
        }
        
        if ($assistant_message === null) {
            return new WP_Error('no_assistant_message', 'No assistant message found');
        }
        
        // Pobierz treść wiadomości
        $content = '';
        
        foreach ($assistant_message['content'] as $content_part) {
            if ($content_part['type'] === 'text') {
                $content .= $content_part['text']['value'];
            }
        }
        
        $this->log_debug_info("Retrieved assistant response");
        
        return array(
            'message' => $content,
            'thread_id' => $thread_id
        );
    }
    
    /**
     * Helper function for logging debug information
     */
    private function log_debug_info($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BC Assistant] ' . $message);
        }
    }
    
    /**
     * Get OpenAI API response - niepotrzebne przy używaniu wątków API
     * Pozostawione dla kompatybilności wstecznej
     */
    private function get_openai_response($conversation, $page_url = '', $page_title = '') {
        $api_key = get_option('bc_assistant_api_key');
        
        if (empty($api_key)) {
            return new WP_Error('api_key_missing', 'API key is not configured');
        }
        
        // Upewnij się, że klucz API jest poprawnie sformatowany
        $api_key = trim($api_key);
        
        $model = get_option('bc_assistant_model', 'gpt-4o');
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