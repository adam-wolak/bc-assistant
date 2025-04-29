<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bc-assistant-bubble-container">
    <button class="bc-assistant-bubble-button" aria-label="Asystent BC">
        <i class="fas fa-comments"></i>
    </button>
    
    <div class="bc-assistant-chat-window">
        <div class="bc-assistant-header">
            <div class="bc-assistant-title">Asystent BC</div>
            <div class="bc-assistant-controls">
                <button class="bc-assistant-minimize" title="Zminimalizuj">
                    <i class="fas fa-minus"></i>
                </button>
                <button class="bc-assistant-close" title="Zamknij">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div class="bc-assistant-messages">
            <!-- Messages will be added here dynamically -->
        </div>
        
        <div class="bc-assistant-input-container">
            <textarea class="bc-assistant-input" placeholder="Wpisz swoje pytanie..."></textarea>
            <button class="bc-assistant-send" title="Wyślij">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>