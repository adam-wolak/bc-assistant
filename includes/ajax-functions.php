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
    
    // Check if this is a voice message
    $is_voice = isset($_POST['is_voice']) && $_POST['is_voice'] === 'true';
    
    if ($is_voice) {
        // Handle voice message
        if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
            BC_Assistant_Helper::log('Audio file upload error');
            wp_send_json_error(array('message' => 'Błąd przesyłania pliku audio'));
            exit;
        }
        
        // Get thread ID
        $thread_id = isset($_POST['thread_id']) ? sanitize_text_field($_POST['thread_id']) : null;
        
        // Get page context data
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'default';
        $procedure_name = isset($_POST['procedure_name']) ? sanitize_text_field($_POST['procedure_name']) : '';
        
        // Process audio file with OpenAI
        $response = bc_assistant_process_audio($_FILES['audio']['tmp_name'], $thread_id, array(
            'context' => $context,
            'procedure_name' => $procedure_name
        ));
    } else {
        // Handle text message
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
    }
    
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

/**
 * Process audio file and convert it to text using OpenAI API
 * 
 * @param string $audio_file Path to temporary audio file
 * @param string|null $thread_id Thread ID
 * @param array $page_context Page context information
 * @return array API response
 */
function bc_assistant_process_audio($audio_file, $thread_id = null, $page_context = null) {
    // Log for debugging
    BC_Assistant_Helper::log('Processing audio file');
    
    if (!file_exists($audio_file)) {
        BC_Assistant_Helper::log('Audio file not found');
        return new WP_Error('missing_file', 'Plik audio nie został znaleziony');
    }
    
    // Get API key
    $api_key = BC_Assistant_Config::get('api_key');
    if (empty($api_key)) {
        BC_Assistant_Helper::log('Missing API key');
        return new WP_Error('missing_api_key', 'Brak klucza API');
    }
    
    // Determine API type to use
    $api_type = BC_Assistant_Config::get('whisper_api_provider', 'openai');
    
    // Process audio using appropriate API
    if ($api_type === 'anthropic') {
        // Currently Anthropic doesn't have a speech-to-text API, fall back to OpenAI
        BC_Assistant_Helper::log('Falling back to OpenAI for speech-to-text');
        $api_type = 'openai';
    }
    
    // Process with OpenAI's Whisper API
    $transcription = bc_assistant_transcribe_audio_openai($audio_file, $api_key);
    
    // Check for transcription errors
    if (is_wp_error($transcription)) {
        BC_Assistant_Helper::log('Transcription error: ' . $transcription->get_error_message());
        return $transcription;
    }
	// Log success
    BC_Assistant_Helper::log('Audio transcribed successfully: ' . substr($transcription, 0, 100) . '...');
	
	// Check for special commands like "połącz z recepcją"
    $special_command = false;
    $lower_transcription = mb_strtolower(trim($transcription), 'UTF-8');
    
    // Frazy do rozpoznawania
    $connection_phrases = [
        'połącz z recepcją',
        'połącz mnie z recepcją',
        'zadzwoń do recepcji',
        'chcę rozmawiać z recepcją',
        'potrzebuję połączenia z recepcją',
        'kontakt z recepcją'
    ];
    
    foreach ($connection_phrases as $phrase) {
        if (strpos($lower_transcription, $phrase) !== false) {
            // To jest komenda do połączenia z recepcją
            $special_command = true;
            
            // Numer telefonu recepcji - ustaw odpowiedni
            $reception_phone = '+48123456789';
            
            // Zwróć specjalną odpowiedź, która zostanie zinterpretowana przez klienta JS
            return [
                'message' => 'Już łączę z recepcją. Proszę czekać...',
                'transcription' => $transcription,
                'thread_id' => $thread_id,
                'special_action' => 'call_reception',
                'phone_number' => $reception_phone
            ];
        }
    }
    
    // Jeśli nie jest to specjalna komenda, wykonaj standardowe przetwarzanie
    if (!$special_command) {
        // Now process the transcribed text like a regular message
        $response = bc_assistant_api_request($transcription, $thread_id, $page_context);
        
        // If successful, add transcription to response and add follow-up prompt
        if (!is_wp_error($response)) {
            $response['transcription'] = $transcription;
            
            // Append a prompt for further questions to the response
            $response['message'] = $response['message'] . ' Czy masz jeszcze jakieś pytania?';
            
            // Generate speech from response
            $api_key = BC_Assistant_Config::get('api_key');
            $speech = bc_assistant_text_to_speech($response['message'], $api_key);
            
            if (!is_wp_error($speech)) {
                $response['speech'] = $speech;
            }
        }
        
        return $response;
    }
}

/**
 * Convert text to speech using OpenAI TTS API
 * 
 * @param string $text Text to convert to speech
 * @param string $api_key OpenAI API key
 * @return string|WP_Error Base64 encoded audio or error
 */
