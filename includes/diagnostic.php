<?php
/**
 * BC Assistant - Diagnostic Tool
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register diagnostic tool page
 */
function bc_assistant_register_diagnostic_page() {
    add_submenu_page(
        null, // No parent page
        'BC Assistant Diagnostic Tool',
        'BC Assistant Diagnostic',
        'manage_options',
        'bc-assistant-diagnostic',
        'bc_assistant_diagnostic_page'
    );
}
add_action('admin_menu', 'bc_assistant_register_diagnostic_page');

/**
 * Diagnostic tool page content
 */
function bc_assistant_diagnostic_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Get environment info
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');
    $plugin_version = BC_ASSISTANT_VERSION;
    
    // Get plugin config
    $config = BC_Assistant_Config::get_all();
    
    // Get database info
    global $wpdb;
    $tables_exist = BC_Assistant_Helper::check_tables_exist();
    
    // Check .env file
    $env_file = ABSPATH . '.env';
    $env_exists = file_exists($env_file);
    $env_readable = is_readable($env_file);
    $env_writable = is_writable($env_file);
    
    // Get environment variables
    $openai_model = getenv('OPENAI_MODEL');
    $openai_assistant_id = getenv('OPENAI_ASSISTANT_ID');
    
    // Get active plugins
    $active_plugins = get_option('active_plugins');
    ?>
    <div class="wrap">
        <h1>BC Assistant - Narzędzie diagnostyczne</h1>
        
        <div class="notice notice-info">
            <p>To narzędzie pomaga zdiagnozować problemy z wtyczką BC Assistant. Informacje tutaj są przeznaczone do celów debugowania.</p>
        </div>
        
        <h2>Informacje o środowisku</h2>
        <table class="widefat" style="margin-bottom: 20px;">
            <tbody>
                <tr>
                    <th>PHP Version</th>
                    <td><?php echo esc_html($php_version); ?></td>
                </tr>
                <tr>
                    <th>WordPress Version</th>
                    <td><?php echo esc_html($wp_version); ?></td>
                </tr>
                <tr>
                    <th>Plugin Version</th>
                    <td><?php echo esc_html($plugin_version); ?></td>
                </tr>
                <tr>
                    <th>Database Tables Exist</th>
                    <td><?php echo $tables_exist ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>'; ?></td>
                </tr>
            </tbody>
        </table>
        
        <h2>Plik .env</h2>
        <table class="widefat" style="margin-bottom: 20px;">
            <tbody>
                <tr>
                    <th>Path</th>
                    <td><?php echo esc_html($env_file); ?></td>
                </tr>
                <tr>
                    <th>Exists</th>
                    <td><?php echo $env_exists ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>'; ?></td>
                </tr>
                <?php if ($env_exists) : ?>
                <tr>
                    <th>Readable</th>
                    <td><?php echo $env_readable ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>'; ?></td>
                </tr>
                <tr>
                    <th>Writable</th>
                    <td><?php echo $env_writable ? '<span style="color: green;">Yes</span>' : '<span style="color: red;">No</span>'; ?></td>
                </tr>
                <?php if ($env_readable) : ?>
                <tr>
                    <th>First 10 bytes</th>
                    <td><?php echo esc_html(substr(file_get_contents($env_file), 0, 10)); ?></td>
                </tr>
                <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2>Zmienne środowiskowe</h2>
        <table class="widefat" style="margin-bottom: 20px;">
            <tbody>
                <tr>
                    <th>OPENAI_MODEL</th>
                    <td><?php echo $openai_model ? esc_html($openai_model) : '<span style="color: orange;">Not set</span>'; ?></td>
                </tr>
                <tr>
                    <th>OPENAI_ASSISTANT_ID</th>
                    <td><?php echo $openai_assistant_id ? esc_html($openai_assistant_id) : '<span style="color: orange;">Not set</span>'; ?></td>
                </tr>
            </tbody>
        </table>
        
        <h2>Konfiguracja wtyczki</h2>
        <table class="widefat" style="margin-bottom: 20px;">
            <tbody>
                <?php foreach ($config as $key => $value) : ?>
                <tr>
                    <th><?php echo esc_html($key); ?></th>
                    <td>
                        <?php 
                        if ($key === 'api_key' && !empty($value)) {
                            echo '[UKRYTY]';
                        } elseif ($key === 'system_message_default' || $key === 'system_message_procedure' || 
                                 $key === 'system_message_contraindications' || $key === 'system_message_prices') {
                            echo '<textarea readonly rows="3" cols="50">' . esc_textarea($value) . '</textarea>';
                        } else {
                            echo esc_html($value);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Test wywołania API</h2>
        <form method="post" action="">
            <?php wp_nonce_field('bc_assistant_test_api', 'bc_assistant_test_api_nonce'); ?>
            <input type="hidden" name="bc_assistant_test_api" value="1">
            <p>
                <button type="submit" class="button button-primary">Przetestuj połączenie API</button>
                <span class="description">To wykona testowe wywołanie API, aby sprawdzić połączenie.</span>
            </p>
        </form>
        
        <?php 
        // Handle API test
        if (isset($_POST['bc_assistant_test_api']) && check_admin_referer('bc_assistant_test_api', 'bc_assistant_test_api_nonce')) {
            $test_result = bc_assistant_api_request('Test message from diagnostic tool');
            
            if (is_wp_error($test_result)) {
                echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . esc_html($test_result->get_error_message()) . '</p>';
                if ($test_result->get_error_data()) {
                    echo '<pre>' . esc_html(print_r($test_result->get_error_data(), true)) . '</pre>';
                }
                echo '</div>';
            } else {
                echo '<div class="notice notice-success"><p><strong>Success!</strong> API responded correctly.</p>';
                echo '<p><strong>Response:</strong> ' . esc_html($test_result['message']) . '</p></div>';
            }
        }
        ?>
        
        <h2>Active Plugins</h2>
        <table class="widefat">
            <tbody>
                <?php foreach ($active_plugins as $plugin) : ?>
                <tr>
                    <td><?php echo esc_html($plugin); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>JavaScript Diagnostic</h2>
        <p>Open browser console (F12) and look for any errors related to "bc-assistant" or "BC Assistant".</p>
        <button id="bc-assistant-diagnostic-test" class="button">Test JavaScript Initialization</button>
        
        <script>
            jQuery(document).ready(function($) {
                $('#bc-assistant-diagnostic-test').on('click', function() {
                    console.log('BC Assistant Diagnostic: Testing JavaScript initialization');
                    
                    try {
                        // Check if global config exists
                        console.log('BC Assistant Diagnostic: Checking global configuration');
                        if (typeof bcAssistantData === 'undefined') {
                            console.error('BC Assistant Diagnostic: bcAssistantData is not defined');
                        } else {
                            console.log('BC Assistant Diagnostic: bcAssistantData is defined', bcAssistantData);
                        }
                        
                        // Check if welcome message exists
                        console.log('BC Assistant Diagnostic: Checking welcome message');
                        if (typeof window.bcAssistantWelcomeMessage === 'undefined') {
                            console.warn('BC Assistant Diagnostic: bcAssistantWelcomeMessage is not defined');
                        } else {
                            console.log('BC Assistant Diagnostic: bcAssistantWelcomeMessage is defined', window.bcAssistantWelcomeMessage);
                        }
                        
                        // Try to find bubble element
                        console.log('BC Assistant Diagnostic: Checking for bubble element');
                        var bubble = document.querySelector('.bc-assistant-bubble');
                        if (!bubble) {
                            console.error('BC Assistant Diagnostic: Bubble element not found');
                        } else {
                            console.log('BC Assistant Diagnostic: Bubble element found', bubble);
                        }
                        
                        console.log('BC Assistant Diagnostic: Test completed');
                    } catch (error) {
                        console.error('BC Assistant Diagnostic: Error during test', error);
                    }
                });
            });
        </script>
    </div>
    <?php
}