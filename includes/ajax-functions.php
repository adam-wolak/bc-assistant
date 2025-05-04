<?php
/**
 * BC Assistant - Improved AJAX Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle AJAX request for sending messages
 */
function bc_assistant_ajax_send_message() {
    // Log for debugging
    BC_Assistant_Helper::log('AJAX message received');
    
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'bc_assistant_nonce')) {
        BC_Assistant_Helper::log('Nonce verification failed');
        wp_send_json_error(array('message' => 'Błąd weryfikacji bezpieczeństwa'));
        exit;
    }
    
    // Get message and thread_id
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
    $thread_id = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : null;
    
    // Get page context data
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'default';
    $procedure_name = isset($_POST['procedure_name']) ? sanitize_text_field($_POST['procedure_name']) : '';
    
    // Check for empty message
    if (empty($message)) {
        BC_Assistant_Helper::log('Empty message');
        wp_send_json_error(array('message' => 'Wiadomość nie może być pusta'));
        exit;
    }
    
    // Log the model being used
    BC_Assistant_Helper::log('Using model: ' . BC_Assistant_Config::get_current_model());
    
    // Prepare page context for system message selection
    $page_context = array(
        'context' => $context,
        'procedure_name' => $procedure_name
    );
    
    // Send message to API
    $response = bc_assistant_api_request($message, $thread_id, $page_context);
    
    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $error_data = $response->get_error_data();
        
        BC_Assistant_Helper::log('API Error: ' . $error_message, $error_data);
        
        // Provide user-friendly error message
        wp_send_json_error(array(
            'message' => 'Przepraszam, wystąpił błąd. Spróbuj ponownie.'
        ));
        exit;
    }
    
    // Log success
    BC_Assistant_Helper::log('API response received successfully');
    
    // Return response
    wp_send_json_success($response);
    exit;
}

// Register AJAX functions
add_action('wp_ajax_bc_assistant_send_message', 'bc_assistant_ajax_send_message');
add_action('wp_ajax_nopriv_bc_assistant_send_message', 'bc_assistant_ajax_send_message');

/**
 * Modified API request function to use page context
 *
 * @param string $message Message to send
 * @param string|null $thread_id Thread ID (optional)
 * @param array $page_context Page context information
 * @return array|WP_Error API response or error object
 */
function bc_assistant_api_request($message, $thread_id = null, $page_context = null) {
    $api_key = BC_Assistant_Config::get('api_key');
    if (empty($api_key)) {
        BC_Assistant_Helper::log('Missing API key');
        return new WP_Error('missing_api_key', 'Brak klucza API');
    }
    
    // Get current model
    $model = BC_Assistant_Config::get_current_model();
    BC_Assistant_Helper::log('Current model: ' . $model);
    
    // Determine API type based on model
    $api_type = (strpos($model, 'claude') !== false) ? 'anthropic' : 'openai';
    BC_Assistant_Helper::log('API type: ' . $api_type);
    
    // If no page context provided, use default
    if ($page_context === null) {
        $page_context = array(
            'context' => 'default',
            'procedure_name' => ''
        );
    }
    
    // Get appropriate system message based on context
    $system_message = BC_Assistant_Helper::get_system_message($page_context);
    
    // Use appropriate API method
    if ($api_type === 'anthropic') {
        return bc_assistant_call_anthropic_api($message, $model, $api_key, $thread_id, $system_message);
    } else {
        // Check if using Assistants API or standard Chat API
        $assistant_id = getenv('OPENAI_ASSISTANT_ID');
        if (!empty($assistant_id)) {
            return bc_assistant_call_openai_assistants_api($message, $api_key, $assistant_id, $thread_id);
        } else {
            return bc_assistant_call_openai_api($message, $model, $api_key, $thread_id, $system_message);
        }
    }
}

/**
 * Modified OpenAI API call function
 *
 * @param string $message Message to send
 * @param string $model Model to use
 * @param string $api_key API key
 * @param string|null $thread_id Thread ID (optional)
 * @param string $system_message System message
 * @return array|WP_Error API response or error object
 */
