<?php
/**
 * Plugin Name: BC Assistant
 * Description: Interactive AI Assistant for WordPress using ChatGPT API
 * Version: 1.0.2
 * Author: Adam Wolak
 * Text Domain: bc-assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definiuj ścieżki wtyczki
define('BC_ASSISTANT_DIR', plugin_dir_path(__FILE__));
define('BC_ASSISTANT_URL', plugin_dir_url(__FILE__));
define('BC_ASSISTANT_VERSION', '1.0.2');

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

    /**
     * Pobiera treść strony jako kontekst dla asystenta
     */
    private function get_page_content_context() {
        global $post;
        
        if (!$post) {
            return '';
        }
        
        // Pobierz treść podstawową
        $content = wp_strip_all_tags($post->post_content);
        $title = get_the_title($post->ID);
        
        // Ogranicz długość treści (aby nie przekraczać limitów tokenów)
        if (strlen($content) > 1000) {
            $content = substr($content, 0, 1000) . '...';
        }
        
        // Sformatuj kontekst
        $context = "Tytuł strony: $title\n\nTreść strony: $content";
        
        return $context;
    }

    /**
     * Pobiera dodatkowy kontekst dla API OpenAI
     */
    private function get_additional_context() {
        // Pobierz podstawowy kontekst ze strony
        $page_context = $this->get_page_content_context();
        
        // Sprawdź, czy jest to strona zabiegu i czy istnieje selektor BC
        $bc_selector_content = $this->get_bc_selector_content();
        
        if (!empty($bc_selector_content)) {
            $page_context .= "\n\nSzczegóły zabiegu: " . $bc_selector_content;
        }
        
        return $page_context;
    }

    /**
     * Pobiera treść z selektora BC (jeśli istnieje na stronie)
     */
    private function get_bc_selector_content() {
        global $post;
        
        if (!$post) {
            return '';
        }
        
        // Sprawdź czy strona zawiera div z klasą bc-content-container (selektor BC)
        $content = '';
        
        // Pobieramy treść elementów z selektora BC bezpośrednio z pamięci podręcznej transient
        $selector_cache_key = 'bc_selector_content_' . $post->ID;
        $cached_content = get_transient($selector_cache_key);
        
        if ($cached_content !== false) {
            return $cached_content;
        }
        
        // Musimy pobierać bezpośrednio z DOM strony
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($post->post_content, 'HTML-ENTITIES', 'UTF-8'));
        $finder = new DomXPath($dom);
        
        // Sprawdź czy istnieje kontener BC Selector
        $containers = $finder->query("//div[contains(@class, 'bc-content-container')]");
        
        if ($containers->length > 0) {
            // Znajdź wszystkie elementy treści
            $contents = $finder->query("//div[contains(@class, 'bc-content')]");
            foreach ($contents as $element) {
                $id = $element->getAttribute('id');
                $zabieg_name = '';
                
                // Próba znalezienia nazwy zabiegu z opcji selecta
                $select_options = $finder->query("//select[contains(@class, 'bc-select')]/option");
                foreach ($select_options as $option) {
                    if ($option->getAttribute('value') == str_replace('content-', '', $id)) {
                        $zabieg_name = $option->textContent;
                        break;
                    }
                }
                
                // Pobierz treść elementu
                $zabieg_content = $dom->saveHTML($element);
                $zabieg_content = wp_strip_all_tags($zabieg_content);
                $zabieg_content = preg_replace('/\s+/', ' ', $zabieg_content);
                
                // Ogranicz długość
                if (strlen($zabieg_content) > 300) {
                    $zabieg_content = substr($zabieg_content, 0, 300) . '...';
                }
                
                if (!empty($zabieg_name)) {
                    $content .= "Zabieg: $zabieg_name\n$zabieg_content\n\n";
                }
            }
        }
        
        // Alternatywna metoda - szukaj bezpośrednio w HTML (może być bardziej niezawodna)
        if (empty($content)) {
            // Poszukaj ręcznie w treści HTML
            $html = $post->post_content;
            
            // Szukaj nazw opcji selektora
            preg_match_all('/<option value="zabieg-\d+">(.*?)<\/option>/', $html, $zabieg_names);
            
            // Szukaj treści każdego zabiegu
            preg_match_all('/<div id="content-zabieg-\d+" class="bc-content">(.*?)<\/div>/s', $html, $zabieg_contents);
            
            if (!empty($zabieg_names[1]) && !empty($zabieg_contents[1])) {
                for ($i = 0; $i < count($zabieg_names[1]); $i++) {
                    if (isset($zabieg_contents[1][$i])) {
                        $zabieg_name = $zabieg_names[1][$i];
                        $zabieg_content = wp_strip_all_tags($zabieg_contents[1][$i]);
                        $zabieg_content = preg_replace('/\s+/', ' ', $zabieg_content);
                        
                        // Ogranicz długość
                        if (strlen($zabieg_content) > 300) {
                            $zabieg_content = substr($zabieg_content, 0, 300) . '...';
                        }
                        
                        $content .= "Zabieg: $zabieg_name\n$zabieg_content\n\n";
                    }
                }
            }
        }
        
        // Alternatywnie, próbuj odczytać bezpośrednio z HTML strony
        if (empty($content)) {
            // Próbuj alternatywną metodę - po prostu wyodrębnij całą zawartość bc-content-container
            preg_match('/<div class="bc-content-container">(.*?)<\/div>/s', $post->post_content, $container_match);
            if (!empty($container_match[1])) {
                $container_content = wp_strip_all_tags($container_match[1]);
                $container_content = preg_replace('/\s+/', ' ', $container_content);
                $content = "Treść selektora zabiegów:\n" . $container_content;
            }
        }
        
        // Sprawdź bezpośrednio pliki na serwerze
        if (empty($content) && has_shortcode($post->post_content, 'bc_selector')) {
            // Poszukaj w plikach
            $upload_dir = wp_upload_dir();
            $selector_dir = $upload_dir['basedir'] . '/bc-selector';
            
            if (is_dir($selector_dir)) {
                $files = scandir($selector_dir);
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'html') {
                        $file_path = $selector_dir . '/' . $file;
                        $file_content = file_get_contents($file_path);
                        if ($file_content !== false) {
                            $zabieg_name = pathinfo($file, PATHINFO_FILENAME);
                            $zabieg_content = wp_strip_all_tags($file_content);
                            
                            // Ogranicz długość
                            if (strlen($zabieg_content) > 300) {
                                $zabieg_content = substr($zabieg_content, 0, 300) . '...';
                            }
                            
                            $content .= "Zabieg: $zabieg_name\n$zabieg_content\n\n";
                        }
                    }
                }
            }
        }
        
        // Zapisz w pamięci podręcznej na godzinę
        set_transient($selector_cache_key, $content, HOUR_IN_SECONDS);
        
        return $content;
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

    public function enqueue_scripts() {
        if (!$this->should_load_assets()) {
            return;
        }

        // Dołącz jQuery
        wp_enqueue_script('jquery');
        
        // Dołącz FontAwesome (dla ikon)
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
        
        // Dołącz plik CSS
        wp_enqueue_style(
            'bc-assistant-style',
            $this->plugin_url . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION
        );
        
        // Dołącz plik JS
        wp_enqueue_script(
            'bc-assistant-script',
            $this->plugin_url . 'assets/js/bc-assistant-script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION,
            true
        );
        
        // Przekaż zmienne do JS
        wp_localize_script('bc-assistant-script', 'bc_assistant_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('bc_assistant_nonce'),
            'bubble_text' => BC_Assistant_Config::get('button_text'),
            'bubble_icon' => BC_Assistant_Config::get('bubble_icon')
        ));
    }
    
    // Obsługa przesłania zapytania do API ChatGPT
    public function process_chat_request() {
        // Sprawdź nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
            wp_send_json_error('Błąd bezpieczeństwa');
            exit;
        }
        
        // Pobierz dane zapytania
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $conversation_history = isset($_POST['conversation']) ? json_decode(stripslashes($_POST['conversation']), true) : array();
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'default';
        $procedure_name = isset($_POST['procedure_name']) ? sanitize_text_field($_POST['procedure_name']) : '';
        $additional_context = isset($_POST['additional_context']) ? sanitize_textarea_field($_POST['additional_context']) : '';
        
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
        
        // Dodaj instrukcję o zwięzłości
        $system_message .= " Odpowiadaj krótko i zwięźle, maksymalnie 2-3 zdania, chyba że pytanie wymaga dłuższej odpowiedzi.";
        
        // Pobierz dodatkowy kontekst ze strony
        $page_content_context = $this->get_additional_context();
        
        // Dodaj kontekst z selektora jeśli został przekazany
        if (!empty($additional_context)) {
            $page_content_context .= "\n\n" . $additional_context;
        }
        
        if (!empty($page_content_context)) {
            // Dodaj kontekst strony do wiadomości systemowej
            $system_message .= "\n\nOto informacje o aktualnej stronie, która może pomóc Ci lepiej odpowiedzieć:\n" . $page_content_context;
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
        
        // Dodaj historię konwersacji (maksymalnie 6 ostatnich wiadomości dla oszczędności tokenów)
        if (!empty($conversation_history)) {
            $conversation_history = array_slice($conversation_history, -6);
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
            'temperature' => 0.5,  // Zmniejszona wartość dla bardziej zwięzłych odpowiedzi
            'max_tokens' => 250    // Zmniejszona wartość dla krótszych odpowiedzi
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
        // Sprawdź, czy użytkownik ma uprawnienia
        if (!current_user_can('manage_options')) {
            wp_die(__('Nie masz wystarczających uprawnień, aby uzyskać dostęp do tej strony.'));
        }
        
        // Zapisz ustawienia jeśli formularz został przesłany
        if (isset($_POST['bc_assistant_save_settings']) && check_admin_referer('bc_assistant_settings')) {
            // Zapisz podstawowe ustawienia
            BC_Assistant_Config::set('api_key', sanitize_text_field($_POST['bc_assistant_api_key']));
            BC_Assistant_Config::set('model', sanitize_text_field($_POST['bc_assistant_model']));
            
            // Zapisz wiadomości systemowe
            BC_Assistant_Config::set('system_message_default', sanitize_textarea_field($_POST['bc_assistant_system_message_default']));
            BC_Assistant_Config::set('system_message_procedure', sanitize_textarea_field($_POST['bc_assistant_system_message_procedure']));
            BC_Assistant_Config::set('system_message_contraindications', sanitize_textarea_field($_POST['bc_assistant_system_message_contraindications']));
            
            // Zapisz wiadomości powitalne
            BC_Assistant_Config::set('welcome_message_default', sanitize_textarea_field($_POST['bc_assistant_welcome_message_default']));
            BC_Assistant_Config::set('welcome_message_procedure', sanitize_textarea_field($_POST['bc_assistant_welcome_message_procedure']));
            BC_Assistant_Config::set('welcome_message_contraindications', sanitize_textarea_field($_POST['bc_assistant_welcome_message_contraindications']));
            
            // Zapisz ustawienia wyświetlania
BC_Assistant_Config::set('button_text', sanitize_text_field($_POST['bc_assistant_button_text']));
            BC_Assistant_Config::set('bubble_icon', sanitize_text_field($_POST['bc_assistant_bubble_icon']));
            BC_Assistant_Config::set('theme', sanitize_text_field($_POST['bc_assistant_theme']));
            
            // Zapisz ustawienia zaawansowane
            BC_Assistant_Config::set('context_detection', isset($_POST['bc_assistant_context_detection']) ? 1 : 0);
            BC_Assistant_Config::set('enable_logging', isset($_POST['bc_assistant_enable_logging']) ? 1 : 0);
            
            echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
        }
        
        // Pobierz aktualne ustawienia
        $settings = BC_Assistant_Config::get_all();
        
        // Wyświetl formularz ustawień
        include($this->plugin_path . 'templates/admin.php');
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
