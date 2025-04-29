<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>BC Assistant - Ustawienia</h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active">Ustawienia główne</a>
        <a href="#prompts" class="nav-tab">Treści promptów</a>
        <a href="#service" class="nav-tab">Cały serwis</a>
        <a href="#contraindications" class="nav-tab">Przeciwskazania</a>
        <a href="#prices" class="nav-tab">Cennik</a>
        <a href="#settings" class="nav-tab">Zaawansowane</a>
    </h2>

    <form method="post" action="">
        <?php wp_nonce_field('bc_assistant_settings'); ?>
        
        <div class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active">Ogólne</a>
            <a href="#messages" class="nav-tab">Wiadomości</a>
            <a href="#appearance" class="nav-tab">Wygląd</a>
            <a href="#advanced" class="nav-tab">Zaawansowane</a>
        </div>
        
       <!-- Zakładka: Ustawienia główne -->
        <div id="general" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row">Klucz API OpenAI</th>
                    <td>
                        <input type="password" name="bc_assistant_api_key" value="<?php echo esc_attr(get_option('bc_assistant_api_key')); ?>" class="regular-text" />
                        <p class="description">Wprowadź swój klucz API z OpenAI. <a href="https://platform.openai.com/account/api-keys" target="_blank">Uzyskaj klucz API</a></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Model</th>
                    <td>
                        <select name="bc_assistant_model">
                            <?php
                            $selected_model = get_option('bc_assistant_model', 'gpt-3.5-turbo');
                            $models = array(
                                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                                'gpt-4' => 'GPT-4',
                                'gpt-4-turbo' => 'GPT-4 Turbo'
                            );
                            foreach ($models as $value => $label) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($value),
                                    selected($selected_model, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
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
        
        <!-- Zakładka: Treści promptów -->
        <div id="prompts" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Podstawowy prompt</th>
                    <td>
                        <textarea name="bc_assistant_system_prompt" rows="5" class="large-text"><?php echo esc_textarea(get_option('bc_assistant_system_prompt', 'Jesteś pomocnym asystentem Bielsko Clinic.')); ?></textarea>
                        <p class="description">Główny prompt systemowy definiujący zachowanie asystenta.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Prompt powitalny</th>
                    <td>
                        <textarea name="bc_assistant_welcome_message" rows="3" class="large-text"><?php echo esc_textarea(get_option('bc_assistant_welcome_message', 'Witaj! Jestem asystentem Bielsko Clinic. W czym mogę pomóc?')); ?></textarea>
                        <p class="description">Wiadomość powitalna wyświetlana przy otwarciu okna czatu.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Zakładka: Cały serwis -->
        <div id="service" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Prompt dla całego serwisu</th>
                    <td>
                        <textarea name="bc_assistant_site_prompt" rows="8" class="large-text"><?php echo esc_textarea(get_option('bc_assistant_site_prompt', 'Informacje o Bielsko Clinic i dostępnych usługach...')); ?></textarea>
                        <p class="description">Dodatkowe informacje o całym serwisie, które zostaną dodane do kontekstu.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Zakładka: Przeciwskazania -->
        <div id="contraindications" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Przeciwskazania do zabiegów</th>
                    <td>
                        <textarea name="bc_assistant_contraindications" rows="8" class="large-text"><?php echo esc_textarea(get_option('bc_assistant_contraindications', 'Lista przeciwskazań do zabiegów...')); ?></textarea>
                        <p class="description">Informacje o przeciwskazaniach do zabiegów.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Zakładka: Cennik -->
        <div id="prices" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Informacje o cenach</th>
                    <td>
                        <textarea name="bc_assistant_prices" rows="8" class="large-text"><?php echo esc_textarea(get_option('bc_assistant_prices', 'Informacje o cenach zabiegów...')); ?></textarea>
                        <p class="description">Informacje o cenach zabiegów i usług.</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Zakładka: Zaawansowane -->
        <div id="settings" class="tab-content" style="display: none;">
            <table class="form-table">
                <tr>
                    <th scope="row">Maksymalna długość odpowiedzi</th>
                    <td>
                        <input type="number" name="bc_assistant_max_tokens" value="<?php echo esc_attr(get_option('bc_assistant_max_tokens', '500')); ?>" min="100" max="4000" step="50" />
                        <p class="description">Maksymalna liczba tokenów w odpowiedzi (1 token ≈ 4 znaki).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Temperatura</th>
                    <td>
                        <input type="range" name="bc_assistant_temperature" value="<?php echo esc_attr(get_option('bc_assistant_temperature', '0.7')); ?>" min="0" max="1" step="0.1" class="bc-range" />
                        <span class="bc-range-value"><?php echo esc_attr(get_option('bc_assistant_temperature', '0.7')); ?></span>
                        <p class="description">Kontroluje kreatywność odpowiedzi (0 = bardziej precyzyjne, 1 = bardziej kreatywne).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Zapisywanie historii rozmów</th>
                    <td>
                        <label>
                            <input type="checkbox" name="bc_assistant_save_history" value="1" <?php checked(get_option('bc_assistant_save_history', '1'), '1'); ?> />
                            Włącz zapisywanie historii rozmów
                        </label>
                        <p class="description">Zapisuje historię rozmów użytkowników (zwiększa kontekst, ale zużywa więcej tokenów).</p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button('Zapisz ustawienia'); ?>
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
    
    // Aktualizacja wartości dla pola range (suwaka)
    $('.bc-range').on('input', function() {
        $(this).next('.bc-range-value').text($(this).val());
    });
    
    // Aktywuj pierwszą zakładkę domyślnie
    $('.nav-tab:first').trigger('click');
});
</script>
