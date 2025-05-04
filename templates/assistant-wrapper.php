<?php
/**
 * BC Assistant - Unified Wrapper Template
 * This template provides a consistent structure for the chat assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get configuration
$theme_class = 'bc-assistant-' . esc_attr($theme);
$unique_id = 'bc-assistant-' . uniqid();
$position = isset($config['bubble_position']) ? esc_attr($config['bubble_position']) : 'bottom-right';

// Get icon class based on settings
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

// Get page context
$page_context = BC_Assistant_Helper::get_page_context();

// Get appropriate welcome message
$welcome_message = BC_Assistant_Helper::get_welcome_message($page_context);

// Get button text
$button_text = esc_html(BC_Assistant_Config::get('button_text') ?: 'Zapytaj asystenta');
?>

<!-- BC Assistant Wrapper -->
<div id="<?php echo $unique_id; ?>" class="bc-assistant-wrapper" data-position="<?php echo $position; ?>" 
     data-context="<?php echo esc_attr($page_context['context']); ?>" 
     data-procedure="<?php echo esc_attr($page_context['procedure_name']); ?>">
    
    <div class="bc-assistant-container <?php echo $theme_class; ?>">
        <!-- Chat Bubble -->
        <div class="bc-assistant-bubble">
            <i class="<?php echo $icon_class; ?>"></i>
        </div>
        
        <!-- Chat Window -->
        <div class="bc-assistant-window">
            <!-- Header -->
            <div class="bc-assistant-header">
                <div class="bc-assistant-title">Asystent BC</div>
                <div class="bc-assistant-controls">
                    <button class="bc-assistant-minimize" title="Zminimalizuj">
                        <i class="fas fa-minus" aria-hidden="true"></i>
                    </button>
                    <button class="bc-assistant-close" title="Zamknij">
                        <i class="fas fa-times" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            
            <!-- Messages Container -->
            <div class="bc-assistant-messages">
                <!-- Initial welcome message will be added here by JS -->
            </div>
            
            <!-- Input Container -->
            <div class="bc-assistant-input-container">
                <textarea class="bc-assistant-input" placeholder="Wpisz swoje pytanie..."></textarea>
                <button class="bc-assistant-send" title="Wyślij">
                    <i class="fas fa-paper-plane" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for JS initialization -->
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Store welcome message for JS to use
        window.bcAssistantWelcomeMessage = <?php echo json_encode($welcome_message); ?>;
    });
</script>