function bc_assistant_text_to_speech($text, $api_key) {
    // Limit text length to prevent API errors (OpenAI TTS has a character limit)
    $max_length = 4096;
    if (strlen($text) > $max_length) {
        $text = substr($text, 0, $max_length - 3) . '...';
    }
    
    // Set up curl request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/speech');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    
    // Create request body
    $request_body = json_encode([
        'model' => 'tts-1',
        'input' => $text,
        'voice' => 'alloy',  // Using nova voice (girl) or alloy, echo, fable, onyx, shimmer
		'speed' => 0.9,
        'response_format' => 'mp3',
    ]);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
    
    // Execute request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        BC_Assistant_Helper::log('cURL error: ' . $error);
        return new WP_Error('curl_error', 'Błąd połączenia: ' . $error);
    }
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for successful response
    if ($http_code !== 200) {
        BC_Assistant_Helper::log('TTS API Error: ' . $http_code);
        return new WP_Error('api_error', 'API Error: ' . $http_code);
    }
    
    // Convert binary audio to base64 for transmission
    $base64_audio = base64_encode($response);
    
    return $base64_audio;
}

/**
 * Transcribe audio file using OpenAI's Whisper API
 * 
 * @param string $audio_file Path to audio file
 * @param string $api_key OpenAI API key
 * @return string|WP_Error Transcribed text or error
 */
function bc_assistant_transcribe_audio_openai($audio_file, $api_key) {
    // Prepare file for upload
    if (!function_exists('curl_file_create')) {
        function curl_file_create($filename, $mimetype = '', $postname = '') {
            return "@$filename;filename="
                . ($postname ?: basename($filename))
                . ($mimetype ? ";type=$mimetype" : '');
        }
    }
    
    // Get file MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $audio_file);
    finfo_close($finfo);
    
    // Create CURLFile
    $cfile = curl_file_create($audio_file, $mime_type, 'audio.wav');
    
    // Set up curl request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => $cfile,
        'model' => 'whisper-1',
        'language' => 'pl', // Set language to Polish
        'response_format' => 'json',
    ]);
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        BC_Assistant_Helper::log('cURL error: ' . $error);
        return new WP_Error('curl_error', 'Błąd połączenia: ' . $error);
    }
    
    // Get HTTP status code
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check for successful response
    if ($http_code !== 200) {
        BC_Assistant_Helper::log('API Error: ' . $http_code . ' - ' . $response);
        return new WP_Error('api_error', 'API Error: ' . $http_code, $response);
    }
    
    // Decode response
    $data = json_decode($response, true);
    
    // Check if we have a transcription
    if (!isset($data['text'])) {
        BC_Assistant_Helper::log('Invalid API response', $data);
        return new WP_Error('invalid_response', 'Invalid API response', $data);
    }
    
    // Return transcribed text
    return $data['text'];
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

/**
 * Call OpenAI Assistants API v2
 *
 * @param string $message Message to send
 * @param string $api_key API key
 * @param string $assistant_id Assistant ID
 * @param string|null $thread_id Thread ID (optional)
 * @return array|WP_Error API response or error object
 */
