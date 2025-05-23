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
	
	// Ensure BC Assistant scripts are loaded on the diagnostic page
if (!wp_script_is('bc-assistant-script', 'enqueued')) {
    // Manually load the necessary scripts
    $config = BC_Assistant_Config::get_all();
    $use_shadow_dom = (bool)BC_Assistant_Config::get('use_shadow_dom');
    
    wp_enqueue_script(
        'bc-assistant-script',
        BC_ASSISTANT_URL . 'assets/js/bc-assistant.js',
        array('jquery'),
        BC_ASSISTANT_VERSION,
        true
    );
    
    if ($use_shadow_dom) {
        wp_enqueue_script(
            'bc-assistant-shadow-dom',
            BC_ASSISTANT_URL . 'assets/js/bc-assistant-shadow.js',
            array('bc-assistant-script'),
            BC_ASSISTANT_VERSION,
            true
        );
    } else {
        wp_enqueue_script(
            'bc-assistant-traditional-dom',
            BC_ASSISTANT_URL . 'assets/js/bc-assistant-traditional.js',
            array('bc-assistant-script'),
            BC_ASSISTANT_VERSION,
            true
        );
    }
    
    // Pass data to script
    wp_localize_script('bc-assistant-script', 'bcAssistantData', array(
        'model' => $config['model'],
        'position' => isset($config['bubble_position']) ? $config['bubble_position'] : 'bottom-right',
        'title' => 'Asystent Bielsko Clinic',
        'welcomeMessage' => $config['welcome_message_default'],
        'apiEndpoint' => admin_url('admin-ajax.php'),
        'action' => 'bc_assistant_send_message',
        'nonce' => wp_create_nonce('bc_assistant_nonce'),
        'debug' => defined('WP_DEBUG') && WP_DEBUG,
        'displayMode' => $config['display_mode'],
        'theme' => $config['theme'],
        'assistant_id' => getenv('OPENAI_ASSISTANT_ID'),
        'useShadowDOM' => $use_shadow_dom,
        'bubble_icon' => isset($config['bubble_icon']) ? $config['bubble_icon'] : 'chat'
    ));
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('BC Assistant: Scripts manually loaded on diagnostic page');
    }
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
        console.clear(); // Clear previous console output
        console.log('BC Assistant Diagnostic: Starting test...');
        
        // Add visual feedback that the button was clicked
        $(this).text('Running test...').addClass('button-primary');
        
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
            
            // Also check for BC Assistant instance
            console.log('BC Assistant Diagnostic: Checking for BC Assistant instance');
            if (typeof window.bcAssistant === 'undefined') {
                console.error('BC Assistant Diagnostic: bcAssistant instance not found');
            } else {
                console.log('BC Assistant Diagnostic: bcAssistant instance found', window.bcAssistant);
            }
            
            console.log('BC Assistant Diagnostic: Test completed');
            
            // Visual feedback of completion
            $(this).after('<div style="color:green;margin-top:10px;">✓ Test completed. Check browser console (F12) for results.</div>');
        } catch (error) {
            console.error('BC Assistant Diagnostic: Error during test', error);
            // Visual feedback of error
            $(this).after('<div style="color:red;margin-top:10px;">✗ Error occurred: ' + error.message + '</div>');
        } finally {
            // Reset button state
            setTimeout(function() {
                $('#bc-assistant-diagnostic-test').text('Test JavaScript Initialization').removeClass('button-primary');
            }, 2000);
        }
    });
});
        </script>
		<h2>Shadow DOM Status</h2>
<table class="widefat" style="margin-bottom: 20px;">
    <tbody>
        <tr>
            <th>Shadow DOM setting</th>
            <td>
                <?php 
                $use_shadow_dom = BC_Assistant_Config::get('use_shadow_dom');
                $raw_value = get_option('bc_assistant_use_shadow_dom');
                echo ($use_shadow_dom ? '<span style="color: green;">Enabled</span>' : '<span style="color: orange;">Disabled</span>');
                echo ' (Processed value: <code>' . var_export($use_shadow_dom, true) . '</code>, Type: <code>' . gettype($use_shadow_dom) . '</code>)';
                ?>
            </td>
        </tr>
        <tr>
            <th>Raw DB value</th>
            <td><code><?php echo var_export($raw_value, true); ?></code> (Type: <code><?php echo gettype($raw_value); ?></code>)</td>
        </tr>
        <tr>
            <th>JavaScript value</th>
            <td id="js-shadow-dom-value">Checking...</td>
        </tr>
        <tr>
            <th>Shadow DOM supported</th>
            <td id="shadow-dom-supported">Checking...</td>
        </tr>
        <tr>
            <th>Current DOM Mode</th>
            <td id="current-dom-mode">Checking...</td>
        </tr>
    </tbody>
