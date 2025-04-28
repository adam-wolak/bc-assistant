<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>BC Assistant - Ustawienia</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('bc_assistant_settings'); ?>
        
        <div class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active">Ogólne</a>
            <a href="#messages" class="nav-tab">Wiadomości</a>
            <a href="#appearance" class="nav-tab">Wygląd</a>
            <a href="#advanced" class="nav-tab">Zaawansowane</a>
        </div>
        
        <div id="general" class="tab-content" style="display: block;">
            <h2>Ustawienia ogólne</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bc_assistant_api_key">Klucz API OpenAI</label></th>
                    <td>
                        <input type="password" name="bc_assistant_api_key" id="bc_assistant_api_key" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" autocomplete="off" />
                        <p class="description">Wprowadź swój klucz API z OpenAI.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_model">Model</label></th>
                    <td>
                        <select name="bc_assistant_model" id="bc_assistant_model">
                            <option value="gpt-4" <?php selected($settings['model'], 'gpt-4'); ?>>GPT-4</option>
                            <option value="gpt-4o" <?php selected($settings['model'], 'gpt-4o'); ?>>GPT-4o</option>
                            <option value="gpt-3.5-turbo" <?php selected($settings['model'], 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
                        </select>
                        <p class="description">Wybierz model AI do użycia w czacie.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_display_mode">Tryb wyświetlania</label></th>
                    <td>
                        <select name="bc_assistant_display_mode" id="bc_assistant_display_mode">
                            <option value="bubble" <?php selected($settings['display_mode'], 'bubble'); ?>>Bąbelek (pływający)</option>
                            <option value="embedded" <?php selected($settings['display_mode'], 'embedded'); ?>>Wbudowany (w treści strony)</option>
                        </select>
                        <p class="description">Wybierz domyślny sposób wyświetlania asystenta.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="messages" class="tab-content" style="display: none;">
            <h2>Wiadomości systemowe i powitalne</h2>
            
            <h3>Kontekst ogólny</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bc_assistant_system_message_default">Wiadomość systemowa (ogólna)</label></th>
                    <td>
                        <textarea name="bc_assistant_system_message_default" id="bc_assistant_system_message_default" rows="4" class="large-text"><?php echo esc_textarea($settings['system_message_default']); ?></textarea>
                        <p class="description">Wiadomość systemowa określająca rolę i zachowanie asystenta w kontekście ogólnym.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_welcome_message_default">Wiadomość powitalna (ogólna)</label></th>
                    <td>
                        <textarea name="bc_assistant_welcome_message_default" id="bc_assistant_welcome_message_default" rows="2" class="large-text"><?php echo esc_textarea($settings['welcome_message_default']); ?></textarea>
                        <p class="description">Wiadomość powitalna wyświetlana użytkownikowi w kontekście ogólnym.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Kontekst zabiegu</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bc_assistant_system_message_procedure">Wiadomość systemowa (zabieg)</label></th>
                    <td>
                        <textarea name="bc_assistant_system_message_procedure" id="bc_assistant_system_message_procedure" rows="4" class="large-text"><?php echo esc_textarea($settings['system_message_procedure']); ?></textarea>
                        <p class="description">Wiadomość systemowa dla stron z zabiegami. Użyj {PROCEDURE_NAME} jako placeholder dla nazwy zabiegu.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_welcome_message_procedure">Wiadomość powitalna (zabieg)</label></th>
                    <td>
                        <textarea name="bc_assistant_welcome_message_procedure" id="bc_assistant_welcome_message_procedure" rows="2" class="large-text"><?php echo esc_textarea($settings['welcome_message_procedure']); ?></textarea>
                        <p class="description">Wiadomość powitalna dla stron z zabiegami. Użyj {PROCEDURE_NAME} jako placeholder dla nazwy zabiegu.</p>
                    </td>
                </tr>
            </table>
            
            <h3>Kontekst przeciwwskazań</h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bc_assistant_system_message_contraindications">Wiadomość systemowa (przeciwwskazania)</label></th>
                    <td>
                        <textarea name="bc_assistant_system_message_contraindications" id="bc_assistant_system_message_contraindications" rows="4" class="large-text"><?php echo esc_textarea($settings['system_message_contraindications']); ?></textarea>
                        <p class="description">Wiadomość systemowa dla stron z przeciwwskazaniami.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_welcome_message_contraindications">Wiadomość powitalna (przeciwwskazania)</label></th>
                    <td>
                        <textarea name="bc_assistant_welcome_message_contraindications" id="bc_assistant_welcome_message_contraindications" rows="2" class="large-text"><?php echo esc_textarea($settings['welcome_message_contraindications']); ?></textarea>
                        <p class="description">Wiadomość powitalna dla stron z przeciwwskazaniami.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="appearance" class="tab-content" style="display: none;">
            <h2>Wygląd asystenta</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bc_assistant_button_text">Tekst przycisku</label></th>
                    <td>
                        <input type="text" name="bc_assistant_button_text" id="bc_assistant_button_text" value="<?php echo esc_attr($settings['button_text']); ?>" class="regular-text" />
                        <p class="description">Tekst wyświetlany na przycisku asystenta (w trybie bąbelka).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_bubble_icon">Ikona bąbelka</label></th>
                    <td>
                        <select name="bc_assistant_bubble_icon" id="bc_assistant_bubble_icon">
                            <option value="chat" <?php selected($settings['bubble_icon'], 'chat'); ?>>Dymek czatu</option>
                            <option value="question" <?php selected($settings['bubble_icon'], 'question'); ?>>Znak zapytania</option>
                            <option value="info" <?php selected($settings['bubble_icon'], 'info'); ?>>Info</option>
                            <option value="robot" <?php selected($settings['bubble_icon'], 'robot'); ?>>Robot</option>
                            <option value="user" <?php selected($settings['bubble_icon'], 'user'); ?>>Użytkownik</option>
                        </select>
                        <div class="icon-preview" style="margin-top: 10px; font-size: 24px;">
                            <i class="fas fa-comments" id="icon-preview"></i>
                        </div>
                        <p class="description">Wybierz ikonę dla przycisku bąbelka.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_theme">Motyw</label></th>
                    <td>
                        <select name="bc_assistant_theme" id="bc_assistant_theme">
                            <option value="light" <?php selected($settings['theme'], 'light'); ?>>Jasny</option>
                            <option value="dark" <?php selected($settings['theme'], 'dark'); ?>>Ciemny</option>
                        </select>
                        <p class="description">Wybierz motyw kolorystyczny asystenta.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div id="advanced" class="tab-content" style="display: none;">
            <h2>Zaawansowane ustawienia</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bc_assistant_context_detection">Wykrywanie kontekstu</label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span>Wykrywanie kontekstu</span></legend>
                            <label for="bc_assistant_context_detection">
                                <input name="bc_assistant_context_detection" type="checkbox" id="bc_assistant_context_detection" value="1" <?php checked($settings['context_detection'], 1); ?>>
                                Automatycznie wykrywaj kontekst strony
                            </label>
                            <p class="description">Gdy włączone, asystent będzie automatycznie dostosowywał swoje zachowanie w zależności od typu strony.</p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bc_assistant_enable_logging">Zapisywanie logów</label></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span>Zapisywanie logów</span></legend>
                            <label for="bc_assistant_enable_logging">
                                <input name="bc_assistant_enable_logging" type="checkbox" id="bc_assistant_enable_logging" value="1" <?php checked($settings['enable_logging'] ?? false, 1); ?>>
                                Zapisuj logi z rozmów
                            </label>
                            <p class="description">Gdy włączone, rozmowy z asystentem będą zapisywane do plików logów (wp-content/uploads/bc-assistant/logs).</p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="bc_assistant_save_settings" class="button button-primary" value="Zapisz ustawienia" />
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Obsługa zakładek
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Aktywuj zakładkę
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Pokaż odpowiednią zawartość
        var target = $(this).attr('href');
        $('.tab-content').hide();
        $(target).show();
    });
    
    // Obsługa podglądu ikony
    $('#bc_assistant_bubble_icon').on('change', function() {
        var icon = $(this).val();
        var iconClass = '';
        
        switch(icon) {
            case 'chat':
                iconClass = 'fas fa-comments';
                break;
            case 'question':
                iconClass = 'fas fa-question-circle';
                break;
            case 'info':
                iconClass = 'fas fa-info-circle';
                break;
            case 'robot':
                iconClass = 'fas fa-robot';
                break;
            case 'user':
                iconClass = 'fas fa-user-md';
                break;
            default:
                iconClass = 'fas fa-comments';
        }
        
        $('#icon-preview').attr('class', iconClass);
    });
    
    // Trigger icon preview on load
    $('#bc_assistant_bubble_icon').trigger('change');
});
</script>
