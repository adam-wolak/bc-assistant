<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="bc-assistant-shortcode">
    <div class="bc-assistant-shortcode-messages">
        <!-- Messages will be added here dynamically -->
    </div>
    
    <div class="bc-assistant-shortcode-input-container">
        <textarea class="bc-assistant-shortcode-input" placeholder="<?php echo esc_attr($atts['placeholder']); ?>"></textarea>
        <button class="bc-assistant-shortcode-send">
            <?php echo esc_html($atts['button_text']); ?>
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    const $container = $('.bc-assistant-shortcode');
    const $messages = $('.bc-assistant-shortcode-messages');
    const $input = $('.bc-assistant-shortcode-input');
    const $sendButton = $('.bc-assistant-shortcode-send');
    let conversationId = localStorage.getItem('bc_assistant_shortcode_conversation_id') || '';
    let threadId = localStorage.getItem('bc_assistant_shortcode_thread_id') || ''; // Dodany thread_id
    
    // Show welcome message
    addMessage(bcAssistantData.welcomeMessage, 'assistant');
    
    // Handle send button click
    $sendButton.on('click', sendMessage);
    
    // Handle input when pressing Enter (but allow Shift+Enter for newlines)
    $input.on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Auto-expand textarea
    $input.on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    function sendMessage() {
        const message = $input.val().trim();
        
        if (!message) {
            return;
        }
        
        // Add user message to chat
        addMessage(message, 'user');
        
        // Clear input
        $input.val('');
        $input.css('height', 'auto');
        
        // Show loading indicator
        showLoading();
        
        // Get current page context
        const currentPageUrl = window.location.href;
        const currentPageTitle = document.title;
        
        // Send message to server
        $.ajax({
            url: bcAssistantData.ajaxUrl,
            method: 'POST',
            data: {
                action: 'bc_assistant_send_message',
                message: message,
                conversation_id: conversationId,
                thread_id: threadId, // Dodane thread_id
                page_url: currentPageUrl,
                page_title: currentPageTitle,
                nonce: bcAssistantData.nonce
            },
            success: function(response) {
                // Hide loading indicator
                hideLoading();
                
                if (response.success) {
                    // Add assistant response to chat
                    addMessage(response.data.message, 'assistant');
                    
                    // Store conversation ID or thread ID
                    if (response.data.conversation_id) {
                        conversationId = response.data.conversation_id;
                        localStorage.setItem('bc_assistant_shortcode_conversation_id', conversationId);
                    }
                    
                    // Obsługa thread_id z odpowiedzi
                    if (response.data.thread_id) {
                        threadId = response.data.thread_id;
                        localStorage.setItem('bc_assistant_shortcode_thread_id', threadId);
                    }
                } else {
                    // Show error message
                    addMessage('Przepraszam, wystąpił błąd. Spróbuj ponownie później.', 'assistant');
                    console.error('BC Assistant API Error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                // Hide loading indicator
                hideLoading();
                
                // Show error message
                addMessage('Przepraszam, wystąpił błąd. Spróbuj ponownie później.', 'assistant');
                console.error('BC Assistant AJAX Error:', status, error);
            }
        });
    }
    
    function addMessage(message, role) {
        // Create message element
        const $messageElement = $('<div class="bc-assistant-shortcode-message"></div>');
        $messageElement.addClass(role);
        
        // Format message content (handle markdown-like syntax)
        let formattedMessage = formatMessage(message);
        
        // Set message content
        $messageElement.html(formattedMessage);
        
        // Add timestamp
        const now = new Date();
        const timestamp = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const $metaElement = $('<div class="bc-assistant-shortcode-message-meta"></div>');
        $metaElement.text(timestamp);
        $messageElement.append($metaElement);
        
        // Add message to container
        $messages.append($messageElement);
        
        // Scroll to bottom - POPRAWIONA WERSJA
        scrollToBottom();
    }
    
    function formatMessage(message) {
        // Handle code blocks
        message = message.replace(/```([^`]*?)```/gs, '<pre><code>$1</code></pre>');
        
        // Handle inline code
        message = message.replace(/`([^`]*?)`/g, '<code>$1</code>');
        
        // Handle bold text
        message = message.replace(/\*\*([^*]*?)\*\*/g, '<strong>$1</strong>');
        
        // Handle italic text
        message = message.replace(/\*([^*]*?)\*/g, '<em>$1</em>');
        
        // Handle links
        message = message.replace(/\[([^\]]*?)\]\(([^)]*?)\)/g, '<a href="$2" target="_blank">$1</a>');
        
        // Handle line breaks
        message = message.replace(/\n/g, '<br>');
        
        return message;
    }
    
    function showLoading() {
        // Create loading element
        const $loadingElement = $(`
            <div class="bc-assistant-shortcode-loading">
                <div class="bc-assistant-shortcode-loading-dots">
                    <div class="bc-assistant-shortcode-loading-dot"></div>
                    <div class="bc-assistant-shortcode-loading-dot"></div>
                    <div class="bc-assistant-shortcode-loading-dot"></div>
                </div>
            </div>
        `);
        
        // Add loading element to messages container
        $messages.append($loadingElement);
        
        // Scroll to bottom
        scrollToBottom();
    }
    
    function hideLoading() {
        $('.bc-assistant-shortcode-loading').remove();
    }
    
    // Poprawiona funkcja przewijania
    function scrollToBottom() {
        if (!$messages.length) {
            return;
        }
        
        const scrollContainer = $messages[0];
        
        // Natychmiastowe przewinięcie
        try {
            scrollContainer.scrollTop = scrollContainer.scrollHeight;
        } catch (e) {
            console.error('Initial scroll failed:', e);
        }
        
        // Przewijanie z małym opóźnieniem (pozwala na renderowanie DOM)
        setTimeout(() => {
            try {
                scrollContainer.scrollTop = scrollContainer.scrollHeight;
            } catch (e) {
                console.error('Delayed scroll failed:', e);
            }
            
            // Dodatkowe przewinięcie po dłuższym czasie (na wypadek obrazów, itp.)
            setTimeout(() => {
                try {
                    scrollContainer.scrollTop = scrollContainer.scrollHeight;
                    
                    // Jeśli nadal nie przewija na sam dół, wymuszamy to przez scrollIntoView
                    const lastMessage = $messages.children().last();
                    if (lastMessage.length) {
                        lastMessage[0].scrollIntoView({ behavior: 'auto', block: 'end' });
                    }
                } catch (e) {
                    console.error('Final scroll failed:', e);
                }
            }, 300);
        }, 50);
    }
});
</script>

