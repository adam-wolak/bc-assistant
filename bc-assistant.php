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
    
    // Sprawdź czy plik istnieje i jest czytelny
    if (file_exists($dotenv) && is_readable($dotenv)) {
        // Logowanie dla debugowania
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC Assistant: .env file found at: ' . $dotenv);
        }
        
        // Wczytaj zawartość pliku
        $env_content = file_get_contents($dotenv);
        
        // Przetwórz każdą linię
        $lines = explode("\n", $env_content);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Pomiń puste linie i komentarze
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Podziel na klucz i wartość
            $pos = strpos($line, '=');
            if ($pos !== false) {
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                
                // Usuń cudzysłowy
                $value = trim($value, "'\"");
                
                // Ustaw zmienną środowiskową
                if (!getenv($key)) {
                    putenv("{$key}={$value}");
                    
                    // Logowanie dla debugowania
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('BC Assistant: Set environment variable: ' . $key);
                    }
                }
            }
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!file_exists($dotenv)) {
                error_log('BC Assistant: .env file NOT found at: ' . $dotenv);
            } elseif (!is_readable($dotenv)) {
                error_log('BC Assistant: .env file exists but is not readable at: ' . $dotenv);
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
        // Rejestracja indywidualnych ustawień
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
        wp_enqueue_style(
            'bc-assistant-style',
            BC_ASSISTANT_URL . 'assets/css/style.css',
            array(),
            BC_ASSISTANT_VERSION . '.' . time() // Dodaj timestamp, aby wymusić odświeżenie
        );
        
        // Dołącz tylko script.js
        wp_enqueue_script(
            'bc-assistant-script',
            BC_ASSISTANT_URL . 'assets/js/script.js',
            array('jquery'),
            BC_ASSISTANT_VERSION . '.' . time(), // Dodaj timestamp, aby wymusić odświeżenie
            true
        );
        
        // Pobierz konfigurację
        $config = BC_Assistant_Config::get_all();
        
        // Przekaż dane do skryptu
        wp_localize_script('bc-assistant-script', 'bcAssistantData', array(
            'model' => $config['model'],
            'position' => isset($config['bubble_position']) ? $config['bubble_position'] : 'bottom-right',
            'title' => 'Asystent Bielsko Clinic',
            'initialMessage' => $config['welcome_message_default'],
            'apiEndpoint' => admin_url('admin-ajax.php'),
            'action' => 'bc_assistant_send_message',
            'nonce' => wp_create_nonce('bc_assistant_nonce'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG,
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
        
        // Dołącz script.js do panelu administracyjnego
        wp_enqueue_script(
            'bc-assistant-admin-script',
            BC_ASSISTANT_URL . 'assets/js/script.js',
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
        $theme = $config->get('theme');
        $page_context = 'default';
        include BC_ASSISTANT_PATH . 'templates/bubble.php';
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

/**
 * Kod diagnostyczny do debugowania problemu z modelami w BC Assistant
 */

// Dodaj narzędzie diagnostyczne
function bc_assistant_add_debug_tool() {
    // Tylko dla administratorów
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Sprawdź czy uruchomiono narzędzie diagnostyczne
    if (isset($_GET['bc_debug']) && $_GET['bc_debug'] === 'model') {
        add_action('admin_notices', 'bc_assistant_debug_output');
    }
}
add_action('admin_init', 'bc_assistant_add_debug_tool');

// Wyświetl informacje diagnostyczne
function bc_assistant_debug_output() {
    // Testowy bezpośredni zapis modelu (pomiń standardowy formularz)
    if (isset($_POST['bc_debug_action']) && $_POST['bc_debug_action'] === 'test_save' && check_admin_referer('bc_debug_nonce')) {
        if (isset($_POST['test_model'])) {
            $model = sanitize_text_field($_POST['test_model']);
            
            // Wyczyść cache przed zapisem
            wp_cache_delete('alloptions', 'options');
            
            // Zapisz opcję
            $result = update_option('bc_assistant_model', $model);
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Model zapisany bezpośrednio: <strong>' . esc_html($model) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>Model nie zmienił się lub nie został zapisany.</p></div>';
            }
            
            // Zaloguj dla debugowania
            error_log('BC Assistant Debug: Model zapisany bezpośrednio: ' . $model . ' (wynik: ' . ($result ? 'sukces' : 'bez zmian') . ')');
        }
    }
    
    // Wyczyść opcję
    if (isset($_POST['bc_debug_action']) && $_POST['bc_debug_action'] === 'clear' && check_admin_referer('bc_debug_nonce')) {
        delete_option('bc_assistant_model');
        echo '<div class="notice notice-warning"><p>Opcja modelu wyczyszczona!</p></div>';
    }
    
    // Odśwież .env plik
    if (isset($_POST['bc_debug_action']) && $_POST['bc_debug_action'] === 'refresh_env' && check_admin_referer('bc_debug_nonce')) {
        // Sprawdź czy plik .env istnieje
        $env_file = ABSPATH . '.env';
        
        if (file_exists($env_file)) {
            // Spróbuj zaktualizować plik .env
            $current_model = get_option('bc_assistant_model', 'gpt-4o');
            
            // Wczytaj zawartość pliku
            $env_content = file_get_contents($env_file);
            
            // Zaktualizuj model w pliku .env
            $pattern = '/OPENAI_MODEL=(.+)/';
            $replacement = 'OPENAI_MODEL=' . $current_model;
            
            if (preg_match($pattern, $env_content)) {
                $new_content = preg_replace($pattern, $replacement, $env_content);
                $write_result = file_put_contents($env_file, $new_content);
                
                if ($write_result !== false) {
                    echo '<div class="notice notice-success"><p>Plik .env zaktualizowany z modelem: <strong>' . esc_html($current_model) . '</strong></p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Nie udało się zapisać do pliku .env. Sprawdź uprawnienia.</p></div>';
                }
            } else {
                // Jeśli nie znaleziono linii z modelem, dodaj ją na końcu pliku
                $new_content = $env_content . "\nOPENAI_MODEL=" . $current_model;
                $write_result = file_put_contents($env_file, $new_content);
                
                if ($write_result !== false) {
                    echo '<div class="notice notice-success"><p>Dodano model do pliku .env: <strong>' . esc_html($current_model) . '</strong></p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Nie udało się zapisać do pliku .env. Sprawdź uprawnienia.</p></div>';
                }
            }
        } else {
            echo '<div class="notice notice-error"><p>Plik .env nie istnieje w katalogu głównym WordPressa.</p></div>';
        }
    }
    
    // Sprawdź pliki skryptów
    if (isset($_POST['bc_debug_action']) && $_POST['bc_debug_action'] === 'check_scripts' && check_admin_referer('bc_debug_nonce')) {
        $script_file = plugin_dir_path(dirname(__FILE__)) . 'bc-assistant/assets/js/script.js';
        
        if (file_exists($script_file)) {
            $script_content = file_get_contents($script_file);
            
            // Szukaj wzorców odnoszących się do modelu
            $model_patterns = [
                'model:',
                'gpt-4o',
                'gpt-4-turbo',
                'gpt-3.5-turbo',
                'claude-3',
            ];
            
            $matches = [];
            foreach ($model_patterns as $pattern) {
                if (stripos($script_content, $pattern) !== false) {
                    $matches[] = $pattern;
                }
            }
            
            if (!empty($matches)) {
                echo '<div class="notice notice-info"><p>Znaleziono odniesienia do modeli w pliku script.js: <strong>' . esc_html(implode(', ', $matches)) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-info"><p>Nie znaleziono bezpośrednich odniesień do modeli w pliku script.js.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Nie znaleziono pliku script.js w katalogu pluginu.</p></div>';
        }
    }
    
    // Pobierz aktualny model
    $current_model = get_option('bc_assistant_model');
    
    // Sprawdź plik .env
    $env_model = 'nie znaleziono';
    $env_file = ABSPATH . '.env';
    
    if (file_exists($env_file) && is_readable($env_file)) {
        $env_content = file_get_contents($env_file);
        preg_match('/OPENAI_MODEL=(.+)/', $env_content, $matches);
        
        if (!empty($matches[1])) {
            $env_model = trim($matches[1]);
        }
    }
    
    // Wyświetl panel diagnostyczny
    ?>
    <div class="wrap" style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; margin-top: 20px;">
        <h1>BC Assistant - Diagnostyka</h1>
        
        <h2>Aktualny stan opcji</h2>
        <table class="widefat" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>Źródło</th>
                    <th>Wartość modelu</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>WordPress Option (get_option)</strong></td>
                    <td><?php echo esc_html($current_model !== false ? $current_model : '(nie ustawiono)'); ?></td>
                </tr>
                <tr>
                    <td><strong>Plik .env</strong></td>
                    <td><?php echo esc_html($env_model); ?></td>
                </tr>
            </tbody>
        </table>
        
        <h2>Test bezpośredniego zapisu modelu</h2>
        <p>Ten formularz zapisuje model bezpośrednio w bazie danych, z pominięciem standardowego formularza:</p>
        
        <form method="post" style="margin-bottom: 30px;">
            <?php wp_nonce_field('bc_debug_nonce'); ?>
            <select name="test_model" style="min-width: 200px;">
                <option value="gpt-4o" <?php selected($current_model, 'gpt-4o'); ?>>GPT-4o (flagowy model)</option>
                <option value="gpt-4-turbo" <?php selected($current_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                <option value="gpt-3.5-turbo" <?php selected($current_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                <option value="claude-3-opus-20240229" <?php selected($current_model, 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
                <option value="claude-3-sonnet-20240229" <?php selected($current_model, 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet</option>
                <option value="claude-3-haiku-20240307" <?php selected($current_model, 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku</option>
            </select>
            <input type="hidden" name="bc_debug_action" value="test_save">
            <button type="submit" class="button button-primary">Zapisz bezpośrednio</button>
        </form>
        
        <h2>Operacje konserwacyjne</h2>
        
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 30px;">
            <!-- Wyczyść opcję -->
            <form method="post">
                <?php wp_nonce_field('bc_debug_nonce'); ?>
                <input type="hidden" name="bc_debug_action" value="clear">
                <button type="submit" class="button">Wyczyść opcję modelu</button>
            </form>
            
            <!-- Odśwież plik .env -->
            <form method="post">
                <?php wp_nonce_field('bc_debug_nonce'); ?>
                <input type="hidden" name="bc_debug_action" value="refresh_env">
                <button type="submit" class="button">Zaktualizuj plik .env</button>
            </form>
            
            <!-- Sprawdź pliki skryptów -->
            <form method="post">
                <?php wp_nonce_field('bc_debug_nonce'); ?>
                <input type="hidden" name="bc_debug_action" value="check_scripts">
                <button type="submit" class="button">Sprawdź script.js</button>
            </form>
        </div>
        
        <h2>Wszystkie opcje pluginu BC Assistant</h2>
        <table class="widefat" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th>Nazwa opcji</th>
                    <th>Wartość</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Znajdź wszystkie opcje pasujące do wzorca
                global $wpdb;
                $similar_options = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%bc_assistant%'");
                
                if (!empty($similar_options)) {
                    foreach ($similar_options as $option) {
                        // Ukryj klucz API
                        $value = ($option->option_name === 'bc_assistant_api_key') ? '(ukryte)' : $option->option_value;
                        echo '<tr>';
                        echo '<td>' . esc_html($option->option_name) . '</td>';
                        echo '<td>' . esc_html($value) . '</td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr><td colspan="2">Nie znaleziono opcji BC Assistant w bazie danych.</td></tr>';
                }
                ?>
            </tbody>
        </table>
        
        <h2>Filtry i hooki mogące wpływać na opcje</h2>
        <?php
        global $wp_filter;
        $relevant_hooks = [
            'option_bc_assistant_model',
            'pre_option_bc_assistant_model',
            'default_option_bc_assistant_model',
            'pre_update_option_bc_assistant_model',
            'update_option_bc_assistant_model',
        ];
        
        echo '<ul>';
        foreach ($relevant_hooks as $hook) {
            if (isset($wp_filter[$hook])) {
                echo '<li><strong>' . esc_html($hook) . ':</strong> ';
                echo count($wp_filter[$hook]) . ' podpiętych funkcji</li>';
                
                // Pokaż szczegóły podpiętych funkcji
                echo '<ul>';
                foreach ($wp_filter[$hook] as $priority => $callbacks) {
                    foreach ($callbacks as $name => $callback) {
                        echo '<li>Priorytet ' . $priority . ': ';
                        if (is_array($callback['function'])) {
                            if (is_object($callback['function'][0])) {
                                echo esc_html(get_class($callback['function'][0]) . '->' . $callback['function'][1]);
                            } else {
                                echo esc_html((is_string($callback['function'][0]) ? $callback['function'][0] : 'Array') . '::' . $callback['function'][1]);
                            }
                        } else {
                            echo esc_html(is_string($callback['function']) ? $callback['function'] : 'Closure/Anonymous function');
                        }
                        echo '</li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<li><strong>' . esc_html($hook) . ':</strong> Brak podpiętych funkcji</li>';
            }
        }
        echo '</ul>';
        ?>
        
        <h2>Zawartość pliku .env</h2>
        <?php
        if (file_exists($env_file) && is_readable($env_file)) {
            $env_content = file_get_contents($env_file);
            
            // Ukryj klucz API przed wyświetleniem
            $env_content_safe = preg_replace('/(OPENAI_API_KEY=)([^\n]+)/', '$1********', $env_content);
            
            echo '<pre style="background: #f0f0f1; padding: 15px; overflow: auto; max-height: 400px;">';
            echo esc_html($env_content_safe);
            echo '</pre>';
        } else {
            echo '<p>Nie można odczytać pliku .env lub plik nie istnieje.</p>';
        }
        ?>
        
        <p style="margin-top: 20px;">
            <a href="<?php echo admin_url('admin.php?page=bc-assistant'); ?>" class="button">Wróć do ustawień</a>
        </p>
    </div>
    <?php
}

// Dodaj link do narzędzia diagnostycznego w menu admina
function bc_assistant_add_debug_link($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $wp_admin_bar->add_node([
        'id'    => 'bc-assistant-debug',
        'title' => 'BC Assistant Debug',
        'href'  => admin_url('?bc_debug=model'),
    ]);
}
add_action('admin_bar_menu', 'bc_assistant_add_debug_link', 999);

/**
 * Funkcje do obsługi API z dynamicznym wyborem modelu
 */

/**
 * Wysyła wiadomość do API i zwraca odpowiedź
 *
 * @param string $message Wiadomość do wysłania
 * @param string|null $thread_id ID wątku (opcjonalne)
 * @return array|WP_Error Odpowiedź API lub obiekt błędu
 */
function bc_assistant_api_request($message, $thread_id = null) {
    $api_key = BC_Assistant_Config::get('api_key');
    if (empty($api_key)) {
        error_log('BC Assistant: Missing API key');
        return new WP_Error('missing_api_key', 'Brak klucza API');
    }
    
    // Pobierz aktualnie wybrany model
    $model = BC_Assistant_Config::get_current_model();
    error_log('BC Assistant: Current model: ' . $model);
    
    // Określ typ API na podstawie modelu
    $api_type = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
    error_log('BC Assistant: API type: ' . $api_type);
    
    // Logowanie dla debugowania
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('BC Assistant: Calling API ' . $api_type . ' with model: ' . $model);
    }
    
    // Wybierz odpowiednią metodę API
    if ($api_type === 'anthropic') {
        return bc_assistant_call_anthropic_api($message, $model, $api_key, $thread_id);
    } else {
        return bc_assistant_call_openai_api($message, $model, $api_key, $thread_id);
    }
}

/**
 * Wywołuje API OpenAI
 *
 * @param string $message Wiadomość do wysłania
 * @param string $model Model do użycia
 * @param string $api_key Klucz API
 * @param string|null $thread_id ID wątku (opcjonalne)
 * @return array|WP_Error Odpowiedź API lub obiekt błędu
 */
function bc_assistant_call_openai_api($message, $model, $api_key, $thread_id = null) {
    // Przygotuj dane zapytania
    $request_body = array(
        'model' => $model,
        'messages' => array(
            array(
                'role' => 'system',
                'content' => BC_Assistant_Config::get('system_message_default')
            ),
            array(
                'role' => 'user',
                'content' => $message
            )
        ),
        'temperature' => 0.7,
        'max_tokens' => 1000
    );
    
    // Jeśli istnieje thread_id, pobierz historię wiadomości
    if ($thread_id) {
        // Tutaj kod do pobierania historii wiadomości z bazy danych
        // i dodania ich do tablicy messages
    }
    
    // Wyślij zapytanie do API OpenAI
    $response = wp_remote_post(
        'https://api.openai.com/v1/chat/completions',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 60
        )
    );
    
// Sprawdź czy zapytanie się powiodło
    if (is_wp_error($response)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC Assistant: Błąd API: ' . $response->get_error_message());
        }
        return $response;
    }
    
    // Pobierz kod odpowiedzi
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC Assistant: Błąd API, kod: ' . $response_code);
        }
        return new WP_Error(
            'api_error',
            'Błąd API: ' . $response_code,
            array(
                'code' => $response_code,
                'response' => wp_remote_retrieve_body($response)
            )
        );
    }
    
    // Zdekoduj odpowiedź
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Sprawdź czy odpowiedź zawiera wiadomość
    if (!isset($data['choices'][0]['message']['content'])) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BC Assistant: Nieprawidłowa odpowiedź API');
        }
        return new WP_Error('invalid_response', 'Nieprawidłowa odpowiedź API');
    }
    
    // Zwróć odpowiedź
    return array(
        'message' => $data['choices'][0]['message']['content'],
        'thread_id' => $thread_id
    );
}

/**
 * Wywołuje API OpenAI Assistants
 *
 * @param string $message Wiadomość do wysłania
 * @param string $api_key Klucz API
 * @param string $assistant_id ID asystenta
 * @param string|null $thread_id ID wątku (opcjonalne)
 * @return array|WP_Error Odpowiedź API lub obiekt błędu
 */
function bc_assistant_call_openai_assistants_api($message, $api_key, $assistant_id, $thread_id = null) {
    // Jeśli nie mamy thread_id, utwórz nowy wątek
    if (empty($thread_id)) {
        // Utwórz nowy wątek
        $thread_response = wp_remote_post(
            'https://api.openai.com/v1/threads',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v1'
                ),
                'body' => json_encode(array()),
                'timeout' => 30
            )
        );
        
        // Obsługa błędów
        if (is_wp_error($thread_response)) {
            error_log('BC Assistant: Error creating thread: ' . $thread_response->get_error_message());
            return $thread_response;
        }
        
        // Pobierz ID wątku
        $thread_data = json_decode(wp_remote_retrieve_body($thread_response), true);
        if (!isset($thread_data['id'])) {
            error_log('BC Assistant: Invalid thread response: ' . wp_remote_retrieve_body($thread_response));
            return new WP_Error('invalid_response', 'Invalid thread response');
        }
        
        $thread_id = $thread_data['id'];
        error_log('BC Assistant: Created new thread: ' . $thread_id);
    }
    
    // Dodaj wiadomość do wątku
    $message_response = wp_remote_post(
        'https://api.openai.com/v1/threads/' . $thread_id . '/messages',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ),
            'body' => json_encode(array(
                'role' => 'user',
                'content' => $message
            )),
            'timeout' => 30
        )
    );
    
    // Obsługa błędów
    if (is_wp_error($message_response)) {
        error_log('BC Assistant: Error adding message: ' . $message_response->get_error_message());
        return $message_response;
    }
    
    // Uruchom asystenta
    $run_response = wp_remote_post(
        'https://api.openai.com/v1/threads/' . $thread_id . '/runs',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ),
            'body' => json_encode(array(
                'assistant_id' => $assistant_id
            )),
            'timeout' => 60
        )
    );
    
    // Obsługa błędów
    if (is_wp_error($run_response)) {
        error_log('BC Assistant: Error starting run: ' . $run_response->get_error_message());
        return $run_response;
    }
    
    // Pobierz ID uruchomienia
    $run_data = json_decode(wp_remote_retrieve_body($run_response), true);
    if (!isset($run_data['id'])) {
        error_log('BC Assistant: Invalid run response: ' . wp_remote_retrieve_body($run_response));
        return new WP_Error('invalid_response', 'Invalid run response');
    }
    
    $run_id = $run_data['id'];
    error_log('BC Assistant: Started run: ' . $run_id);
    
    // Czekaj na zakończenie uruchomienia
    $status = '';
    $max_retries = 15; // Maksymalna liczba prób
    $retry_count = 0;
    
    while ($retry_count < $max_retries) {
        // Pobierz status uruchomienia
        $status_response = wp_remote_get(
            'https://api.openai.com/v1/threads/' . $thread_id . '/runs/' . $run_id,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                    'OpenAI-Beta' => 'assistants=v1'
                ),
                'timeout' => 30
            )
        );
        
        // Obsługa błędów
        if (is_wp_error($status_response)) {
            error_log('BC Assistant: Error checking run status: ' . $status_response->get_error_message());
            return $status_response;
        }
        
        // Pobierz status
        $status_data = json_decode(wp_remote_retrieve_body($status_response), true);
        if (!isset($status_data['status'])) {
            error_log('BC Assistant: Invalid status response: ' . wp_remote_retrieve_body($status_response));
            return new WP_Error('invalid_response', 'Invalid status response');
        }
        
        $status = $status_data['status'];
        error_log('BC Assistant: Run status: ' . $status);
        
        // Sprawdź czy uruchomienie zakończyło się
        if ($status === 'completed') {
            break;
        } elseif ($status === 'failed' || $status === 'cancelled') {
            error_log('BC Assistant: Run failed or cancelled: ' . wp_remote_retrieve_body($status_response));
            return new WP_Error('run_failed', 'Run failed or cancelled');
        }
        
        // Poczekaj przed kolejną próbą
        sleep(1);
        $retry_count++;
    }
    
    // Jeśli przekroczyliśmy maksymalną liczbę prób, zwróć błąd
    if ($retry_count >= $max_retries) {
        error_log('BC Assistant: Run timed out');
        return new WP_Error('run_timeout', 'Run timed out');
    }
    
    // Pobierz wiadomości asystenta
    $messages_response = wp_remote_get(
        'https://api.openai.com/v1/threads/' . $thread_id . '/messages',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v1'
            ),
            'timeout' => 30
        )
    );
    
    // Obsługa błędów
    if (is_wp_error($messages_response)) {
        error_log('BC Assistant: Error getting messages: ' . $messages_response->get_error_message());
        return $messages_response;
    }
    
    // Pobierz wiadomości
    $messages_data = json_decode(wp_remote_retrieve_body($messages_response), true);
    if (!isset($messages_data['data']) || !is_array($messages_data['data']) || empty($messages_data['data'])) {
        error_log('BC Assistant: Invalid messages response: ' . wp_remote_retrieve_body($messages_response));
        return new WP_Error('invalid_response', 'Invalid messages response');
    }
    
    // Znajdź ostatnią wiadomość asystenta
    $assistant_message = null;
    foreach ($messages_data['data'] as $message_item) {
        if ($message_item['role'] === 'assistant') {
            $assistant_message = $message_item;
            break;
        }
    }
    
    if (!$assistant_message || !isset($assistant_message['content']) || empty($assistant_message['content'])) {
        error_log('BC Assistant: No assistant message found');
        return new WP_Error('no_assistant_message', 'No assistant message found');
    }
    
    // Pobierz treść wiadomości
    $message_content = '';
    foreach ($assistant_message['content'] as $content_item) {
        if ($content_item['type'] === 'text') {
            $message_content = $content_item['text']['value'];
            break;
        }
    }
    
    // Zwróć odpowiedź
    return array(
        'message' => $message_content,
        'thread_id' => $thread_id
    );
}

