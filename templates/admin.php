<?php
/**
 * BC Assistant - Szablon panelu administracyjnego
 * 
 * Ten plik zawiera kod HTML dla panelu administracyjnego BC Assistant.
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Zapisz ustawienia, jeśli formularz został wysłany
if (isset($_POST['bc_assistant_save_settings']) && current_user_can('manage_options')) {
    
    // Sprawdź nonce
    if (isset($_POST['bc_assistant_nonce']) && wp_verify_nonce($_POST['bc_assistant_nonce'], 'bc_assistant_settings')) {
        
        // Zapisz model
        if (isset($_POST['bc_assistant_model'])) {
            $model = sanitize_text_field($_POST['bc_assistant_model']);
            BC_Assistant_Config::set('model', $model);
            
            // Pokaż komunikat sukcesu
            add_settings_error(
                'bc_assistant_settings',
                'settings_updated',
                'Ustawienia zostały zapisane.',
                'updated'
            );
        }
        
        // Zapisz klucz API
        if (isset($_POST['bc_assistant_api_key'])) {
            $api_key = sanitize_text_field($_POST['bc_assistant_api_key']);
            BC_Assistant_Config::set('api_key', $api_key);
        }
        
        // Zapisz inne ustawienia
        if (isset($_POST['bc_assistant_system_message_default'])) {
            $system_message = sanitize_textarea_field($_POST['bc_assistant_system_message_default']);
            BC_Assistant_Config::set('system_message_default', $system_message);
        }
        
        if (isset($_POST['bc_assistant_welcome_message_default'])) {
            $welcome_message = sanitize_text_field($_POST['bc_assistant_welcome_message_default']);
            BC_Assistant_Config::set('welcome_message_default', $welcome_message);
        }
        
        if (isset($_POST['bc_assistant_display_mode'])) {
            $display_mode = sanitize_text_field($_POST['bc_assistant_display_mode']);
            BC_Assistant_Config::set('display_mode', $display_mode);
        }
    }
}

// Pobierz aktualne ustawienia
$current_model = BC_Assistant_Config::get('model');
$api_key = BC_Assistant_Config::get('api_key');
$system_message = BC_Assistant_Config::get('system_message_default');
$welcome_message = BC_Assistant_Config::get('welcome_message_default');
$display_mode = BC_Assistant_Config::get('display_mode');

// Pobierz dostępne modele
$available_models = BC_Assistant_Config::get_available_models();
?>

<div class="wrap">
    <h1>BC Assistant - Ustawienia</h1>
    
    <?php settings_errors('bc_assistant_settings'); ?>
    
    <div class="notice notice-info" style="padding: 10px; margin-bottom: 15px;">
        <p><strong>Informacja:</strong> BC Assistant używa API OpenAI oraz Anthropic (Claude) do generowania odpowiedzi na pytania użytkowników.</p>
        <p>Wybierz model AI, który będzie używany, oraz dostosuj inne ustawienia asystenta.</p>
    </div>
    
    <!-- Główny formularz ustawień -->
    <form method="post" action="" id="bc-assistant-settings-form">
        <?php wp_nonce_field('bc_assistant_settings', 'bc_assistant_nonce'); ?>
        
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
            <tr valign="top">
                <th scope="row">Wiadomość systemowa</th>
                <td>
                    <textarea name="bc_assistant_system_message_default" rows="5" cols="50" 
                              class="large-text"><?php echo esc_textarea($system_message); ?></textarea>
                    <p class="description">Wiadomość systemowa określa zachowanie asystenta. Nie jest widoczna dla użytkownika.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Wiadomość powitalna</th>
                <td>
                    <input type="text" name="bc_assistant_welcome_message_default" 
                           value="<?php echo esc_attr($welcome_message); ?>" 
                           class="large-text" />
                    <p class="description">Wiadomość powitalna wyświetlana użytkownikowi po otwarciu czatu.</p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Tryb wyświetlania</th>
                <td>
                    <select name="bc_assistant_display_mode">
                        <option value="bubble" <?php selected($display_mode, 'bubble'); ?>>Bąbelek (floating bubble)</option>
                        <option value="embedded" <?php selected($display_mode, 'embedded'); ?>>Osadzony (embedded)</option>
                    </select>
                    <p class="description">Wybierz sposób wyświetlania asystenta na stronie.</p>
                </td>
            </tr>
        </table>
        
        <input type="hidden" name="bc_assistant_save_settings" value="1" />
        
        <?php submit_button('Zapisz ustawienia', 'primary', 'submit', true, ['id' => 'bc-assistant-save-button']); ?>
    </form>
    
    <!-- Sekcja debugowania -->
    <div style="margin-top: 30px;">
        <h2>Narzędzia diagnostyczne</h2>
        <p>
            <a href="<?php echo admin_url('?bc_debug=model'); ?>" class="button button-secondary">Otwórz narzędzie diagnostyczne</a>
            <span class="description" style="margin-left: 10px;">Użyj narzędzia diagnostycznego, aby zbadać problemy z zapisywaniem modelu.</span>
        </p>
    </div>
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