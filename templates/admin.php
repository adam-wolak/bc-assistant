<?php
/**
 * BC Assistant - Szablon panelu administracyjnego
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Określ aktywną zakładkę
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Zapisz ustawienia, jeśli formularz został wysłany
if (isset($_POST['bc_assistant_save_settings']) && current_user_can('manage_options')) {
    
    // Sprawdź nonce
    if (isset($_POST['bc_assistant_nonce']) && wp_verify_nonce($_POST['bc_assistant_nonce'], 'bc_assistant_settings')) {
        
        // Zapisz aktywną zakładkę
        if (isset($_POST['bc_assistant_active_tab'])) {
            $active_tab = sanitize_text_field($_POST['bc_assistant_active_tab']);
        }
        
        // Zapisz ustawienia ogólne
        if (isset($_POST['bc_assistant_model'])) {
            $model = sanitize_text_field($_POST['bc_assistant_model']);
            BC_Assistant_Config::set('model', $model);
        }
        
        if (isset($_POST['bc_assistant_api_key'])) {
            $api_key = sanitize_text_field($_POST['bc_assistant_api_key']);
            BC_Assistant_Config::set('api_key', $api_key);
        }
        
        if (isset($_POST['bc_assistant_assistant_id'])) {
            $assistant_id = sanitize_text_field($_POST['bc_assistant_assistant_id']);
            
            // Zapisz ID asystenta do pliku .env
            $env_file = ABSPATH . '.env';
            if (file_exists($env_file) && is_writable($env_file)) {
                $env_content = file_get_contents($env_file);
                $pattern = '/OPENAI_ASSISTANT_ID=(.+)/';
                $replacement = 'OPENAI_ASSISTANT_ID=' . $assistant_id;
                
                if (preg_match($pattern, $env_content)) {
                    $new_content = preg_replace($pattern, $replacement, $env_content);
                } else {
                    $new_content = rtrim($env_content) . "\nOPENAI_ASSISTANT_ID=" . $assistant_id . "\n";
                }
                
                file_put_contents($env_file, $new_content);
            }
        }
        
        // Zapisz prompty
        if (isset($_POST['bc_assistant_system_message_default'])) {
            $system_message = sanitize_textarea_field($_POST['bc_assistant_system_message_default']);
            BC_Assistant_Config::set('system_message_default', $system_message);
        }
        
        if (isset($_POST['bc_assistant_system_message_procedure'])) {
            $system_message = sanitize_textarea_field($_POST['bc_assistant_system_message_procedure']);
            BC_Assistant_Config::set('system_message_procedure', $system_message);
        }
        
        if (isset($_POST['bc_assistant_system_message_contraindications'])) {
            $system_message = sanitize_textarea_field($_POST['bc_assistant_system_message_contraindications']);
            BC_Assistant_Config::set('system_message_contraindications', $system_message);
        }
        
        if (isset($_POST['bc_assistant_system_message_prices'])) {
            $system_message = sanitize_textarea_field($_POST['bc_assistant_system_message_prices']);
            BC_Assistant_Config::set('system_message_prices', $system_message);
        }
        
        // Zapisz ustawienia wyświetlania
        if (isset($_POST['bc_assistant_welcome_message_default'])) {
            $welcome_message = sanitize_text_field($_POST['bc_assistant_welcome_message_default']);
            BC_Assistant_Config::set('welcome_message_default', $welcome_message);
        }
        
        if (isset($_POST['bc_assistant_display_mode'])) {
            $display_mode = sanitize_text_field($_POST['bc_assistant_display_mode']);
            BC_Assistant_Config::set('display_mode', $display_mode);
        }
        
        if (isset($_POST['bc_assistant_bubble_icon'])) {
            $bubble_icon = sanitize_text_field($_POST['bc_assistant_bubble_icon']);
            BC_Assistant_Config::set('bubble_icon', $bubble_icon);
        }
        
        // Wyświetl komunikat sukcesu
        add_settings_error(
            'bc_assistant_settings',
            'settings_updated',
            'Ustawienia zostały zapisane.',
            'updated'
        );
    }
}

// Pobierz aktualne ustawienia
$current_model = BC_Assistant_Config::get('model');
$api_key = BC_Assistant_Config::get('api_key');
$available_models = BC_Assistant_Config::get_available_models();
?>

<div class="wrap">
    <h1>BC Assistant - Ustawienia</h1>
    
    <?php settings_errors('bc_assistant_settings'); ?>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=bc-assistant&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">Ogólne</a>
        <a href="?page=bc-assistant&tab=prompts" class="nav-tab <?php echo $active_tab == 'prompts' ? 'nav-tab-active' : ''; ?>">Prompty</a>
        <a href="?page=bc-assistant&tab=display" class="nav-tab <?php echo $active_tab == 'display' ? 'nav-tab-active' : ''; ?>">Wyświetlanie</a>
        <a href="?page=bc-assistant&tab=debug" class="nav-tab <?php echo $active_tab == 'debug' ? 'nav-tab-active' : ''; ?>">Diagnostyka</a>
    </h2>
    
    <form method="post" action="" id="bc-assistant-settings-form">
        <?php wp_nonce_field('bc_assistant_settings', 'bc_assistant_nonce'); ?>
        
        <?php if ($active_tab == 'general') : ?>
            <!-- Zakładka Ogólne -->
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Klucz API</th>
                    <td>
                        <input type="password" name="bc_assistant_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text" />
                        <p class="description">Wprowadź klucz API dla OpenAI lub Anthropic, w zależności od wybranego modelu.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">ID Asystenta OpenAI</th>
                    <td>
                        <input type="text" name="bc_assistant_assistant_id" 
                               value="<?php echo esc_attr(getenv('OPENAI_ASSISTANT_ID')); ?>" 
                               class="regular-text" />
                        <p class="description">Wprowadź ID asystenta z platformy OpenAI (tylko dla API Assistants).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Model AI</th>
                    <td>
                        <select name="bc_assistant_model" id="bc-assistant-model-select">
                            <?php foreach ($available_models as $model_key => $model_name) : ?>
                                <option value="<?php echo esc_attr($model_key); ?>" <?php selected($current_model, $model_key); ?>>
                                    <?php echo esc_html($model_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Wybierz model AI, który będzie używany do generowania odpowiedzi.</p>
                        <p class="description">Uwaga: Modele GPT-3.5 i GPT-4 zostały wycofane 30 kwietnia 2025 r. Model gpt-4o jest najnowszym modelem OpenAI.</p>
                        
                        <!-- Wyświetl aktualnie zapisany model dla debugowania -->
                        <div style="margin-top: 10px; padding: 8px; border-left: 4px solid #0085ba; background: #f0f0f1;">
                            <strong>Aktualnie zapisany model:</strong> <?php echo esc_html($current_model); ?> 
                            (<?php echo esc_html(BC_Assistant_Config::get_model_display_name($current_model)); ?>)
                        </div>
                    </td>
                </tr>
            </table>
        <?php elseif ($active_tab == 'prompts') : ?>
            <!-- Zakładka Prompty -->
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Prompt ogólny</th>
                    <td>
                        <textarea name="bc_assistant_system_message_default" rows="10" cols="50" 
                                  class="large-text"><?php echo esc_textarea(BC_Assistant_Config::get('system_message_default')); ?></textarea>
                        <p class="description">Wiadomość systemowa określa zachowanie asystenta. Nie jest widoczna dla użytkownika.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Prompt dla zabiegów</th>
                    <td>
                        <textarea name="bc_assistant_system_message_procedure" rows="10" cols="50" 
                                  class="large-text"><?php echo esc_textarea(BC_Assistant_Config::get('system_message_procedure')); ?></textarea>
                        <p class="description">Prompt używany, gdy użytkownik pyta o konkretny zabieg.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Prompt dla przeciwwskazań</th>
                    <td>
                        <textarea name="bc_assistant_system_message_contraindications" rows="10" cols="50" 
                                  class="large-text"><?php echo esc_textarea(BC_Assistant_Config::get('system_message_contraindications')); ?></textarea>
                        <p class="description">Prompt używany, gdy użytkownik pyta o przeciwwskazania.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Prompt dla cennika</th>
                    <td>
                        <textarea name="bc_assistant_system_message_prices" rows="10" cols="50" 
                                  class="large-text"><?php echo esc_textarea(BC_Assistant_Config::get('system_message_prices')); ?></textarea>
                        <p class="description">Prompt używany, gdy użytkownik pyta o ceny.</p>
                    </td>
                </tr>
            </table>
        <?php elseif ($active_tab == 'display') : ?>
            <!-- Zakładka Wyświetlanie -->
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Wiadomość powitalna</th>
                    <td>
                        <input type="text" name="bc_assistant_welcome_message_default" 
                               value="<?php echo esc_attr(BC_Assistant_Config::get('welcome_message_default')); ?>" 
                               class="large-text" />
                        <p class="description">Wiadomość powitalna wyświetlana użytkownikowi po otwarciu czatu.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Tryb wyświetlania</th>
                    <td>
                        <select name="bc_assistant_display_mode">
                            <option value="bubble" <?php selected(BC_Assistant_Config::get('display_mode'), 'bubble'); ?>>Bąbelek (floating bubble)</option>
                            <option value="embedded" <?php selected(BC_Assistant_Config::get('display_mode'), 'embedded'); ?>>Osadzony (embedded)</option>
                            <option value="voice" <?php selected(BC_Assistant_Config::get('display_mode'), 'voice'); ?>>Tylko głosowy (mobile)</option>
                        </select>
                        <p class="description">Wybierz sposób wyświetlania asystenta na stronie.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Ikona bąbelka</th>
                    <td>
                        <select name="bc_assistant_bubble_icon">
                            <option value="chat" <?php selected(BC_Assistant_Config::get('bubble_icon'), 'chat'); ?>>Dymek czatu</option>
                            <option value="question" <?php selected(BC_Assistant_Config::get('bubble_icon'), 'question'); ?>>Znak zapytania</option>
                            <option value="info" <?php selected(BC_Assistant_Config::get('bubble_icon'), 'info'); ?>>Informacja</option>
                            <option value="robot" <?php selected(BC_Assistant_Config::get('bubble_icon'), 'robot'); ?>>Robot</option>
                            <option value="user" <?php selected(BC_Assistant_Config::get('bubble_icon'), 'user'); ?>>Lekarz</option>
                        </select>
                        <p class="description">Wybierz ikonę dla bąbelka czatu.</p>
                    </td>
                </tr>
            </table>
        <?php elseif ($active_tab == 'debug') : ?>
            <!-- Zakładka Diagnostyka -->
            <p>
                <a href="<?php echo admin_url('?bc_debug=model'); ?>" class="button button-secondary">Otwórz narzędzie diagnostyczne</a>
                <span class="description" style="margin-left: 10px;">Użyj narzędzia diagnostycznego, aby zbadać problemy z zapisywaniem modelu.</span>
            </p>
        <?php endif; ?>
        
        <input type="hidden" name="bc_assistant_save_settings" value="1" />
        <input type="hidden" name="bc_assistant_active_tab" value="<?php echo esc_attr($active_tab); ?>" />
        
        <?php submit_button('Zapisz ustawienia', 'primary', 'submit', true, ['id' => 'bc-assistant-save-button']); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Logowanie dla debugowania
    var currentModel = $('#bc-assistant-model-select').val();
    console.log('BC Assistant: Aktualnie wybrany model:', currentModel);
    
    // Logowanie zmiany modelu
    $('#bc-assistant-model-select').on('change', function() {
        console.log('BC Assistant: Zmieniono model na:', $(this).val());
    });
    
    // Logowanie wysyłania formularza
    $('#bc-assistant-settings-form').on('submit', function() {
        console.log('BC Assistant: Zapisywanie ustawień, model =', $('#bc-assistant-model-select').val());
        
        // Zapisz w localStorage dla debugowania
        if (window.localStorage) {
            localStorage.setItem('bc_assistant_last_model', $('#bc-assistant-model-select').val());
        }
    });
});
</script>