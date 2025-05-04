<?php
/**
 * BC Assistant - Funkcje AJAX
 */

if (!defined('ABSPATH')) {
    exit;
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

// Zarejestruj funkcje AJAX
add_action('wp_ajax_bc_assistant_send_message', 'bc_assistant_ajax_send_message');
add_action('wp_ajax_nopriv_bc_assistant_send_message', 'bc_assistant_ajax_send_message');