function bc_assistant_call_openai_api($message, $model, $api_key, $thread_id = null, $system_message = null) {
    // If no system message provided, use default
    if ($system_message === null) {
        $system_message = BC_Assistant_Config::get('system_message_default');
    }
    
    // Prepare messages array
    $messages = array(
        array(
            'role' => 'system',
            'content' => $system_message
        )
    );
    
    // Add conversation history if thread_id exists
    if ($thread_id) {
        // Here you would add code to retrieve message history from database
        // and add it to the messages array
    }
    
    // Add current user message
    $messages[] = array(
        'role' => 'user',
        'content' => $message
    );
    
    // Prepare request body
    $request_body = array(
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 1000
    );
    
    // Send request to OpenAI API
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
    
    // Check for errors
    if (is_wp_error($response)) {
        BC_Assistant_Helper::log('API Error: ' . $response->get_error_message());
        return $response;
    }
    
    // Get response code
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        BC_Assistant_Helper::log('API Error, code: ' . $response_code, wp_remote_retrieve_body($response));
        return new WP_Error(
            'api_error',
            'API Error: ' . $response_code,
            array(
                'code' => $response_code,
                'response' => wp_remote_retrieve_body($response)
            )
        );
    }
    
    // Decode response
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check if response contains message
    if (!isset($data['choices'][0]['message']['content'])) {
        BC_Assistant_Helper::log('Invalid API response', $data);
        return new WP_Error('invalid_response', 'Invalid API response');
    }
    
    // Return response
    return array(
        'message' => $data['choices'][0]['message']['content'],
        'thread_id' => $thread_id
    );
}

/**
 * Modified Anthropic API call function
 *
 * @param string $message Message to send
 * @param string $model Model to use
 * @param string $api_key API key
 * @param string|null $thread_id Thread ID (optional)
 * @param string $system_message System message
 * @return array|WP_Error API response or error object
 */
function bc_assistant_call_anthropic_api($message, $model, $api_key, $thread_id = null, $system_message = null) {
    // If no system message provided, use default
    if ($system_message === null) {
        $system_message = BC_Assistant_Config::get('system_message_default');
    }
    
    // Prepare messages array
    $messages = array();
    
    // Add conversation history if thread_id exists
    if ($thread_id) {
        // Here you would add code to retrieve message history from database
        // and add it to the messages array
    }
    
    // Add current user message
    $messages[] = array(
        'role' => 'user',
        'content' => $message
    );
    
    // Prepare request body
    $request_body = array(
        'model' => $model,
        'max_tokens' => 1000,
        'temperature' => 0.7,
        'system' => $system_message,
        'messages' => $messages
    );
    
    // Send request to Anthropic API
    $response = wp_remote_post(
        'https://api.anthropic.com/v1/messages',
        array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($request_body),
            'timeout' => 60
        )
    );
    
    // Check for errors
    if (is_wp_error($response)) {
        BC_Assistant_Helper::log('Anthropic API Error: ' . $response->get_error_message());
        return $response;
    }
    
    // Get response code
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        BC_Assistant_Helper::log('Anthropic API Error, code: ' . $response_code, wp_remote_retrieve_body($response));
        return new WP_Error(
            'api_error',
            'Anthropic API Error: ' . $response_code,
            array(
                'code' => $response_code,
                'response' => wp_remote_retrieve_body($response)
            )
        );
    }
    
    // Decode response
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    // Check if response contains message
    if (!isset($data['content']) || !is_array($data['content']) || empty($data['content'])) {
        BC_Assistant_Helper::log('Invalid Anthropic API response', $data);
        return new WP_Error('invalid_response', 'Invalid Anthropic API response');
    }
    
    // Extract text from content array
    $message_content = '';
    foreach ($data['content'] as $content_part) {
        if ($content_part['type'] === 'text') {
            $message_content .= $content_part['text'];
        }
    }
    
    // Return response
    return array(
        'message' => $message_content,
        'thread_id' => $thread_id
    );
}