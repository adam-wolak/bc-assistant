<?php
/**
 * Plugin Name: BC Assistant
 * Description: Interactive AI Assistant for WordPress using ChatGPT API
 * Version: 1.0.1
 * Author: Adam Wolak
 * Text Domain: bc-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definiuj ścieżki wtyczki
define('BC_ASSISTANT_DIR', plugin_dir_path(__FILE__));
define('BC_ASSISTANT_URL', plugin_dir_url(__FILE__));
define('BC_ASSISTANT_VERSION', '1.0.0');

// Dołącz pliki wtyczki
require_once BC_ASSISTANT_DIR . 'includes/config.php';

// Hooks aktywacji/deaktywacji
register_activation_hook(__FILE__, 'bc_assistant_activate');
register_deactivation_hook(__FILE__, 'bc_assistant_deactivate');

function bc_assistant_activate() {
    // Inicjalizacja konfiguracji domyślnej
    BC_Assistant_Config::set_defaults();
    
    // Upewnij się, że katalogi assetów istnieją
    $upload_dir = wp_upload_dir();
    $assistant_dir = $upload_dir['basedir'] . '/bc-assistant';
    
    if (!file_exists($assistant_dir)) {
        wp_mkdir_p($assistant_dir);
        @chmod($assistant_dir, 0755);
    }
}

function bc_assistant_deactivate() {
    // Opcjonalnie można dodać akcje deaktywacji
}

class BC_Assistant_Plugin {
    private $plugin_path;
    private $plugin_url;
    private $current_page_context;
    
    public function __construct() {
        $this->plugin_path = BC_ASSISTANT_DIR;
        $this->plugin_url = BC_ASSISTANT_URL;
        $this->current_page_context = 'default';
        
        // Podstawowe akcje
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 15);
        add_action('admin_menu', array($this, 'admin_menu'));
//        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'render_bubble'));
        add_action('wp_ajax_bc_assistant_chat', array($this, 'process_chat_request'));
        add_action('wp_ajax_nopriv_bc_assistant_chat', array($this, 'process_chat_request'));
    }

    public function init() {
        // Rejestruj shortcode niezależnie od WPBakery
        add_shortcode('bc_assistant', array($this, 'render_shortcode'));
        
        // Jeśli WPBakery jest aktywny, dodaj jako komponent
        if (function_exists('vc_map')) {
            vc_map(array(
                'name' => 'BC Assistant',
                'base' => 'bc_assistant',
                'category' => 'Bielsko Clinic',
                'icon' => 'icon-wpb-ui-custom-heading',
                'params' => array(
                    array(
                        'type' => 'dropdown',
                        'heading' => 'Tryb wyświetlania',
                        'param_name' => 'display_mode',
                        'value' => array(
                            'Wbudowany' => 'embedded',
                            'Bąbelek' => 'bubble',
                        ),
                        'std' => BC_Assistant_Config::get('display_mode'),
                        'description' => 'Wybierz sposób wyświetlania asystenta',
                    ),
                    array(
                        'type' => 'textfield',
                        'heading' => 'Tytuł',
                        'param_name' => 'title',
                        'value' => 'Asystent Bielsko Clinic',
                        'admin_label' => true,
                    ),
                    array(
                        'type' => 'dropdown',
                        'heading' => 'Kontekst',
                        'param_name' => 'context',
                        'value' => array(
                            'Automatyczny' => 'auto',
                            'Ogólny' => 'default',
                            'Zabieg' => 'procedure',
                            'Przeciwwskazania' => 'contraindications',
                        ),
                        'std' => 'auto',
                        'description' => 'Wybierz kontekst dla asystenta',
                    ),
                    array(
                        'type' => 'textfield',
                        'heading' => 'Nazwa zabiegu',
                        'param_name' => 'procedure_name',
                        'value' => '',
                        'description' => 'Tylko dla kontekstu "Zabieg"',
                        'dependency' => array(
                            'element' => 'context',
                            'value' => 'procedure',
                        ),
                    ),
                    array(
                        'type' => 'textarea',
                        'heading' => 'Wiadomość powitalna',
                        'param_name' => 'welcome_message',
                        'value' => BC_Assistant_Config::get('welcome_message_default'),
                    ),
                    array(
                        'type' => 'dropdown',
                        'heading' => 'Motyw',
                        'param_name' => 'theme',
                        'value' => array(
                            'Jasny' => 'light',
                            'Ciemny' => 'dark',
                        ),
                        'std' => BC_Assistant_Config::get('theme'),
                    ),
                ),
            ));
        }
    }

    public function enqueue_scripts() {
        if (!$this->should_load_assets()) {
            return;
        }

       // Dołącz Font Awesome z CDN
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
             array(),
            '5.15.4'
        );

        // Dołącz jQuery
        wp_enqueue_script('jquery');
        
        // Dołącz plik CSS
        wp_enqueue_style(
           'bc-assistant-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION
        );

        
        // Dołącz plik JS
        wp_enqueue_script(
           'bc-assistant-script',
            BC_ASSISTANT_URL . 'assets/js/script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );

        
        // Przekaż zmienne do JS
        wp_localize_script('bc-assistant-script', 'bc_assistant_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bc_assistant_nonce'),
        ));
    }

    private function should_load_assets() {
        // Zawsze ładuj assety, ponieważ używamy trybu bubble w stopce
        return true;
    }
    
    /**
     * Wykrywa kontekst bieżącej strony
     */
    private function detect_page_context() {
        global $post;
        
        // Domyślny kontekst
        $context = 'default';
        $procedure_name = '';
        
        if (!$post) {
            return array('context' => $context, 'procedure_name' => $procedure_name);
        }
        
        // Sprawdź czy to jest strona zabiegu (można dostosować do konkretnej struktury strony)
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            if ($category->slug === 'zabiegi' || strpos($category->slug, 'zabieg') !== false) {
                $context = 'procedure';
                $procedure_name = get_the_title($post->ID);
                break;
            }
        }
        
        // Sprawdź czy to jest strona przeciwwskazań (można dostosować)
        if (strpos(strtolower($post->post_title), 'przeciwwskazania') !== false || 
            strpos(strtolower($post->post_content), 'przeciwwskazania') !== false) {
            $context = 'contraindications';
        }
        
        return array('context' => $context, 'procedure_name' => $procedure_name);
    }

    public function render_shortcode($atts) {
        $page_context = $this->detect_page_context();
        
        $atts = shortcode_atts(array(
            'display_mode' => BC_Assistant_Config::get('display_mode'),
            'title' => 'Asystent Bielsko Clinic',
            'context' => 'auto',
            'procedure_name' => $page_context['procedure_name'],
            'welcome_message' => BC_Assistant_Config::get('welcome_message_default'),
            'theme' => BC_Assistant_Config::get('theme')
        ), $atts);
        
        // Jeśli kontekst jest automatyczny, użyj wykrytego kontekstu
        if ($atts['context'] === 'auto') {
            $atts['context'] = $page_context['context'];
        }
        
        // Ustaw właściwą wiadomość powitalną w zależności od kontekstu
        if ($atts['context'] === 'procedure' && empty($atts['welcome_message'])) {
            $welcome_message = BC_Assistant_Config::get('welcome_message_procedure');
            $atts['welcome_message'] = str_replace('{PROCEDURE_NAME}', $atts['procedure_name'], $welcome_message);
        } elseif ($atts['context'] === 'contraindications' && empty($atts['welcome_message'])) {
            $atts['welcome_message'] = BC_Assistant_Config::get('welcome_message_contraindications');
        }
        
        // Zapisz kontekst do użycia w API
        $this->current_page_context = array(
            'context' => $atts['context'],
            'procedure_name' => $atts['procedure_name']
        );
        
        // Renderuj odpowiedni szablon
        ob_start();
        if ($atts['display_mode'] === 'embedded') {
            include($this->plugin_path . 'templates/assistant-embedded.php');
        } else {
            // Tryb bubble jest obsługiwany przez wp_footer
            // Tutaj możemy dodać pusty div jako placeholder
            echo '<div class="bc-assistant-placeholder" data-title="' . esc_attr($atts['title']) . '" data-context="' . esc_attr($atts['context']) . '" data-procedure="' . esc_attr($atts['procedure_name']) . '"></div>';
        }
        return ob_get_clean();
    }
    
    /**
     * Renderuje bąbelek czatu w stopce
     */
    public function render_bubble() {
        // Sprawdź czy asystent powinien być widoczny na tej stronie
        if (!BC_Assistant_Config::get('context_detection')) {
            // Jeśli wykrywanie kontekstu jest wyłączone, pokazuj tylko tam gdzie jest shortcode
            global $post;
            if (!$post || (!has_shortcode($post->post_content, 'bc_assistant') && !$this->has_wpbakery_component($post->post_content))) {
                return;
            }
        }
        
        // Wykryj kontekst strony
        $page_context = $this->detect_page_context();
        $this->current_page_context = $page_context;
        
        // Przygotuj dane dla asystenta
        $title = 'Asystent Bielsko Clinic';
        
        // Wybierz właściwą wiadomość powitalną
        if ($page_context['context'] === 'procedure') {
            $welcome_message = BC_Assistant_Config::get('welcome_message_procedure');
            $welcome_message = str_replace('{PROCEDURE_NAME}', $page_context['procedure_name'], $welcome_message);
        } elseif ($page_context['context'] === 'contraindications') {
            $welcome_message = BC_Assistant_Config::get('welcome_message_contraindications');
        } else {
            $welcome_message = BC_Assistant_Config::get('welcome_message_default');
        }
        
        $theme = BC_Assistant_Config::get('theme');
        
        // Renderuj szablon bąbelka
        include($this->plugin_path . 'templates/assistant-bubble.php');
    }
    
    /**
     * Sprawdza czy post zawiera komponent WPBakery BC Assistant
     */
    private function has_wpbakery_component($content) {
        if (!function_exists('vc_map')) {
            return false;
        }
        
        return strpos($content, '[bc_assistant') !== false;
    }
    
    // Obsługa przesłania zapytania do API ChatGPT
    public function process_chat_request() {
        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            exit;
        }
        
        // Pobierz dane zapytania
        $message = sanitize_text_field($_POST['message'] ?? '');
        $conversation_history = isset($_POST['conversation']) ? json_decode(stripslashes($_POST['conversation']), true) : array();
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'default';
        $procedure_name = isset($_POST['procedure_name']) ? sanitize_text_field($_POST['procedure_name']) : '';
        
        if (empty($message)) {
            wp_send_json_error('Wiadomość nie może być pusta');
            exit;
        }
        
        // Pobierz klucz API i model
        $api_key = BC_Assistant_Config::get('api_key');
        $model = BC_Assistant_Config::get('model');
        
        // Wybierz odpowiednią wiadomość systemową na podstawie kontekstu
        if ($context === 'procedure' && !empty($procedure_name)) {
            $system_message = BC_Assistant_Config::get('system_message_procedure');
            $system_message = str_replace('{PROCEDURE_NAME}', $procedure_name, $system_message);
        } elseif ($context === 'contraindications') {
            $system_message = BC_Assistant_Config::get('system_message_contraindications');
        } else {
            $system_message = BC_Assistant_Config::get('system_message_default');
        }
        
        if (empty($api_key)) {
            wp_send_json_error('Klucz API nie został skonfigurowany');
            exit;
        }
        
        // Przygotuj zapytanie do API OpenAI
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_message
            )
        );
        
        // Dodaj historię konwersacji (maksymalnie 10 ostatnich wiadomości)
        if (!empty($conversation_history)) {
            $conversation_history = array_slice($conversation_history, -10);
            foreach ($conversation_history as $msg) {
                $messages[] = array(
                    'role' => $msg['role'],
                    'content' => $msg['content']
                );
            }
        }
        
        // Dodaj bieżącą wiadomość
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );
        
        // Przygotuj dane do API
        $request_data = array(
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 500
        );
        
        // Wyślij zapytanie do API OpenAI
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Błąd komunikacji z API: ' . $response->get_error_message());
            exit;
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['error'])) {
            wp_send_json_error('Błąd API: ' . $response_body['error']['message']);
            exit;
        }
        
        if (!isset($response_body['choices'][0]['message']['content'])) {
            wp_send_json_error('Nieoczekiwana odpowiedź z API');
            exit;
        }
        
        $assistant_response = $response_body['choices'][0]['message']['content'];
        
        // Zapisz użycie API do logów (opcjonalnie)
        $this->log_api_usage($message, $assistant_response, $context);
        
        // Zwróć odpowiedź
        wp_send_json_success(array(
            'response' => $assistant_response
        ));
        exit;
    }
    
    /**
     * Loguje użycie API do pliku
     */
    private function log_api_usage($user_message, $assistant_response, $context) {
        // Wyłącz logowanie jeśli nie jest potrzebne
        if (!BC_Assistant_Config::get('enable_logging', false)) {
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/bc-assistant/logs';
        
        // Upewnij się, że katalog logów istnieje
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_file = $log_dir . '/api_usage_' . date('Y-m') . '.log';
        
        $log_entry = date('Y-m-d H:i:s') . " | Context: $context\n";
        $log_entry .= "User: " . $user_message . "\n";
        $log_entry .= "Assistant: " . $assistant_response . "\n";
        $log_entry .= "----------------------------------------\n";
        
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
    }
    
    // Dodaj stronę ustawień
    public function admin_menu() {
        add_menu_page(
            'BC Assistant', 
            'BC Assistant',
            'manage_options',
            'bc-assistant',
            array($this, 'admin_page'),
            'dashicons-admin-comments',
            100
        );
    }
    
    // Wyświetl stronę ustawień
    public function admin_page() {
        // Zapisz ustawienia jeśli formularz został przesłany
        if (isset($_POST['bc_assistant_save_settings']) && check_admin_referer('bc_assistant_settings')) {
            update_option('bc_assistant_api_key', sanitize_text_field($_POST['bc_assistant_api_key']));
            update_option('bc_assistant_model', sanitize_text_field($_POST['bc_assistant_model']));
            update_option('bc_assistant_sys_message', sanitize_textarea_field($_POST['bc_assistant_sys_message']));
            echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
        }
        
        // Pobierz aktualne ustawienia
        $api_key = get_option('bc_assistant_api_key', '');
        $model = get_option('bc_assistant_model', 'gpt-4');
        $system_message = get_option('bc_assistant_sys_message', 'Jesteś pomocnym asystentem Bielsko Clinic, który odpowiada na pytania dotyczące zabiegów i usług kliniki.');
        
        // Wyświetl formularz ustawień
        ?>
        <div class="wrap">
            <h1>BC Assistant - Ustawienia</h1>
            <form method="post" action="">
                <?php wp_nonce_field('bc_assistant_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bc_assistant_api_key">Klucz API OpenAI</label></th>
                        <td>
                            <input type="password" name="bc_assistant_api_key" id="bc_assistant_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" autocomplete="off" />
                            <p class="description">Wprowadź swój klucz API z OpenAI.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bc_assistant_model">Model</label></th>
                        <td>
                            <select name="bc_assistant_model" id="bc_assistant_model">
                                <option value="gpt-4" <?php selected($model, 'gpt-4'); ?>>GPT-4</option>
                                <option value="gpt-4o" <?php selected($model, 'gpt-4o'); ?>>GPT-4o</option>
                                <option value="gpt-3.5-turbo" <?php selected($model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                            </select>
                            <p class="description">Wybierz model AI do użycia w czacie.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bc_assistant_sys_message">Wiadomość systemowa</label></th>
                        <td>
                            <textarea name="bc_assistant_sys_message" id="bc_assistant_sys_message" rows="5" class="large-text"><?php echo esc_textarea($system_message); ?></textarea>
                            <p class="description">Wiadomość systemowa określająca rolę i zachowanie asystenta.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="bc_assistant_save_settings" class="button button-primary" value="Zapisz ustawienia" />
                </p>
            </form>
        </div>
        <?php
    }
}

function BC_Assistant() {
    static $instance = null;
    if (null === $instance) {
        $instance = new BC_Assistant_Plugin();
    }
    return $instance;
}

// Initialize
BC_Assistant();