/**
 * Obsługa AJAX dla wysyłania wiadomości
 */
function bc_assistant_ajax_send_message() {
    // Dodajmy logowanie dla debugowania
    error_log('BC Assistant: AJAX message received');
    
    // Sprawdź nonce dla bezpieczeństwa
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
        error_log('BC Assistant: Nonce verification failed');
        wp_send_json_error(array('message' => 'Błąd weryfikacji bezpieczeństwa'));
        exit;
    }
    
    // Pobierz wiadomość i thread_id
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $thread_id = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : null;
    
    if (empty($message)) {
        error_log('BC Assistant: Empty message');
        wp_send_json_error(array('message' => 'Wiadomość nie może być pusta'));
        exit;
    }
    
    // Loguj model
    error_log('BC Assistant: Using model: ' . BC_Assistant_Config::get_current_model());
    
    // Wyślij wiadomość do API
    $response = bc_assistant_api_request($message, $thread_id);
    
    // Sprawdź czy wystąpił błąd
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $error_data = $response->get_error_data();
        
        error_log('BC Assistant: API Error: ' . $error_message);
        
        $debug_info = '';
        if (defined('WP_DEBUG') && WP_DEBUG && $error_data) {
            $debug_info = ' Szczegóły: ' . print_r($error_data, true);
            error_log('BC Assistant: Error details: ' . $debug_info);
        }
        
        wp_send_json_error(array(
            'message' => 'Przepraszam, wystąpił błąd. Spróbuj ponownie.'
        ));
        exit;
    }
    
    // Zwróć odpowiedź
    wp_send_json_success($response);
    exit;
}

