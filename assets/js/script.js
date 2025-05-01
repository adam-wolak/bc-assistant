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
            this.conversationId = localStorage.getItem('bc_assistant_conversation_id') || '';
            this.threadId = localStorage.getItem('bc_assistant_thread_id') || ''; // Dodane dla OpenAI Threads API
            this.isVoiceMode = false;
            this.isFullVoiceMode = false;
            this.isDragging = false;
            
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
            // Reset all states
            this.resetState();
            
            this.chatWindow.fadeIn(300);
            this.chatInput.focus();
            
            // Add draggable functionality
            this.makeDraggable(this.chatWindow);
            
            // On mobile, add open class to container
            if (this.isMobile) {
                this.container.addClass('open');
            }
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        /**
         * Minimize chat window
         */
        minimizeChat() {
            this.chatWindow.fadeOut(300, () => {
                // After fadeout, make sure we can reopen
                this.resetState();
            });
            
            // On mobile, remove open class
            if (this.isMobile) {
                this.container.removeClass('open');
            }
        }
        
        /**
         * Close chat window
         */
        closeChat() {
            this.chatWindow.fadeOut(300, () => {
                // After fadeout, make sure we can reopen
                this.resetState();
            });
            
            // On mobile, remove open class
            if (this.isMobile) {
                this.container.removeClass('open');
            }
        }
        
        /**
         * Reset all states
         */
        resetState() {
            this.isDragging = false;
            this.isVoiceMode = false;
            this.isFullVoiceMode = false;
            
            // Remove all events that could interfere
            $(document).off('mousemove.dragassistant touchmove.dragassistant');
            $(document).off('mouseup.dragassistant touchend.dragassistant');
            
            // Reset any CSS that might interfere
            $('body').css('user-select', '');
            
            // Make sure the chat window has the right position
            if (window.innerWidth <= 767) {
                // Mobile position
                this.chatWindow.css({
                    position: '',
                    left: '',
                    top: '',
                    right: '',
                    bottom: ''
                });
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
                    thread_id: this.threadId, // Dodane dla Threads API
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
                        
                        // Store conversation or thread ID
                        if (response.data.conversation_id) {
                            this.conversationId = response.data.conversation_id;
                            localStorage.setItem('bc_assistant_conversation_id', this.conversationId);
                        }
                        
                        // Obsługa thread_id z odpowiedzi
                        if (response.data.thread_id) {
                            this.threadId = response.data.thread_id;
                            localStorage.setItem('bc_assistant_thread_id', this.threadId);
                        }
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
         * Send voice message without updating chat interface
         */
        sendVoiceMessage(message, expectReply = false) {
            if (!message) return;
            
            // Wysyłanie wiadomości bez aktualizacji interfejsu czatu
            $.ajax({
                url: bcAssistantData.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bc_assistant_send_message',
                    message: message,
                    conversation_id: this.conversationId,
                    thread_id: this.threadId, // Dodane dla Threads API
                    page_url: window.location.href,
                    page_title: document.title,
                    voice_mode: true,
                    nonce: bcAssistantData.nonce
                },
                success: (response) => {
                    this.voiceMode.show();
                    
                    if (response.success) {
                        // Odtwórz odpowiedź głosowo bez pokazywania czatu
                        this.speakResponse(response.data.message, expectReply);
                        
                        if (!expectReply) {
                            this.container.find('.bc-assistant-voice-status').text('Naciśnij mikrofon, aby kontynuować');
                        }
                        
                        // Store conversation or thread ID
                        if (response.data.conversation_id) {
                            this.conversationId = response.data.conversation_id;
                            localStorage.setItem('bc_assistant_conversation_id', this.conversationId);
                        }
                        
                        // Obsługa thread_id z odpowiedzi
                        if (response.data.thread_id) {
                            this.threadId = response.data.thread_id;
                            localStorage.setItem('bc_assistant_thread_id', this.threadId);
                        }
                    } else {
                        this.speakResponse('Przepraszam, wystąpił błąd. Spróbuj ponownie.', false);
                        this.container.find('.bc-assistant-voice-status').text('Wystąpił błąd. Spróbuj ponownie.');
                    }
                    
                    // Tylko zmień ikonę jeśli nie oczekujemy odpowiedzi
                    if (!expectReply) {
                        this.voiceButton.html('<i class="fas fa-microphone"></i>');
                    }
                },
                error: () => {
                    this.voiceButton.html('<i class="fas fa-microphone"></i>');
                    this.container.find('.bc-assistant-voice-status').text('Wystąpił błąd. Spróbuj ponownie.');
                    this.speakResponse('Przepraszam, wystąpił błąd. Spróbuj ponownie.', false);
                }
            });
        }
        
        /**
         * Speak response using speech synthesis
         */
        speakResponse(text, expectReply = true) {
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
            
            // Jeśli oczekujemy odpowiedzi, nasłuchuj zdarzenia końca mowy
            if (expectReply) {
                utterance.onend = () => {
                    // Po zakończeniu mowy, rozpocznij nasłuchiwanie odpowiedzi użytkownika
                    if (this.isFullVoiceMode) {
                        setTimeout(() => {
                            this.container.find('.bc-assistant-voice-status').text('Czekam na Twoją odpowiedź...');
                            this.voiceButton.html('<i class="fas fa-microphone"></i>');
                            
                            // Automatycznie uruchom nasłuchiwanie po krótkiej przerwie
                            setTimeout(() => {
                                this.startVoiceRecognition(true); // true oznacza kontynuację konwersacji
                            }, 1000);
                        }, 300);
                    }
                };
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
         * Scroll messages container to bottom - ULEPSZONA WERSJA
         */
        scrollToBottom() {
            // Bardziej niezawodne przewijanie z różnymi poziomami zabezpieczeń
            if (!this.messagesContainer || !this.messagesContainer.length) {
                return;
            }
            
            const scrollContainer = this.messagesContainer[0];
            
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
                        const lastMessage = this.messagesContainer.children().last();
                        if (lastMessage.length) {
                            lastMessage[0].scrollIntoView({ behavior: 'auto', block: 'end' });
                        }
                    } catch (e) {
                        console.error('Final scroll failed:', e);
                    }
                }, 300);
            }, 50);
        }
        
        /**
         * Make element draggable
         */
        makeDraggable(element) {
            if (!element || !element.length) return;
            
            const $header = element.find('.bc-assistant-header');
            let isDragging = false;
            let startX, startY;
            let startPos = { left: 0, top: 0 };
            let hasMoved = false;
            
            // Zatrzymaj wszystkie wydarzenia click w nagłówku, aby nie zamykały okna
            $header.off('click').on('click', function(e) {
                if (!$(e.target).closest('.bc-assistant-controls').length) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            // Set cursor style
            $header.css('cursor', 'move');
            
            // Mouse down event
            $header.off('mousedown touchstart').on('mousedown touchstart', function(e) {
                // Don't handle events from control buttons
                if ($(e.target).closest('.bc-assistant-controls').length > 0) {
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();
                
                isDragging = true;
                hasMoved = false;
                
                // Get initial positions
                startX = e.type === 'mousedown' ? e.clientX : e.originalEvent.touches[0].clientX;
                startY = e.type === 'mousedown' ? e.clientY : e.originalEvent.touches[0].clientY;
                
                startPos = {
                    left: parseInt(element.css('left')) || element.offset().left,
                    top: parseInt(element.css('top')) || element.offset().top
                };
                
                // Ensure window won't close when dragging
                $('body').css('user-select', 'none');
            });
            
            // Mouse move event
            $(document).off('mousemove.dragassistant touchmove.dragassistant').on('mousemove.dragassistant touchmove.dragassistant', function(e) {
                if (!isDragging) return;
                
                e.preventDefault();
                e.stopPropagation();
                
                hasMoved = true;
                
                const clientX = e.type === 'mousemove' ? e.clientX : e.originalEvent.touches[0].clientX;
                const clientY = e.type === 'mousemove' ? e.clientY : e.originalEvent.touches[0].clientY;
                
                // Calculate new position
                const deltaX = clientX - startX;
                const deltaY = clientY - startY;
                
                // Set element to absolute positioning if not already
                if (element.css('position') !== 'absolute') {
                    const currentOffset = element.offset();
                    element.css({
                        position: 'absolute',
                        left: currentOffset.left + 'px',
                        top: currentOffset.top + 'px',
                        right: 'auto',
                        bottom: 'auto',
                        'z-index': 999999
                    });
                }
                
                // Apply new position
                element.css({
                    left: (startPos.left + deltaX) + 'px',
                    top: (startPos.top + deltaY) + 'px'
                });
            });
            
            // Mouse up event
            $(document).off('mouseup.dragassistant touchend.dragassistant').on('mouseup.dragassistant touchend.dragassistant', function(e) {
                if (!isDragging) return;
                
                e.preventDefault();
                
                isDragging = false;
                $('body').css('user-select', '');
                
                // Prevent window from closing if it was dragged
                if (hasMoved) {
                    e.stopPropagation();
                }
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
            
            // Voice button triggers voice recognition
            this.voiceButton.off('click').on('click', () => {
                this.voiceMode.show();
                this.chatWindow.hide(); // Hide chat window in voice mode
                this.startVoiceRecognition();
            });
        }
        
        /**
         * Start voice recognition with fallback options
         */
        startVoiceRecognition(isContinuation = false) {
            // Pokaż informację o nasłuchiwaniu
            this.voiceButton.html('<i class="fas fa-spinner fa-spin"></i>');
            this.container.find('.bc-assistant-voice-status').text('Słucham...');
            
            // Ustaw flagę trybu głosowego
            this.isFullVoiceMode = true;

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
            
            // Zwiększ timeout nasłuchiwania, aby dać użytkownikowi czas na odpowiedź
            recognition.maxSpeechTime = 10000; // 10 sekund
            
            try {
                recognition.start();
                
                // Jeśli nie ma odpowiedzi w ciągu określonego czasu, zatrzymaj nasłuchiwanie
                const listenTimeout = setTimeout(() => {
                    if (recognition) {
                        recognition.stop();
                        this.voiceButton.html('<i class="fas fa-microphone"></i>');
                        this.container.find('.bc-assistant-voice-status').text('Nie usłyszałem odpowiedzi. Naciśnij mikrofon, aby kontynuować.');
                    }
                }, 15000); // 15 sekund na odpowiedź
                
                recognition.onresult = (event) => {
                    clearTimeout(listenTimeout); // Anuluj timeout
                    
                    const transcript = event.results[0][0].transcript;
                    
                    // Set flag for voice mode
                    this.isVoiceMode = true;
                    
                    if (this.isFullVoiceMode) {
                        // W pełnym trybie głosowym nie pokazuj interfejsu czatu
                        this.voiceMode.show();
                        this.chatWindow.hide(); // Upewnij się, że czat jest ukryty
                        this.container.find('.bc-assistant-voice-status').text('Przetwarzam pytanie...');
                        
                        // Wyślij pytanie bez pokazywania interfejsu czatu
                        this.sendVoiceMessage(transcript, true); // true oznacza, że oczekujemy odpowiedzi
                    } else {
                        // Standardowe zachowanie - pokaż czat
                        this.voiceMode.hide();
                        this.chatWindow.show();
                        this.chatInput.val(transcript);
                        this.sendMessage();
                    }
                };
                
                recognition.onerror = (event) => {
                    clearTimeout(listenTimeout); // Anuluj timeout
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
                    clearTimeout(listenTimeout); // Anuluj timeout
                    
                    // Zresetuj przycisk i status tylko jeśli nie przetwarzamy jeszcze pytania
                    if (this.container.find('.bc-assistant-voice-status').text() !== 'Przetwarzam pytanie...') {
                        this.voiceButton.html('<i class="fas fa-microphone"></i>');
                        this.container.find('.bc-assistant-voice-status').text('Naciśnij mikrofon, aby mówić');
                    }
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