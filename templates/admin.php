<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap bc-assistant-admin">
    <h1>BC Assistant - Ustawienia</h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active">Ustawienia główne</a>
        <a href="#prompts" class="nav-tab">Treści promptów</a>
        <a href="#service" class="nav-tab">Cały serwis</a>
        <a href="#contraindications" class="nav-tab">Przeciwskazania</a>
        <a href="#prices" class="nav-tab">Cennik</a>
        <a href="#threads" class="nav-tab">Wątki OpenAI</a>
        <a href="#settings" class="nav-tab">Zaawansowane</a>
    </h2>
    
    <form method="post" action="options.php">
        <?php settings_fields('bc_assistant_options'); ?>
        
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
                            $selected_model = get_option('bc_assistant_model', 'gpt-4o');
                            $models = array(
                                'gpt-4o' => 'GPT-4o (flagowy model)',
                                'gpt-4o-mini' => 'GPT-4o mini (szybszy i tańszy)',
                                'o1' => 'o1 (model rozumujący)',
                                'o1-mini' => 'o1-mini (szybszy i tańszy)',
                                'o1-pro' => 'o1-pro (ulepszona jakość)',
                                'o3' => 'o3 (nowy model rozumujący)',
                                'o3-mini' => 'o3-mini (szybszy i tańszy)',
                                'o3-mini-high' => 'o3-mini-high (ulepszona jakość)',
                                'o4-mini' => 'o4-mini (najnowsza generacja, kompaktowy)',
                                'gpt-4.5' => 'GPT-4.5 (luty 2025, mniej halucynacji)',
                                'gpt-4.1' => 'GPT-4.1 (dla deweloperów, długi kontekst)',
                                'gpt-4-1106-preview' => 'GPT-4 Turbo (11/06 - wersja legacy)'
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
                        <p class="description">Wybierz model OpenAI. Domyślnie zalecamy GPT-4o, który oferuje najlepszy stosunek jakości do ceny.</p>
                        <p class="description"><strong>Uwaga:</strong> Modele GPT-3.5 i GPT-4 zostały wycofane 30 kwietnia 2025 r. Model gpt-4-1106-preview pozostawiono dla kompatybilności wstecznej.</p>
                    </td>
                </tr>
                <!-- Pozostałe ustawienia główne -->
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
        
<!-- Nowa zakładka: Wątki OpenAI -->
<div id="threads" class="tab-content" style="display: none;">
    <h3>API Wątków OpenAI (Assistants API)</h3>
    
    <div class="notice notice-info">
        <p><strong>Informacja:</strong> BC Assistant używa API wątków OpenAI, które umożliwia bardziej zaawansowane funkcje, takie jak pamięć kontekstu czy dostęp do narzędzi.</p>
        <p>Aby korzystać z asystenta, musisz utworzyć go w panelu OpenAI i wprowadzić jego ID poniżej.</p>
    </div>
    
    <table class="form-table">
        <tr>
            <th scope="row">ID asystenta OpenAI</th>
            <td>
                <input type="text" name="bc_assistant_assistant_id" value="<?php echo esc_attr(get_option('bc_assistant_assistant_id', '')); ?>" class="regular-text" placeholder="asst_...">
                <p class="description">Wprowadź ID asystenta utworzonego w panelu OpenAI (format: asst_...).</p>
                <p><a href="https://platform.openai.com/assistants" target="_blank" class="button">Otwórz panel asystentów OpenAI</a></p>
            </td>
        </tr>
        <tr>
            <th scope="row">Instrukcje</th>
            <td>
                <ol>
                    <li>Utwórz nowego <a href="https://platform.openai.com/assistants" target="_blank">asystenta w panelu OpenAI</a></li>
                    <li>Skonfiguruj go z modelem <strong>gpt-4o</strong> lub innym wybranym</li>
                    <li>Dodaj odpowiednie instrukcje i uprawnienia</li>
                    <li>Skopiuj ID asystenta (format: asst_...) i wklej powyżej</li>
                </ol>
            </td>
        </tr>
    </table>
    
    <div class="notice notice-warning">
        <p><strong>Uwaga:</strong> Pamiętaj o ustawieniu modelu asystenta na platformie OpenAI, aby był zgodny z modelem wybranym w ustawieniach pluginu. W przeciwnym wypadku mogą wystąpić rozbieżności w działaniu.</p>
        <p><strong>Ważne:</strong> Nigdy nie udostępniaj identyfikatora asystenta w publicznym kodzie. Traktuj go podobnie jak klucz API - może być wykorzystany przez osoby niepowołane.</p>
    </div>
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
                        <p class="description">Zapisuje historię rozmów użytkowników (przy użyciu API wątków historia jest automatycznie zarządzana przez OpenAI).</p>
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