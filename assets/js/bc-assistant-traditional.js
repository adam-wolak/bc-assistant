/**
 * BC Assistant - Traditional DOM Implementation
 * Version 2.0.0
 * 
 * Ten plik zawiera tylko implementację Traditional DOM dla BC Assistant.
 * Można go używać niezależnie lub połączyć z głównym plikiem bc-assistant.js.
 */

/**
 * Initialize traditional DOM implementation
 */
function initTraditionalDOM() {
    // This implementation works with the existing DOM elements from templates/assistant-wrapper.php
    jQuery(document).ready(function($) {
        // Find elements
        const $wrapper = $('.bc-assistant-wrapper');
        if (!$wrapper.length) return;
        
        const $bubble = $wrapper.find('.bc-assistant-bubble');
        const $window = $wrapper.find('.bc-assistant-window');
        const $closeBtn = $wrapper.find('.bc-assistant-close');
        const $minimizeBtn = $wrapper.find('.bc-assistant-minimize');
        const $messagesContainer = $wrapper.find('.bc-assistant-messages');
        const $input = $wrapper.find('.bc-assistant-input');
        const $sendBtn = $wrapper.find('.bc-assistant-send');
        
        // Initialize state
        let isOpen = false;
        let isTyping = false;
        let threadId = localStorage.getItem('bc_assistant_thread_id') || '';
        const messages = [];
        
        // Add mode toggle
        addModeToggle();
        
        // Make window draggable
        makeChatWindowDraggable();
        
        // Add welcome message
        addMessage('assistant', getWelcomeMessage());
        
        // Set up event handlers
        $bubble.on('click', toggleWindow);
        $closeBtn.on('click', closeWindow);
        $minimizeBtn.on('click', closeWindow);
        $sendBtn.on('click', sendMessage);
        
        $input.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        $input.on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Check for page resizing
        $(window).on('resize', adjustLayout);
        
        // Fix scrolling issues
        fixScrollingIssues();
        
        // Function implementations
        function toggleWindow() {
            if (isOpen) {
                closeWindow();
            } else {
                openWindow();
            }
        }
        
        function openWindow() {
            isOpen = true;
            $window.fadeIn(300);
            setTimeout(() => {
                $input.focus();
                scrollToBottom();
            }, 100);
        }
        
        function closeWindow() {
            isOpen = false;
            $window.fadeOut(300);
        }
        
        function getWelcomeMessage() {
            // Try from config
            if (window.bcAssistantData && window.bcAssistantData.welcomeMessage) {
                return window.bcAssistantData.welcomeMessage;
            }
            
            // Try from global variable
            if (typeof window.bcAssistantWelcomeMessage !== 'undefined') {
                return window.bcAssistantWelcomeMessage;
            }
            
            // Default fallback
            return 'Witaj! W czym mogę pomóc?';
        }
        
        function addMessage(role, content) {
            // Create message HTML
            const messageHtml = `
                <div class="bc-message bc-message-${role}">
                    <div class="bc-message-content">${formatMessage(content)}</div>
                    <div class="bc-message-timestamp">${formatTime(new Date())}</div>
                </div>
            `;
            
            // Add to container
            $messagesContainer.append(messageHtml);
            
            // Store in state
            messages.push({
                role,
                content,
                timestamp: new Date()
            });
            
            // Scroll to bottom with delay to ensure content is rendered
            setTimeout(() => {
                scrollToBottom();
            }, 50);
            
            // Try again with a longer delay for more complex content
            setTimeout(() => {
                scrollToBottom();
            }, 300);
        }
        
        function formatMessage(text) {
            if (!text) return '';
            
            return text
                // Replace newlines with <br>
                .replace(/\n/g, '<br>')
                // Bold text
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                // Italic text
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                // Inline code
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                // Links
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>')
                // Code blocks
                .replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        }
        
        function formatTime(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        function showTypingIndicator() {
            const indicatorHtml = `
                <div class="bc-message bc-message-assistant bc-typing-indicator">
                    <div class="bc-message-content">
                        <div class="bc-typing-dots">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </div>
            `;
            
            $messagesContainer.append(indicatorHtml);
            scrollToBottom();
        }
        
        function hideTypingIndicator() {
            $messagesContainer.find('.bc-typing-indicator').remove();
        }
        
        function scrollToBottom() {
            // Multiple scroll methods for better compatibility
            try {
                // Method 1: Standard method
                $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
                
                // Method 2: Use native element scrolling with extra buffer
                const container = $messagesContainer[0];
                if (container) {
                    container.scrollTop = container.scrollHeight + 1000;
                }
                
                // Method 3: Force scroll with animation
                $messagesContainer.animate({ scrollTop: $messagesContainer[0].scrollHeight }, 200);
                
                // Method 4: Use scrollIntoView on the last message
                const lastMessage = $messagesContainer.find('.bc-message').last()[0];
                if (lastMessage) {
                    lastMessage.scrollIntoView({ behavior: 'auto', block: 'end' });
                }
            } catch (error) {
                console.error('Error scrolling to bottom:', error);
            }
        }
        
        function fixScrollingIssues() {
            // Create a mutation observer to watch for new messages
            const observer = new MutationObserver((mutations) => {
                scrollToBottom();
            });
            
            // Start observing
            observer.observe($messagesContainer[0], { 
                childList: true,
                subtree: true 
            });
            
            // Initial scroll
            scrollToBottom();
            
            // Also try scrolling after a delay (in case of dynamic content loading)
            setTimeout(scrollToBottom, 500);
            setTimeout(scrollToBottom, 1000);
        }
        
        function adjustLayout() {
            // Adjust layout based on window size
            const isMobile = window.innerWidth < 768;
            
            if (isMobile) {
                $wrapper.addClass('bc-mobile');
            } else {
                $wrapper.removeClass('bc-mobile');
            }
        }
        
        function makeChatWindowDraggable() {
            // Check if window element exists
            if (!$window.length) return;
            
            let isDragging = false;
            let offsetX, offsetY;
            
            // Make header draggable
            const $header = $window.find('.bc-assistant-header');
            
            if ($header.length) {
                $header.css('cursor', 'move');
                
                $header.on('mousedown', startDrag);
                $header.on('touchstart', handleTouchStart);
            }
            
            function startDrag(e) {
                // Don't start dragging if we're clicking on control buttons
                if ($(e.target).closest('.bc-assistant-close, .bc-assistant-minimize').length) {
                    return;
                }
                
                isDragging = true;
                offsetX = e.clientX - $window.offset().left;
                offsetY = e.clientY - $window.offset().top;
                
                // Add event listeners
                $(document).on('mousemove', onDrag);
                $(document).on('mouseup', stopDrag);
                
                // Remove transition for smoother dragging
                $window.css('transition', 'none');
                
                // Prevent text selection
                $('body').css('user-select', 'none');
            }
            
            function handleTouchStart(e) {
                // Don't start dragging if we're touching control buttons
                if ($(e.target).closest('.bc-assistant-close, .bc-assistant-minimize').length) {
                    return;
                }
                
                const touch = e.originalEvent.touches[0];
                isDragging = true;
                offsetX = touch.clientX - $window.offset().left;
                offsetY = touch.clientY - $window.offset().top;
                
                // Add event listeners
                $(document).on('touchmove', handleTouchMove);
                $(document).on('touchend', handleTouchEnd);
                
                // Remove transition for smoother dragging
                $window.css('transition', 'none');
                
                // Prevent text selection
                $('body').css('user-select', 'none');
            }
            
            function onDrag(e) {
                if (!isDragging) return;
                
                const x = e.clientX - offsetX;
                const y = e.clientY - offsetY;
                
                // Stay within viewport boundaries
                const maxX = $(window).width() - $window.outerWidth();
                const maxY = $(window).height() - $window.outerHeight();
                
                const boundedX = Math.max(0, Math.min(x, maxX));
                const boundedY = Math.max(0, Math.min(y, maxY));
                
                // Set new position
                $window.css({
                    left: boundedX + 'px',
                    top: boundedY + 'px',
                    right: 'auto',
                    bottom: 'auto'
                });
            }
            
            function handleTouchMove(e) {
                if (!isDragging) return;
                
                const touch = e.originalEvent.touches[0];
                const x = touch.clientX - offsetX;
                const y = touch.clientY - offsetY;
                
                // Stay within viewport boundaries
                const maxX = $(window).width() - $window.outerWidth();
                const maxY = $(window).height() - $window.outerHeight();
                
                const boundedX = Math.max(0, Math.min(x, maxX));
                const boundedY = Math.max(0, Math.min(y, maxY));
                
                // Set new position
                $window.css({
                    left: boundedX + 'px',
                    top: boundedY + 'px',
                    right: 'auto',
                    bottom: 'auto'
                });
                
                // Prevent page scrolling
                e.preventDefault();
            }
            
            function stopDrag() {
                isDragging = false;
                
                // Remove event listeners
                $(document).off('mousemove', onDrag);
                $(document).off('mouseup', stopDrag);
                
                // Restore transition
                $window.css('transition', '');
                
                // Restore text selection
                $('body').css('user-select', '');
            }
            
            function handleTouchEnd() {
                isDragging = false;
                
                // Remove event listeners
                $(document).off('touchmove', handleTouchMove);
                $(document).off('touchend', handleTouchEnd);
                
                // Restore transition
                $window.css('transition', '');
                
                // Restore text selection
                $('body').css('user-select', '');
            }
        }
        
        function addModeToggle() {
            // Create the toggle buttons
            const toggleHtml = `
                <div class="bc-assistant-mode-toggle">
                    <button class="bc-mode-text active">Tekst</button>
                    <button class="bc-mode-voice">Głos</button>
                </div>
            `;
            
            // Create the voice modal
            const voiceModalHtml = `
                <div class="bc-voice-modal">
                    <div class="bc-voice-modal-content">
                        <h3>Nagrywanie głosu</h3>
                        <p>Kliknij przycisk, aby rozpocząć/zatrzymać nagrywanie</p>
                        <button class="bc-voice-record-button">
                            <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"></path><path fill="currentColor" d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"></path></svg>
                        </button>
                        <div class="bc-voice-status">Gotowy do nagrywania</div>
                    </div>
                </div>
            `;
            
            // Add toggle container to the chat window
            $messagesContainer.before(toggleHtml);
            
            // Add voice modal to the document body
            $('body').append(voiceModalHtml);
            
            // Get references to new elements
            const $toggleContainer = $wrapper.find('.bc-assistant-mode-toggle');
            const $textButton = $toggleContainer.find('.bc-mode-text');
            const $voiceButton = $toggleContainer.find('.bc-mode-voice');
            const $voiceModal = $('.bc-voice-modal');
            const $recordButton = $voiceModal.find('.bc-voice-record-button');
            const $statusText = $voiceModal.find('.bc-voice-status');
            
            // Hide voice modal initially
            $voiceModal.hide();
            
            // Set up event handlers
            $textButton.on('click', function() {
                $textButton.addClass('active');
                $voiceButton.removeClass('active');
                $wrapper.removeClass('bc-voice-mode');
                $voiceModal.hide();
            });
            
            $voiceButton.on('click', function() {
                $voiceButton.addClass('active');
                $textButton.removeClass('active');
                $wrapper.addClass('bc-voice-mode');
                $voiceModal.show();
            });
            
            // Voice recording logic
            let mediaRecorder;
            let audioChunks = [];
            let isRecording = false;
            
            $recordButton.on('click', function() {
                if (isRecording) {
                    // Stop recording
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                        $recordButton.removeClass('recording');
                        $statusText.text('Przetwarzanie...');
                        isRecording = false;
                    }
                } else {
                    // Start recording
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            isRecording = true;
                            $recordButton.addClass('recording');
                            $statusText.text('Nagrywanie... (kliknij, aby zatrzymać)');
                            
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];
                            
                            mediaRecorder.ondataavailable = event => {
                                audioChunks.push(event.data);
                            };
                            
                            mediaRecorder.onstop = () => {
                                // Combine audio chunks into a blob
                                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                                
                                // Send audio to server
                                sendAudioToServer(audioBlob);
                                
                                // Release microphone
                                stream.getTracks().forEach(track => track.stop());
                                
                                // Reset UI
                                $statusText.text('Gotowy do nagrywania');
                                
                                // Return to text mode
                                $textButton.addClass('active');
                                $voiceButton.removeClass('active');
                                $voiceModal.hide();
                            };
                            
                            // Start recording
                            mediaRecorder.start();
                            
                            // Auto-stop after 15 seconds if user forgets to stop
                            setTimeout(() => {
                                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                                    mediaRecorder.stop();
                                    $recordButton.removeClass('recording');
                                    isRecording = false;
                                }
                            }, 15000);
                        })
                        .catch(error => {
                            console.error("Error accessing microphone:", error);
                            
                            // Show error message
                            $statusText.text('Błąd dostępu do mikrofonu');
                            
                            // Also add as message
                            addMessage('assistant', 'Nie udało się uzyskać dostępu do mikrofonu. Upewnij się, że masz włączony mikrofon i zezwoliłeś na dostęp do niego.');
                            
                            isRecording = false;
                            $recordButton.removeClass('recording');
                            
                            // Return to text mode
                            $textButton.addClass('active');
                            $voiceButton.removeClass('active');
                            $voiceModal.hide();
                        });
                }
            });
        }
        
        // Add voice button to the interface
        const $inputContainer = $wrapper.find('.bc-assistant-input-container');
        const $voiceButton = $('<button class="bc-assistant-voice">');
        $voiceButton.html('<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"></path><path fill="currentColor" d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"></path></svg>');
        $voiceButton.insertBefore($sendBtn);

        // Voice recording configuration
        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;

        $voiceButton.on('click', function() {
            if (isRecording) {
                // Stop recording
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    mediaRecorder.stop();
                    $voiceButton.removeClass('recording');
                    isRecording = false;
                }
            } else {
                // Start recording
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(stream => {
                        isRecording = true;
                        $voiceButton.addClass('recording');
                        
                        mediaRecorder = new MediaRecorder(stream);
                        audioChunks = [];
                        
                        mediaRecorder.ondataavailable = event => {
                            audioChunks.push(event.data);
                        };
                        
                        mediaRecorder.onstop = () => {
                            // Combine audio chunks into a blob
                            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                            
                            // Send audio to server
                            sendAudioToServer(audioBlob);
                            
                            // Release microphone
                            stream.getTracks().forEach(track => track.stop());
                        };
                        
                        // Start recording
                        mediaRecorder.start();
                        
                        // Auto-stop after 15 seconds if user forgets to stop
                        setTimeout(() => {
                            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                                mediaRecorder.stop();
                                $voiceButton.removeClass('recording');
                                isRecording = false;
                            }
                        }, 15000);
                    })
                    .catch(error => {
                        console.error("Error accessing microphone:", error);
                        
                        // Show error message to user
                        addMessage('assistant', 'Nie udało się uzyskać dostępu do mikrofonu. Upewnij się, że masz włączony mikrofon i zezwoliłeś na dostęp do niego.');
                        
                        isRecording = false;
                        $voiceButton.removeClass('recording');
                    });
            }
        });

        function sendAudioToServer(audioBlob) {
            // Show typing indicator
            showTypingIndicator();
            
            // Create form data
            const formData = new FormData();
            formData.append('action', window.bcAssistantData ? window.bcAssistantData.action : 'bc_assistant_send_message');
            formData.append('audio', audioBlob);
            formData.append('thread_id', threadId);
            formData.append('nonce', window.bcAssistantData ? window.bcAssistantData.nonce : '');
            formData.append('is_voice', 'true');
            
            // Get page context
            const context = $wrapper.data('context') || 'default';
            const procedureName = $wrapper.data('procedure') || '';
            
            if (context !== 'default') {
                formData.append('context', context);
            }
            
            if (procedureName) {
                formData.append('procedure_name', procedureName);
            }
            
            // Update state
            isTyping = true;
            
            // Send request
            fetch(window.bcAssistantData ? window.bcAssistantData.apiEndpoint : '/wp-admin/admin-ajax.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Hide typing indicator
                hideTypingIndicator();
                
                // Update state
                isTyping = false;
                
                // Handle response
                if (data.success) {
                    // Add transcribed user message if available
                    if (data.data.transcription) {
                        addMessage('user', data.data.transcription);
                    }
                    
                    // Add assistant response
                    addMessage('assistant', data.data.message);
                    
                    // Store thread ID if provided
                    if (data.data.thread_id) {
                        threadId = data.data.thread_id;
                        localStorage.setItem('bc_assistant_thread_id', threadId);
                    }
                } else {
                    // Show error message
                    addMessage('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie później.');
                    
                    // Log error
                    if (window.bcAssistantData && window.bcAssistantData.debug) {
                        console.error('BC Assistant API Error:', data);
                    }
                }
            })
            .catch(error => {
                // Hide typing indicator
                hideTypingIndicator();
                
                // Update state
                isTyping = false;
                
                // Show error message
                addMessage('assistant', 'Przepraszam, wystąpił błąd połączenia. Spróbuj ponownie później.');
                
                // Log error
                if (window.bcAssistantData && window.bcAssistantData.debug) {
                    console.error('BC Assistant Error:', error);
                }
            });
        }
        
        async function sendMessage() {
            const messageText = $input.val().trim();
            
            // Skip empty messages or if already sending
            if (!messageText || isTyping) return;
            
            // Update state
            isTyping = true;
            
            // Add user message to chat
            addMessage('user', messageText);
            
            // Clear input field
            $input.val('');
            $input.css('height', 'auto');
            
            // Show typing indicator
            showTypingIndicator();
            
            try {
                // Create form data
                const formData = new FormData();
                formData.append('action', window.bcAssistantData ? window.bcAssistantData.action : 'bc_assistant_send_message');
                formData.append('message', messageText);
                formData.append('thread_id', threadId);
                formData.append('nonce', window.bcAssistantData ? window.bcAssistantData.nonce : '');
                
                // Get page context
                const context = $wrapper.data('context') || 'default';
                const procedureName = $wrapper.data('procedure') || '';
                
                if (context !== 'default') {
                    formData.append('context', context);
                }
                
                if (procedureName) {
                    formData.append('procedure_name', procedureName);
                }
                
                // Send request
                const response = await fetch(window.bcAssistantData ? window.bcAssistantData.apiEndpoint : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                
                // Parse response
                const data = await response.json();
                
                // Hide typing indicator
                hideTypingIndicator();
                
                // Update state
                isTyping = false;
                
                // Handle response
                if (data.success) {
                    // Add assistant response
                    addMessage('assistant', data.data.message);
                    
                    // Store thread ID if provided
                    if (data.data.thread_id) {
                        threadId = data.data.thread_id;
                        localStorage.setItem('bc_assistant_thread_id', threadId);
                    }
                } else {
                    // Show error message
                    addMessage('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie później.');
                    
                    // Log error
                    if (window.bcAssistantData && window.bcAssistantData.debug) {
                        console.error('BC Assistant API Error:', data);
                    }
                }
            } catch (error) {
                // Hide typing indicator
                hideTypingIndicator();
                
                // Update state
                isTyping = false;
                
                // Show error message
                addMessage('assistant', 'Przepraszam, wystąpił błąd połączenia. Spróbuj ponownie później.');
                
                // Log error
                if (window.bcAssistantData && window.bcAssistantData.debug) {
                    console.error('BC Assistant Error:', error);
                }
            }
        }
        
        // Initialize layout
        adjustLayout();
        
        // Log initialization
        if (window.bcAssistantData && window.bcAssistantData.debug) {
            console.log('BC Assistant initialized with model:', window.bcAssistantData.model || 'default');
        }
        
        // Make window and functions globally accessible
        window.bcAddMessage = addMessage;
        window.bcSendAudioToServer = sendAudioToServer;
        window.bcScrollToBottom = scrollToBottom;
    });
}

// Initialize traditional DOM implementation if not already initialized elsewhere
if (typeof window !== 'undefined') {
    if (!window.bcAssistantInitialized) {
        initTraditionalDOM();
        window.bcAssistantInitialized = true;
    }
}