add_action('wp_ajax_bc_assistant_send_message', 'bc_assistant_ajax_send_message');
add_action('wp_ajax_nopriv_bc_assistant_send_message', 'bc_assistant_ajax_send_message');

/**
 * Dodaj shortcode do wyświetlania czatu
 */
function bc_assistant_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => 'Asystent',
        'placeholder' => 'Wpisz swoją wiadomość...',
        'welcome' => '',
    ), $atts, 'bc_assistant');
    
    ob_start();
    include BC_ASSISTANT_PATH . 'templates/embedded.php';
    return ob_get_clean();
}
add_shortcode('bc_assistant', 'bc_assistant_shortcode');

/**
 * Dodaj atrybut defer do skryptów JS
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

/**
 * Dodaj link do ustawień na stronie pluginów
 */
function bc_assistant_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=bc-assistant') . '">Ustawienia</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'bc_assistant_plugin_action_links');

/**
 * Sprawdź czy wszystkie wymagane ustawienia są skonfigurowane
 */
function bc_assistant_check_settings() {
    $api_key = BC_Assistant_Config::get('api_key');
    
    if (empty($api_key)) {
        add_action('admin_notices', 'bc_assistant_api_key_notice');
    }
}
add_action('admin_init', 'bc_assistant_check_settings');

