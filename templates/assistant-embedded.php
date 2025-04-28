<?php
if (!defined('ABSPATH')) {
    exit;
}

$title = esc_html($atts['title']);
$welcome_message = esc_html($atts['welcome_message']);
$theme_class = 'bc-assistant-' . esc_attr($atts['theme']);
$unique_id = 'bc-assistant-' . uniqid();
$context = esc_attr($atts['context']);
$procedure_name = esc_attr($atts['procedure_name']);
?>

<div id="<?php echo $unique_id; ?>" class="bc-assistant-embedded <?php echo $theme_class; ?>" data-context="<?php echo $context; ?>" data-procedure="<?php echo $procedure_name; ?>">
    <!-- Nagłówek asystenta -->
    <div class="bc-assistant-header">
        <div class="bc-assistant-title"><?php echo $title; ?></div>
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
