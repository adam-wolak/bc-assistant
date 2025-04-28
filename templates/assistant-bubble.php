<?php
if (!defined('ABSPATH')) {
    exit;
}

$theme_class = 'bc-assistant-' . esc_attr($theme);
$unique_id = 'bc-assistant-bubble-' . uniqid();
$context = esc_attr($page_context['context']);
$procedure_name = esc_attr($page_context['procedure_name']);

// Wybierz odpowiednią ikonę
$icon_class = '';
switch (BC_Assistant_Config::get('bubble_icon')) {
    case 'question':
        $icon_class = 'fas fa-question-circle';
        break;
    case 'info':
        $icon_class = 'fas fa-info-circle';
        break;
    case 'robot':
        $icon_class = 'fas fa-robot';
        break;
    case 'user':
        $icon_class = 'fas fa-user-md';
        break;
    case 'chat':
    default:
        $icon_class = 'fas fa-comments';
}

$button_text = esc_html(BC_Assistant_Config::get('button_text'));
<script type="text/javascript">
(function() {
    // Natychmiastowa poprawka kolorów
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            // Popraw kolory wiadomości
            $('.bc-assistant-ai .bc-assistant-bubble').css({
                'background-color': '#F5F0E7',
                'color': '#4A3F35'
            });
            
            // Popraw kolory przycisków i nagłówka
            $('.bc-assistant-header, .bc-assistant-send, .bc-assistant-bubble-button').css({
                'background-color': '#A67C52'
            });
            
            // Popraw pole tekstowe
            $('.bc-assistant-text').css({
                'background-color': '#F7F4EF',
                'border-color': '#d6c9b4'
            });
        });
    }
})();
</script>

?>

<!-- BC Assistant Bubble -->
<div id="<?php echo $unique_id; ?>" class="bc-assistant-bubble-container <?php echo $theme_class; ?>" data-context="<?php echo $context; ?>" data-procedure="<?php echo $procedure_name; ?>">
    <!-- Przycisk bąbelka -->
    <div class="bc-assistant-bubble-button">
        <div class="bc-assistant-bubble-icon">
            <i class="<?php echo $icon_class; ?>"></i>
        </div>
        <div class="bc-assistant-bubble-text"><?php echo $button_text; ?></div>
    </div>
    
    <!-- Okno czatu -->
    <div class="bc-assistant-chat-window">
        <!-- Nagłówek czatu -->
        <div class="bc-assistant-header">
            <div class="bc-assistant-title"><?php echo $title; ?></div>
            <div class="bc-assistant-controls">
                <button class="bc-assistant-minimize" title="Zminimalizuj">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="bc-assistant-close" title="Zamknij">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Okno rozmowy -->
        <div class="bc-assistant-conversation">
            <!-- Wiadomość powitalna -->
            <div class="bc-assistant-message bc-assistant-ai">
                <div class="bc-assistant-avatar">
                    <img src="<?php echo esc_url($this->plugin_url . 'assets/img/assistant-avatar.png'); ?>" alt="Asystent">
                </div>
                <div class="bc-assistant-bubble"><?php echo nl2br($welcome_message); ?></div>
            </div>
            
            <!-- Tutaj będą dodawane wiadomości dynamicznie przez JS -->
        </div>
        
        <!-- Pole do wpisywania wiadomości -->
        <div class="bc-assistant-input">
            <textarea class="bc-assistant-text" placeholder="Wpisz swoją wiadomość..."></textarea>
            <button class="bc-assistant-send" title="Wyślij">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        
        <!-- Status połączenia -->
        <div class="bc-assistant-status">
            <div class="bc-assistant-typing">Asystent pisze<span class="typing-dots">...</span></div>
            <div class="bc-assistant-error"></div>
        </div>
    </div>
</div>