/**
 * Powiadomienie o braku klucza API
 */
function bc_assistant_api_key_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
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
 * Dodaj prosty debugger dla zalogowanych administratorów
 */
function bc_assistant_add_debug_info($content) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return $content;
    }
    
    // Sprawdź czy włączono debugowanie
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return $content;
    }
    
    // Dodaj informacje dla administratorów na końcu treści
    $debug = '';
    $debug .= '<div style="margin-top: 50px; padding: 20px; background-color: #f8f8f8; border: 1px solid #ddd;">';
    $debug .= '<h3>BC Assistant - Debug Info (tylko dla administratorów)</h3>';
    $debug .= '<p>Model: ' . esc_html(BC_Assistant_Config::get_current_model()) . '</p>';
    $debug .= '<p>API Key: ' . (BC_Assistant_Config::get('api_key') ? 'Skonfigurowany' : 'Brak') . '</p>';
    
    // Sprawdź plik .env
    $env_file = ABSPATH . '.env';
    if (file_exists($env_file) && is_readable($env_file)) {
        $env_content = file_get_contents($env_file);
        if (preg_match('/OPENAI_MODEL=(.+)/', $env_content, $matches)) {
            $debug .= '<p>.env Model: ' . esc_html(trim($matches[1])) . '</p>';
        }
    }
    
    $debug .= '<p><a href="' . esc_url(admin_url('?bc_debug=model')) . '">Otwórz narzędzie diagnostyczne</a></p>';
    $debug .= '</div>';
    
    return $content . $debug;
}
add_filter('the_content', 'bc_assistant_add_debug_info', 999);