</table>

<script>
jQuery(document).ready(function($) {
    // Check Shadow DOM setting in JavaScript
    var jsShadowDomValue = typeof bcAssistantData !== 'undefined' ? bcAssistantData.useShadowDOM : 'undefined';
    $('#js-shadow-dom-value').html('<code>' + jsShadowDomValue + '</code> (Type: <code>' + typeof jsShadowDomValue + '</code>)');
    
    // Check if browser supports Shadow DOM
    var shadowDomSupported = !!HTMLElement.prototype.attachShadow;
    $('#shadow-dom-supported').html(shadowDomSupported ? 
        '<span style="color: green;">Yes</span>' : 
        '<span style="color: red;">No</span> (This browser does not support Shadow DOM)');
    
    // Check current mode
    var currentMode = 'Unknown';
    if (typeof bcAssistantData !== 'undefined') {
        if (bcAssistantData.useShadowDOM) {
            // Check if Shadow DOM is actually being used
            if ($('bc-assistant-widget').length > 0) {
                currentMode = '<span style="color: green;">Shadow DOM (widget found)</span>';
            } else {
                currentMode = '<span style="color: orange;">Shadow DOM configured but widget not found</span>';
            }
        } else {
            // Check if Traditional DOM is actually being used
            if ($('.bc-assistant-wrapper').length > 0) {
                currentMode = '<span style="color: blue;">Traditional DOM (wrapper found)</span>';
            } else {
                currentMode = '<span style="color: orange;">Traditional DOM configured but wrapper not found</span>';
            }
        }
    } else {
        currentMode = '<span style="color: red;">Configuration not available (bcAssistantData undefined)</span>';
    }
    $('#current-dom-mode').html(currentMode);
});
</script>

<h2>Test Shadow DOM Implementation</h2>
<button id="test-shadow-dom" class="button">Test Shadow DOM Creation</button>
<div id="shadow-dom-test-results" style="margin-top: 10px;"></div>

<script>
jQuery(document).ready(function($) {
    $('#test-shadow-dom').on('click', function() {
        var $results = $('#shadow-dom-test-results');
        $results.html('<div>Testing Shadow DOM implementation...</div>');
        
        try {
            // Create test element
            var testElement = document.createElement('div');
            testElement.id = 'shadow-dom-test-element';
            testElement.style.border = '1px solid #ccc';
            testElement.style.padding = '10px';
            testElement.style.marginTop = '10px';
            testElement.innerHTML = '<h3>Shadow DOM Test Container</h3>';
            
            // Append to results
            $results.append(testElement);
            
            // Try to create shadow root
            var shadowResult = '';
            try {
                var shadowRoot = testElement.attachShadow({mode: 'open'});
                shadowRoot.innerHTML = `
                    <style>
                        p { color: blue; font-weight: bold; }
                    </style>
                    <p>This text is inside Shadow DOM</p>
                `;
                shadowResult = '<div style="color: green;">✓ Shadow DOM successfully created and content added</div>';
            } catch (shadowError) {
                shadowResult = '<div style="color: red;">✗ Error creating Shadow DOM: ' + shadowError.message + '</div>';
            }
            
            $results.append(shadowResult);
            
            // Add verification instructions
            $results.append(`
                <div style="margin-top: 10px;">
                    <strong>Verification:</strong>
                    <ol>
                        <li>You should see a box with "Shadow DOM Test Container" heading</li>
                        <li>Below that should be blue bold text saying "This text is inside Shadow DOM"</li>
                        <li>If you see this text, Shadow DOM is working correctly on this browser</li>
                        <li>If you don't see the blue text or see an error, Shadow DOM is not supported or has issues</li>
                    </ol>
                </div>
            `);
            
        } catch (error) {
            $results.append('<div style="color: red;">✗ Error during test: ' + error.message + '</div>');
        }
    });
});
</script>

 </div>
 <?php
}