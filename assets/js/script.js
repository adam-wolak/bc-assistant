/**
 * BC Assistant Scripts
 */
(function($) {
    'use strict';
    
    /**
     * BC Assistant Bubble Class
     */
    class BCAssistantBubble {
        /**
         * Constructor
         */
        constructor() {
            this.container = $('.bc-assistant-bubble-container');
            this.bubbleButton = $('.bc-assistant-bubble-button');
            this.chatWindow = $('.bc-assistant-chat-window');
            this.messagesContainer = $('.bc-assistant-messages');
            this.chatInput = $('.bc-assistant-input');
            this.sendButton = $('.bc-assistant-send');
            this.minimizeButton = $('.bc-assistant-minimize');
            this.closeButton = $('.bc-assistant-close');
            this.conversationId = '';
            this.isVoiceMode = false;
            
            this.init();
        }
        
        /**
         * Initialize
         */
        init() {
            // Set event listeners
            this.bubbleButton.on('click', () => this.toggleChat());
            this.sendButton.on('click', () => this.sendMessage());
            this.minimizeButton.on('click', () => this.minimizeChat());
            this.closeButton.on('click', () => this.closeChat());
            
            // Handle input when pressing Enter (but allow Shift+Enter for newlines)
            this.chatInput.on('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Auto-expand textarea
            this.chatInput.on('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
            
            // Show welcome message when initialized
            this.addMessage(bcAssistantData.welcomeMessage, 'assistant');
            
            // Detect mobile devices and setup mobile mode if needed
            this.isMobile = window.innerWidth <= 767;
            if (this.isMobile) {
                this.setupMobileMode();
            }
            
            // Handle window resize
            $(window).on('resize', () => {
                const isMobileNow = window.innerWidth <= 767;
                if (isMobileNow !== this.isMobile) {
                    this.isMobile = isMobileNow;
                    if (this.isMobile) {
                        this.setupMobileMode();
                    }
                }
            });
            
            // Initialize voices for speech synthesis (if available)
            if ('speechSynthesis' in window) {
                // Load voices
                window.speechSynthesis.onvoiceschanged = () => {
                    this.voices = window.speechSynthesis.getVoices();
                };
                
                // Get voices
                this.voices = window.speechSynthesis.getVoices();
            }
        }
        
        /**
         * Toggle chat window
         */
        toggleChat() {
            if (this.chatWindow.is(':visible')) {
                this.chatWindow.fadeOut(300);
                if (this.isMobile) {
                    this.container.removeClass('open');
                }
            } else {
                this.openChat();
            }
        }
        
        /**
         * Open chat window
         */
        openChat() {
            this.chatWindow.fadeIn(300);
            this.chatInput.focus();
            
            // Add draggable functionality
            this.makeDraggable(this.chatWindow);
            
            // On mobile, add open class to container
            if (this.isMobile) {
                this.container.addClass('open');
            }
            
            // Scroll to bottom to ensure latest messages are visible
            this.scrollToBottom();
        }
        
        /**
         * Minimize chat window
         */
        minimizeChat() {
            this.chatWindow.fadeOut(300);
            
            // On mobile, remove open class
            if (this.isMobile) {
                this.container.removeClass('open');
            }
        }
        
        /**
         * Close chat window
         */
        closeChat() {
            this.chatWindow.fadeOut(300);
            
            // On mobile, remove open class
            if (this.isMobile) {
                this.container.removeClass('open');
            }
        }
        
        /**
         * Send message to API
         */
        sendMessage() {
            const message = this.chatInput.val().trim();
            
            if (!message) {
                return;
            }
            
            // Store if this message came from voice mode
            const isVoiceMessage = this.isVoiceMode;
            this.isVoiceMode = false;
            
            // Add user message to chat
            this.addMessage(message, 'user');
            
            // Clear input
            this.chatInput.val('');
            this.chatInput.css('height', 'auto');
            
            // Show loading indicator
            this.showLoading();
            
            // Add current URL to request data for better context
            const currentPageUrl = window.location.href;
            const currentPageTitle = document.title;
            
            // Send message to server
            $.ajax({
                url: bcAssistantData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bc_assistant_send_message',
                    message: message,
                    conversation_id: this.conversationId,
                    page_url: currentPageUrl,
                    page_title: currentPageTitle,
                    nonce: bcAssistantData.nonce
                },
                success: (response) => {
                    // Hide loading indicator
                    this.hideLoading();
                    
                    if (response.success) {
                        // Add assistant response to chat
                        this.addMessage(response.data.message, 'assistant');
                        
                        // If message came from voice mode, speak the response
                        if (isVoiceMessage && 'speechSynthesis' in window) {
                            this.speakResponse(response.data.message);
                        }
                        
                        // Store conversation ID
                        this.conversationId = response.data.conversation_id;
                    } else {
                        // Show error message
                        this.addMessage('Przepraszam, wystąpił błąd. Spróbuj ponownie później.', 'assistant');
                        console.error('BC Assistant API Error:', response.data);
                    }
                },
                error: (xhr, status, error) => {
                    // Hide loading indicator
                    this.hideLoading();
                    
                    // Show error message
                    this.addMessage('Przepraszam, wystąpił błąd. Spróbuj ponownie później.', 'assistant');
                    console.error('BC Assistant AJAX Error:', status, error);
                }
            });
        }
        
        /**
         * Speak response using speech synthesis
         */
        speakResponse(text) {
            // Stop current speech if any
            if (window.speechSynthesis.speaking) {
                window.speechSynthesis.cancel();
            }
            
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'pl-PL';
            
            // Find Polish voice if available
            const polishVoice = this.voices ? this.voices.find(voice => voice.lang.includes('pl')) : null;
            if (polishVoice) {
                utterance.voice = polishVoice;
            }
            
            window.speechSynthesis.speak(utterance);
        }
        
        /**
         * Add message to chat
         */
        addMessage(message, role) {
            // Create message element
            const messageElement = $('<div class="bc-assistant-message"></div>');
            messageElement.addClass(role);
            
            // Format message content (handle markdown-like syntax)
            let formattedMessage = this.formatMessage(message);
            
            // Set message content
            messageElement.html(formattedMessage);
            
            // Add timestamp
            const now = new Date();
            const timestamp = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const metaElement = $('<div class="bc-assistant-message-meta"></div>');
            metaElement.text(timestamp);
            messageElement.append(metaElement);
            
            // Add message to container
            this.messagesContainer.append(messageElement);
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        /**
         * Format message with markdown-like syntax
         */
        formatMessage(message) {
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
        
        /**
         * Show loading indicator
         */
        showLoading() {
            // Create loading element if it doesn't exist
            if (!this.loadingElement) {
                this.loadingElement = $(`
                    <div class="bc-assistant-loading">
                        <div class="bc-assistant-loading-dots">
                            <div class="bc-assistant-loading-dot"></div>
                            <div class="bc-assistant-loading-dot"></div>
                            <div class="bc-assistant-loading-dot"></div>
                        </div>
                    </div>
                `);
            }
            
            // Add loading element to messages container
            this.messagesContainer.append(this.loadingElement);
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        /**
         * Hide loading indicator
         */
        hideLoading() {
            if (this.loadingElement) {
                this.loadingElement.remove();
            }
        }
        
        /**
         * Scroll messages container to bottom
         */
        scrollToBottom() {
            // Use setTimeout to ensure scrolling happens after DOM update
            setTimeout(() => {
                this.messagesContainer.scrollTop(this.messagesContainer[0].scrollHeight);
            }, 100);
        }
        
        /**
         * Make element draggable
         */
        makeDraggable(element) {
            if (!element || !element.length) return;
            
            const $header = element.find('.bc-assistant-header');
            let isDragging = false;
            let offsetX, offsetY;
            let wasClick = true; // Track if it was a click or drag
            let moved = false; // Track if the element was moved
            
            // Set cursor style for header
            $header.css('cursor', 'move');
            
            // Handle mouse/touch events for dragging
            $header.on('mousedown touchstart', function(e) {
                // Don't trigger on controls
                if ($(e.target).closest('.bc-assistant-controls').length > 0) {
                    return;
                }
                
                isDragging = true;
                wasClick = true; // Assume it's a click until moved
                moved = false; // Reset moved flag
                
                const pageX = e.type === 'mousedown' ? e.pageX : e.originalEvent.touches[0].pageX;
                const pageY = e.type === 'mousedown' ? e.pageY : e.originalEvent.touches[0].pageY;
                
                const elementOffset = element.offset();
                offsetX = pageX - elementOffset.left;
                offsetY = pageY - elementOffset.top;
                
                e.preventDefault();
            });
            
            $(document).on('mousemove touchmove', function(e) {
                if (!isDragging) return;
                
                // If moved, it's not just a click
                wasClick = false;
                moved = true;
                
                const pageX = e.type === 'mousemove' ? e.pageX : e.originalEvent.touches[0].pageX;
                const pageY = e.type === 'mousemove' ? e.pageY : e.originalEvent.touches[0].pageY;
                
                // Change positioning to absolute if it's not already
                if (element.css('position') !== 'absolute') {
                    const position = element.position();
                    element.css({
                        'position': 'absolute',
                        'z-index': 9999999,
                        'left': position.left + 'px',
                        'top': position.top + 'px',
                        'right': 'auto',
                        'bottom': 'auto'
                    });
                }
                
                const windowWidth = $(window).width();
                const windowHeight = $(window).height();
                const elementWidth = element.outerWidth();
                const elementHeight = element.outerHeight();
                
                // Calculate new coordinates, ensuring window stays within screen bounds
                let newLeft = Math.max(0, Math.min(pageX - offsetX, windowWidth - elementWidth));
                let newTop = Math.max(0, Math.min(pageY - offsetY, windowHeight - elementHeight));
                
                // Set new position
                element.css({
                    'left': newLeft + 'px',
                    'top': newTop + 'px'
                });
                
                e.preventDefault();
            });
            
            $(document).on('mouseup touchend', function() {
                if (!isDragging) return;
                isDragging = false;
            });
        }
        
        /**
         * Setup mobile mode
         */
        setupMobileMode() {
            // Add HTML for voice mode if it doesn't exist
            if (this.container.find('.bc-assistant-voice-mode').length === 0) {
                const voiceModeHTML = `
                    <div class="bc-assistant-voice-mode">
                        <div class="bc-assistant-voice-title">Tryb głosowy</div>
                        <div class="bc-assistant-voice-button">
                            <i class="fas fa-microphone"></i>
                        </div>
                        <div class="bc-assistant-voice-status">Naciśnij mikrofon, aby mówić</div>
                    </div>
                `;
                
                this.container.append(voiceModeHTML);
            }
            
            this.voiceMode = this.container.find('.bc-assistant-voice-mode');
            this.voiceButton = this.container.find('.bc-assistant-voice-button');
            
            // Modify bubble button behavior for mobile
            this.bubbleButton.off('click').on('click', () => {
                if (!this.container.hasClass('open')) {
                    this.container.addClass('open');
                    this.voiceMode.show();
                    this.chatWindow.hide(); // Make sure chat window is hidden
                } else {
                    this.container.removeClass('open');
                    this.voiceMode.hide();
                    this.chatWindow.hide();
                }
            });
            
            // Make sure voice button properly displays chat window
            this.voiceButton.off('click').on('click', () => {
                this.voiceMode.hide();
                this.chatWindow.fadeIn(300);
                this.startVoiceRecognition();
                this.scrollToBottom(); // Ensure messages are visible
            });
        }
        
        /**
         * Start voice recognition with fallback options
         */
        startVoiceRecognition() {
            // Show listening status
            this.voiceButton.html('<i class="fas fa-spinner fa-spin"></i>');
            this.container.find('.bc-assistant-voice-status').text('Słucham...');

            // Check if browser supports Web Speech API
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                this.useOpenAIAudio();
                return;
            }
            
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();
            recognition.lang = 'pl-PL';
            recognition.continuous = false;
            recognition.interimResults = false;
            
            try {
                recognition.start();
                
                recognition.onresult = (event) => {
                    const transcript = event.results[0][0].transcript;
                    
                    // Set flag for voice mode
                    this.isVoiceMode = true;
                    
                    // Hide voice mode and show chat window
                    this.voiceMode.hide();
                    this.chatWindow.show();
                    
                    // Set input value and send message
                    this.chatInput.val(transcript);
                    this.sendMessage();
                };
                
                recognition.onerror = (event) => {
                    console.error('Speech recognition error', event.error);
                    
                    // Try OpenAI audio API if Web Speech API fails
                    if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                        this.useOpenAIAudio();
                    } else {
                        this.voiceButton.html('<i class="fas fa-microphone"></i>');
                        this.container.find('.bc-assistant-voice-status').text('Wystąpił błąd. Spróbuj ponownie.');
                    }
                };
                
                recognition.onend = () => {
                    this.voiceButton.html('<i class="fas fa-microphone"></i>');
                    this.container.find('.bc-assistant-voice-status').text('Naciśnij mikrofon, aby mówić');
                };
            } catch (error) {
                console.error('Speech recognition error:', error);
                this.useOpenAIAudio();
            }
        }
        
        /**
         * Fallback to manual input when speech recognition is not available
         */
        useOpenAIAudio() {
            // Reset UI
            this.voiceButton.html('<i class="fas fa-microphone"></i>');
            this.container.find('.bc-assistant-voice-status').text('Naciśnij mikrofon, aby mówić');
            
            // Show message and open chat window
            this.voiceMode.hide();
            this.chatWindow.fadeIn(300);
            
            // Add message explaining the issue
            this.addMessage('Twoja przeglądarka nie obsługuje rozpoznawania mowy. Możesz wpisać pytanie ręcznie.', 'assistant');
            
            // Focus on input
            this.chatInput.focus();
            
            // If possible, try native audio recording with OpenAI
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                this.tryOpenAIAudioRecording();
            }
        }
        
        /**
         * Try to use OpenAI audio API
         */
        tryOpenAIAudioRecording() {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    const mediaRecorder = new MediaRecorder(stream);
                    const audioChunks = [];

                    mediaRecorder.addEventListener("dataavailable", event => {
                        audioChunks.push(event.data);
                    });

                    mediaRecorder.addEventListener("stop", () => {
                        // Stop stream
                        stream.getTracks().forEach(track => track.stop());
                        
                        // Create audio blob
                        const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                        
                        // Prepare FormData
                        const formData = new FormData();
                        formData.append('audio', audioBlob);
                        formData.append('action', 'bc_assistant_transcribe_audio');
                        formData.append('nonce', bcAssistantData.nonce);
                        
                        // Send to our endpoint
                        $.ajax({
                            url: bcAssistantData.ajaxUrl,
                            method: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: (response) => {
                                if (response.success) {
                                    // Set flag for voice mode
                                    this.isVoiceMode = true;
                                    
                                    // Set input value and send message
                                    this.chatInput.val(response.data.text);
                                    this.sendMessage();
                                } else {
                                    this.addMessage('Nie udało się przetworzyć nagrania. Spróbuj wpisać pytanie ręcznie.', 'assistant');
                                }
                            },
                            error: () => {
                                this.addMessage('Wystąpił błąd podczas przetwarzania nagrania. Spróbuj wpisać pytanie ręcznie.', 'assistant');
                            }
                        });
                    });

                    // Start recording
                    mediaRecorder.start();
                    
                    // Record for 5 seconds
                    setTimeout(() => {
                        if (mediaRecorder.state === "recording") {
                            mediaRecorder.stop();
                        }
                    }, 5000);
                })
                .catch(error => {
                    console.error('Error accessing microphone:', error);
                    this.addMessage('Nie można uzyskać dostępu do mikrofonu. Spróbuj wpisać pytanie ręcznie.', 'assistant');
                });
        }
    }
    
    // Initialize assistant when document is ready
    $(document).ready(function() {
        window.bcAssistant = new BCAssistantBubble();
    });
    
})(jQuery);