/**
 * Dodaj obsługę CORS dla API
 */
function bc_assistant_add_cors_headers() {
    // Sprawdź czy to żądanie do naszego API
    if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], site_url()) !== false) {
        header('Access-Control-Allow-Origin: ' . esc_url_raw(site_url()));
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, X-WP-Nonce');
    }
}
add_action('init', 'bc_assistant_add_cors_headers');

/**
 * Dodaj wsparcie dla przycisku w edytorze Gutenberg
 */
function bc_assistant_register_gutenberg_button() {
    if (!function_exists('register_block_type')) {
        return;
    }
    
    wp_register_script(
        'bc-assistant-gutenberg',
        BC_ASSISTANT_URL . 'assets/js/gutenberg.js',
        array('wp-blocks', 'wp-element', 'wp-editor'),
        BC_ASSISTANT_VERSION,
        true
    );
    
    register_block_type('bc-assistant/chat-button', array(
        'editor_script' => 'bc-assistant-gutenberg',
    ));
}
add_action('init', 'bc_assistant_register_gutenberg_button');

/**
 * Dodaj aktualizację automatyczną
 */
function bc_assistant_check_for_updates() {
    // Ta funkcja będzie zaimplementowana w przyszłych wersjach
    // Sprawdzanie aktualizacji i pobieranie nowych wersji pluginu
}
add_action('admin_init', 'bc_assistant_check_for_updates');