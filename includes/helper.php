<?php
/**
 * BC Assistant Helper Functions
 * 
 * This file contains helper functions for the BC Assistant plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BC_Assistant_Helper {
    /**
     * Get appropriate icon class based on settings
     * 
     * @return string The icon class
     */
    public static function get_icon_class() {
        $icon_type = BC_Assistant_Config::get('bubble_icon');
        
        switch ($icon_type) {
            case 'question':
                return 'fas fa-question-circle';
            case 'info':
                return 'fas fa-info-circle';
            case 'robot':
                return 'fas fa-robot';
            case 'user':
                return 'fas fa-user-md';
            case 'chat':
            default:
                return 'fas fa-comments';
        }
    }
    
    /**
     * Get page context information
     * 
     * @return array Context information
     */
    public static function get_page_context() {
        $context = [
            'context' => 'default',
            'procedure_name' => ''
        ];
        
        // Get current URL
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Determine context based on URL
        if (strpos($current_url, '/laseroterapia/') !== false) {
            $context['context'] = 'procedure';
            $context['procedure_name'] = 'Laseroterapia';
        } elseif (strpos($current_url, '/kosmetologia/') !== false) {
            $context['context'] = 'procedure';
            $context['procedure_name'] = 'Kosmetologia';
        } elseif (strpos($current_url, '/medycyna-estetyczna/') !== false) {
            $context['context'] = 'procedure';
            $context['procedure_name'] = 'Medycyna estetyczna';
        } elseif (strpos($current_url, '/przeciwwskazania/') !== false) {
            $context['context'] = 'contraindications';
        } elseif (strpos($current_url, '/cennik/') !== false) {
            $context['context'] = 'prices';
        }
        
        return $context;
    }
    
    /**
     * Get appropriate welcome message based on context
     * 
     * @param array $context The page context
     * @return string The welcome message
     */
    public static function get_welcome_message($context) {
        $config = BC_Assistant_Config::get_all();
        
        if ($context['context'] === 'procedure' && !empty($context['procedure_name'])) {
            return str_replace(
                '{PROCEDURE_NAME}', 
                $context['procedure_name'], 
                $config['welcome_message_procedure']
            );
        } elseif ($context['context'] === 'contraindications') {
            return $config['welcome_message_contraindications'];
        } elseif ($context['context'] === 'prices') {
            return $config['welcome_message_prices'] ?? $config['welcome_message_default'];
        } else {
            return $config['welcome_message_default'];
        }
    }
    
    /**
     * Get appropriate system message based on context
     * 
     * @param array $context The page context
     * @return string The system message
     */
    public static function get_system_message($context) {
        $config = BC_Assistant_Config::get_all();
        
        if ($context['context'] === 'procedure' && !empty($context['procedure_name'])) {
            return str_replace(
                '{PROCEDURE_NAME}', 
                $context['procedure_name'], 
                $config['system_message_procedure']
            );
        } elseif ($context['context'] === 'contraindications') {
            return $config['system_message_contraindications'];
        } elseif ($context['context'] === 'prices') {
            return $config['system_message_prices'] ?? $config['system_message_default'];
        } else {
            return $config['system_message_default'];
        }
    }
    
    /**
     * Detect mobile devices
     * 
     * @return boolean True if mobile device
     */
    public static function is_mobile() {
        $useragent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (
            preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $useragent) ||
            preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4))
        ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Log debug information
     * 
     * @param string $message The message to log
     * @param mixed $data Additional data to log
     */
    public static function log($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = 'BC Assistant: ' . $message;
            
            if ($data !== null) {
                if (is_array($data) || is_object($data)) {
                    $log_message .= ' - ' . print_r($data, true);
                } else {
                    $log_message .= ' - ' . $data;
                }
            }
            
            error_log($log_message);
        }
    }
    
    /**
     * Log all available information for debugging
     * Useful for troubleshooting
     */
    public static function log_debug_info() {
        if (!(defined('WP_DEBUG') && WP_DEBUG)) {
            return;
        }
        
        // Log basic environment info
        self::log('PHP Version: ' . phpversion());
        self::log('WordPress Version: ' . get_bloginfo('version'));
        self::log('Plugin Version: ' . BC_ASSISTANT_VERSION);
        
        // Log plugin configuration
        $config = BC_Assistant_Config::get_all();
        self::log('Plugin Configuration', $config);
        
        // Log environment variables
        $env_vars = array(
            'OPENAI_MODEL' => getenv('OPENAI_MODEL'),
            'OPENAI_ASSISTANT_ID' => getenv('OPENAI_ASSISTANT_ID')
        );
        self::log('Environment Variables', $env_vars);
        
        // Log browser details (if available)
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            self::log('User Agent: ' . $_SERVER['HTTP_USER_AGENT']);
        }
        
        // Log current page info
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        self::log('Current URL: ' . $current_url);
        
        // Log active plugins
        $active_plugins = get_option('active_plugins');
        self::log('Active Plugins', $active_plugins);
        
        // Check if Droplabs plugin is active
        $droplabs_active = in_array('droplabs/droplabs.php', $active_plugins) || 
                           in_array('droplabs-pro/droplabs-pro.php', $active_plugins);
        self::log('Droplabs Active: ' . ($droplabs_active ? 'Yes' : 'No'));
    }
    
    /**
     * Generate a unique conversation ID
     * 
     * @return string Unique ID
     */
    public static function generate_conversation_id() {
        return uniqid('bc_conv_');
    }
    
    /**
     * Save message to database
     * 
     * @param string $conversation_id Conversation ID
     * @param string $role Message role (user/assistant)
     * @param string $content Message content
     * @return int|false Message ID if successful, false otherwise
     */
    public static function save_message($conversation_id, $role, $content) {
        global $wpdb;
        
        // Get or create conversation record
        $conversation = self::get_or_create_conversation($conversation_id);
        if (!$conversation) {
            return false;
        }
        
        // Insert message
        $result = $wpdb->insert(
            $wpdb->prefix . 'bc_assistant_messages',
            array(
                'conversation_id' => $conversation['id'],
                'role' => $role,
                'content' => $content,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            self::log('Failed to save message', $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get or create conversation record
     * 
     * @param string $conversation_id Conversation ID
     * @return array|false Conversation data if successful, false otherwise
     */
    public static function get_or_create_conversation($conversation_id) {
        global $wpdb;
        
        // Check if conversation exists
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bc_assistant_conversations WHERE thread_id = %s",
                $conversation_id
            ),
            ARRAY_A
        );
        
        if ($conversation) {
            return $conversation;
        }
        
        // Create new conversation
        $result = $wpdb->insert(
            $wpdb->prefix . 'bc_assistant_conversations',
            array(
                'user_id' => get_current_user_id(),
                'thread_id' => $conversation_id,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s')
        );
        
        if ($result === false) {
            self::log('Failed to create conversation', $wpdb->last_error);
            return false;
        }
        
        $conversation_id = $wpdb->insert_id;
        
        return array(
            'id' => $conversation_id,
            'user_id' => get_current_user_id(),
            'thread_id' => $conversation_id,
            'created_at' => current_time('mysql')
        );
    }
    
    /**
     * Get conversation messages
     * 
     * @param string $conversation_id Conversation ID
     * @return array Messages
     */
    public static function get_conversation_messages($conversation_id) {
        global $wpdb;
        
        // Get conversation record
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bc_assistant_conversations WHERE thread_id = %s",
                $conversation_id
            ),
            ARRAY_A
        );
        
        if (!$conversation) {
            return array();
        }
        
        // Get messages
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}bc_assistant_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation['id']
            ),
            ARRAY_A
        );
        
        return $messages ?: array();
    }
    
    /**
     * Format messages for API request
     * 
     * @param array $messages Messages from database
     * @return array Formatted messages for API
     */
    public static function format_messages_for_api($messages) {
        $formatted_messages = array();
        
        foreach ($messages as $message) {
            $formatted_messages[] = array(
                'role' => $message['role'],
                'content' => $message['content']
            );
        }
        
        return $formatted_messages;
    }
    
    /**
     * Initialize database tables
     * Called during plugin activation
     */
    public static function initialize_database() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create conversations table
        $conversations_table = "CREATE TABLE {$wpdb->prefix}bc_assistant_conversations (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            thread_id varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY thread_id (thread_id)
        ) $charset_collate;";
        
        // Create messages table
        $messages_table = "CREATE TABLE {$wpdb->prefix}bc_assistant_messages (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            role varchar(50) NOT NULL,
            content text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) $charset_collate;";
        
        // Load dbDelta function
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Create tables
        dbDelta($conversations_table);
        dbDelta($messages_table);
        
        // Log result
        self::log('Database tables initialized');
    }
    
    /**
     * Check if database tables exist
     * 
     * @return boolean True if tables exist
     */
    public static function check_tables_exist() {
        global $wpdb;
        
        $conversations_table = $wpdb->prefix . 'bc_assistant_conversations';
        $messages_table = $wpdb->prefix . 'bc_assistant_messages';
        
        $conversations_exists = $wpdb->get_var("SHOW TABLES LIKE '$conversations_table'") === $conversations_table;
        $messages_exists = $wpdb->get_var("SHOW TABLES LIKE '$messages_table'") === $messages_table;
        
        return $conversations_exists && $messages_exists;
    }
}
    
    /**
     * Check if Droplabs is active
     * 
     * @return boolean True if Droplabs is detected
     */
    public static function is_droplabs_active() {
        // Check for Droplabs elements in footer
        add_action('wp_footer', function() {
            $droplabs_elements = array(
                '.droplabs-container',
                '.droplabs-widget',
                '.droplabs-bubble',
                '[id*="droplabs"]',
                '.dl-container'
            );
            
            echo '<script type="text/javascript">
                document.addEventListener("DOMContentLoaded", function() {
                    var hasDroplabs = false;
                    
                    // Check for Droplabs elements
                    var selectors = ' . json_encode($droplabs_elements) . ';
                    for (var i = 0; i < selectors.length; i++) {
                        if (document.querySelector(selectors[i])) {
                            hasDroplabs = true;
                            break;
                        }
                    }
                    
                    // Set data attribute on BC Assistant wrapper
                    var bcWrapper = document.querySelector(".bc-assistant-wrapper");
                    if (bcWrapper && hasDroplabs) {
                        bcWrapper.setAttribute("data-has-droplabs", "true");
                        
                        // Adjust position to avoid conflict
                        if (bcWrapper.getAttribute("data-position").includes("bottom")) {
                            bcWrapper.style.bottom = "150px";
                        }
                    }
                });
            </script>';
        }, 99);
        
        return false; // Default return for server-side checks
    }
    
    /**
     * Get appropriate API endpoint based on model
     * 
     * @param string $model The model to use
     * @return string The API endpoint URL
     */
    public static function get_api_endpoint($model) {
        if (strpos($model, 'claude') !== false) {
            return 'https://api.anthropic.com/v1/messages';
        } else {
            return 'https://api.openai.com/v1/chat/completions';
        }
    }
    
    /**
     * Format message for display
     * 
     * @param string $message The message to format
     * @return string Formatted message
     */
    public static function format_message($message) {
        // Handle code blocks
        $message = preg_replace('/```([^`]*?)```/s', '<pre><code>$1</code></pre>', $message);
        
        // Handle inline code
        $message = preg_replace('/`([^`]*?)`/s', '<code>$1</code>', $message);
        
        // Handle bold text
        $message = preg_replace('/\*\*([^*]*?)\*\*/s', '<strong>$1</strong>', $message);
        
        // Handle italic text
        $message = preg_replace('/\*([^*]*?)\*/s', '<em>$1</em>', $message);
        
        // Handle links
        $message = preg_replace('/\[([^\]]*?)\]\(([^)]*?)\)/s', '<a href="$2" target="_blank">$1</a>', $message);
        
        // Handle line breaks
        $message = str_replace("\n", '<br>', $message);
        
        return $message;
    }
}