function bc_assistant_call_openai_assistants_api($message, $api_key, $assistant_id, $thread_id = null) {
    // Set common headers for v2 API
    $headers = array(
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
        'OpenAI-Beta' => 'assistants=v2' // Updated to v2
    );
    
    // If no thread_id, we'll use the createThreadAndRun endpoint for efficiency
    if (empty($thread_id)) {
        BC_Assistant_Helper::log('No thread ID provided, creating new thread and run');
        
        // Use createThreadAndRun endpoint
        $response = wp_remote_post(
            'https://api.openai.com/v1/threads/runs',
            array(
                'headers' => $headers,
                'body' => json_encode(array(
                    'assistant_id' => $assistant_id,
                    'thread' => array(
                        'messages' => array(
                            array(
                                'role' => 'user',
                                'content' => $message
                            )
                        )
                    )
                )),
                'timeout' => 60
            )
        );
        
        // Handle errors
        if (is_wp_error($response)) {
            BC_Assistant_Helper::log('Error creating thread and run: ' . $response->get_error_message());
            return $response;
        }
        
        // Check response
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            BC_Assistant_Helper::log('Error response: ' . $response_code . ' - ' . wp_remote_retrieve_body($response));
            return new WP_Error('api_error', 'API Error: ' . $response_code, wp_remote_retrieve_body($response));
        }
        
        // Get run data
        $run_data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($run_data['thread_id']) || !isset($run_data['id'])) {
            BC_Assistant_Helper::log('Invalid response format: ' . wp_remote_retrieve_body($response));
            return new WP_Error('invalid_response', 'Invalid response format', wp_remote_retrieve_body($response));
        }
        
        // Get thread ID and run ID
        $thread_id = $run_data['thread_id'];
        $run_id = $run_data['id'];
        
        BC_Assistant_Helper::log('Created thread: ' . $thread_id . ' and run: ' . $run_id);
    } else {
        // We have a thread ID, so add message to existing thread and create run
        BC_Assistant_Helper::log('Using existing thread: ' . $thread_id);
        
        // Add message to thread
        $message_response = wp_remote_post(
            'https://api.openai.com/v1/threads/' . $thread_id . '/messages',
            array(
                'headers' => $headers,
                'body' => json_encode(array(
                    'role' => 'user',
                    'content' => $message
                )),
                'timeout' => 30
            )
        );
        
        // Handle errors
        if (is_wp_error($message_response)) {
            BC_Assistant_Helper::log('Error adding message: ' . $message_response->get_error_message());
            return $message_response;
        }
        
        // Create run
        $run_response = wp_remote_post(
            'https://api.openai.com/v1/threads/' . $thread_id . '/runs',
            array(
                'headers' => $headers,
                'body' => json_encode(array(
                    'assistant_id' => $assistant_id
                )),
                'timeout' => 30
            )
        );
        
        // Handle errors
        if (is_wp_error($run_response)) {
            BC_Assistant_Helper::log('Error creating run: ' . $run_response->get_error_message());
            return $run_response;
        }
        
        // Get run ID
        $run_data = json_decode(wp_remote_retrieve_body($run_response), true);
        if (!isset($run_data['id'])) {
            BC_Assistant_Helper::log('Invalid run response: ' . wp_remote_retrieve_body($run_response));
            return new WP_Error('invalid_response', 'Invalid run response', wp_remote_retrieve_body($run_response));
        }
        
        $run_id = $run_data['id'];
        BC_Assistant_Helper::log('Created run: ' . $run_id);
    }
    
    // Now we have thread_id and run_id, wait for run to complete
    $status = '';
    $max_retries = 15;
    $retry_count = 0;
    
    while ($retry_count < $max_retries) {
        // Wait before checking status
        sleep(1);
        
        // Get run status
        $status_response = wp_remote_get(
            'https://api.openai.com/v1/threads/' . $thread_id . '/runs/' . $run_id,
            array(
                'headers' => $headers,
                'timeout' => 30
            )
        );
        
        // Handle errors
        if (is_wp_error($status_response)) {
            BC_Assistant_Helper::log('Error checking run status: ' . $status_response->get_error_message());
            return $status_response;
        }
        
        // Get status
        $status_data = json_decode(wp_remote_retrieve_body($status_response), true);
        if (!isset($status_data['status'])) {
            BC_Assistant_Helper::log('Invalid status response: ' . wp_remote_retrieve_body($status_response));
            return new WP_Error('invalid_response', 'Invalid status response', wp_remote_retrieve_body($status_response));
        }
        
        $status = $status_data['status'];
        BC_Assistant_Helper::log('Run status: ' . $status);
        
        // Check if run is complete
        if ($status === 'completed') {
            break;
        } elseif (in_array($status, array('failed', 'cancelled', 'expired'))) {
            BC_Assistant_Helper::log('Run failed with status: ' . $status . ' - ' . wp_remote_retrieve_body($status_response));
            return new WP_Error('run_failed', 'Run failed with status: ' . $status, wp_remote_retrieve_body($status_response));
        }
        
        $retry_count++;
    }
    
    // If max retries exceeded, return error
    if ($retry_count >= $max_retries) {
        BC_Assistant_Helper::log('Run timed out');
        return new WP_Error('run_timeout', 'Run timed out after ' . $max_retries . ' retries');
    }
    
    // Get messages
    $messages_response = wp_remote_get(
        'https://api.openai.com/v1/threads/' . $thread_id . '/messages',
        array(
            'headers' => $headers,
            'timeout' => 30
        )
    );
    
    // Handle errors
    if (is_wp_error($messages_response)) {
        BC_Assistant_Helper::log('Error getting messages: ' . $messages_response->get_error_message());
        return $messages_response;
    }
    
    // Get messages
    $messages_data = json_decode(wp_remote_retrieve_body($messages_response), true);
    if (!isset($messages_data['data']) || !is_array($messages_data['data']) || empty($messages_data['data'])) {
        BC_Assistant_Helper::log('Invalid messages response: ' . wp_remote_retrieve_body($messages_response));
        return new WP_Error('invalid_response', 'Invalid messages response', wp_remote_retrieve_body($messages_response));
    }
    
    // Find latest assistant message (first in the array, as they're sorted by creation time in descending order)
    $assistant_message = null;
    foreach ($messages_data['data'] as $msg) {
        if ($msg['role'] === 'assistant') {
            $assistant_message = $msg;
            break;
        }
    }
    
    if (!$assistant_message) {
        BC_Assistant_Helper::log('No assistant message found');
        return new WP_Error('no_response', 'No assistant message found');
    }
    
    // Extract text content from the message - v2 API may have different content structure
    $content = '';
    if (isset($assistant_message['content']) && is_array($assistant_message['content'])) {
        foreach ($assistant_message['content'] as $content_part) {
            if (isset($content_part['type']) && $content_part['type'] === 'text') {
                $content .= $content_part['text']['value'];
            }
        }
    }
    
    // Return response
    return array(
        'message' => $content,
        'thread_id' => $thread_id
    );
}