<style>
.bc-assistant-shortcode {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    line-height: 1.5;
    font-size: 14px;
    border: 1px solid #ddd;
    border-radius: 12px;
    overflow: hidden;
    margin: 20px 0;
}

.bc-assistant-shortcode-messages {
    max-height: 400px;
    overflow-y: auto;
    padding: 15px;
    background-color: #f9f9f9;
}

.bc-assistant-shortcode-message {
    margin-bottom: 15px;
    padding: 10px 12px;
    border-radius: 10px;
    max-width: 90%;
    word-wrap: break-word;
}

.bc-assistant-shortcode-message.user {
    background-color: #E8E8E8;
    margin-left: auto;
    border-bottom-right-radius: 2px;
}

.bc-assistant-shortcode-message.assistant {
    background-color: #F7F4EF;
    margin-right: auto;
    border-bottom-left-radius: 2px;
    border-left: 3px solid #A67C52;
}

.bc-assistant-shortcode-message pre {
    background-color: #1E1E1E;
    color: #FFFFFF;
    padding: 10px;
    border-radius: 5px;
    overflow-x: auto;
    font-family: monospace;
    margin: 10px 0;
}

.bc-assistant-shortcode-message code {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}

.bc-assistant-shortcode-message-meta {
    font-size: 12px;
    color: #888;
    margin-top: 5px;
    text-align: right;
}

.bc-assistant-shortcode-input-container {
    display: flex;
    align-items: center;
    padding: 10px;
    border-top: 1px solid #E8E8E8;
    background-color: white;
}

.bc-assistant-shortcode-input {
    flex: 1;
    padding: 10px;
    border: 1px solid #DDD;
    border-radius: 5px;
    resize: none;
    font-family: inherit;
    font-size: 14px;
    min-height: 40px;
    max-height: 150px;
    overflow-y: auto;
}

.bc-assistant-shortcode-input:focus {
    outline: none;
    border-color: #A67C52;
}

.bc-assistant-shortcode-send {
    padding: 8px 15px;
    border-radius: 5px;
    background-color: #A67C52;
    color: white;
    border: none;
    margin-left: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bc-assistant-shortcode-send:hover {
    background-color: #8D6848;
}

.bc-assistant-shortcode-send i {
    margin-left: 5px;
}

.bc-assistant-shortcode-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
}

.bc-assistant-shortcode-loading-dots {
    display: flex;
}

.bc-assistant-shortcode-loading-dot {
    width: 8px;
    height: 8px;
    background-color: #A67C52;
    border-radius: 50%;
    margin: 0 3px;
    animation: bc-assistant-shortcode-loading 1.4s infinite ease-in-out both;
}

.bc-assistant-shortcode-loading-dot:nth-child(1) {
    animation-delay: -0.32s;
}

.bc-assistant-shortcode-loading-dot:nth-child(2) {
    animation-delay: -0.16s;
}

@keyframes bc-assistant-shortcode-loading {
    0%, 80%, 100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}
</style>