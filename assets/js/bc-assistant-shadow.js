/**
 * BC Assistant - Shadow DOM Implementation
 * Version 2.0.0
 * 
 * Ten plik zawiera kompletną implementację Shadow DOM dla BC Assistant.
 * Można go używać niezależnie lub połączyć z głównym plikiem bc-assistant.js.
 */

/**
 * Initialize Shadow DOM implementation
 */
function initShadowDOM() {
    // Define the custom element
    class BCAssistantWidget extends HTMLElement {
        constructor() {
            super();
            
            // Create shadow root for style isolation
            this.attachShadow({ mode: 'open' });
            
            // Initialize state
            this.state = {
                messages: [],
                isOpen: false,
                isTyping: false,
                threadId: localStorage.getItem('bc_assistant_thread_id') || ''
            };
            
            // Get configuration
            this.config = window.bcAssistantData || {
                title: 'BC Assistant',
                position: 'bottom-right',
                theme: 'light',
                welcomeMessage: 'Jak mogę pomóc?',
                apiEndpoint: '/wp-admin/admin-ajax.php',
                action: 'bc_assistant_send_message',
                nonce: ''
            };
            
            // Initialize component
            this.init();
        }
        
        /**
         * Initialize component
         */
        init() {
            // Render initial UI
            this.render();
            
            // Set up event listeners
            this.addEventListeners();
            
            // Add welcome message
            this.addMessage('assistant', this.getWelcomeMessage());
            
            // Make chat window draggable
            this.makeDraggable();
            
            // Set up voice capability
            this.setupVoiceCapability();
            
            // Log initialization in debug mode
            if (this.config.debug) {
                console.log('BC Assistant initialized with model:', this.config.model);
            }
        }
        
        /**
         * Get welcome message from config or global variable
         */
        getWelcomeMessage() {
            // Try from config
            if (this.config.welcomeMessage) {
                return this.config.welcomeMessage;
            }
            
            // Try from global variable
            if (typeof window.bcAssistantWelcomeMessage !== 'undefined') {
                return window.bcAssistantWelcomeMessage;
            }
            
            // Default fallback
            return 'Witaj! W czym mogę pomóc?';
        }
        
        /**
         * Set up voice functionality
         */
        setupVoiceCapability() {
            // Add voice button to the UI
            const inputContainer = this.shadowRoot.querySelector('.input-container');
            const voiceButton = document.createElement('button');
            voiceButton.className = 'voice-button';
            voiceButton.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"></path><path fill="currentColor" d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"></path></svg>';
            
            // Insert before send button
            const sendButton = inputContainer.querySelector('.send-button');
            inputContainer.insertBefore(voiceButton, sendButton);
            
            // Set up voice recording
            let mediaRecorder;
            let audioChunks = [];
            let isRecording = false;
            
            voiceButton.addEventListener('click', () => {
                if (isRecording) {
                    // Stop recording
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                        voiceButton.classList.remove('recording');
                        isRecording = false;
                    }
                } else {
                    // Start recording
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            isRecording = true;
                            voiceButton.classList.add('recording');
                            
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];
                            
                            mediaRecorder.ondataavailable = event => {
                                audioChunks.push(event.data);
                            };
                            
                            mediaRecorder.onstop = () => {
                                // Combine audio chunks into a blob
                                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                                
                                // Send audio to server
                                this.sendAudioToServer(audioBlob);
                                
                                // Release microphone
                                stream.getTracks().forEach(track => track.stop());
                            };
                            
                            // Start recording
                            mediaRecorder.start();
                            
                            // Auto-stop after 15 seconds if user forgets to stop
                            setTimeout(() => {
                                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                                    mediaRecorder.stop();
                                    voiceButton.classList.remove('recording');
                                    isRecording = false;
                                }
                            }, 15000);
                        })
                        .catch(error => {
                            console.error("Error accessing microphone:", error);
                            
                            // Show error message to user
                            this.addMessage('assistant', 'Nie udało się uzyskać dostępu do mikrofonu. Upewnij się, że masz włączony mikrofon i zezwoliłeś na dostęp do niego.');
                            
                            isRecording = false;
                            voiceButton.classList.remove('recording');
                        });
                }
            });
            
            // Also add a mode toggle at the top of messages
            const window = this.shadowRoot.querySelector('.window');
            const messagesContainer = this.shadowRoot.querySelector('.messages');
            
            const toggleContainer = document.createElement('div');
            toggleContainer.className = 'mode-toggle';
            toggleContainer.innerHTML = `
                <button class="mode-text active">Tekst</button>
                <button class="mode-voice">Głos</button>
            `;
            
            // Insert before messages
            if (window && messagesContainer) {
                window.insertBefore(toggleContainer, messagesContainer);
                
                // Add event listeners
                const textBtn = toggleContainer.querySelector('.mode-text');
                const voiceBtn = toggleContainer.querySelector('.mode-voice');
                
                textBtn.addEventListener('click', () => {
                    textBtn.classList.add('active');
                    voiceBtn.classList.remove('active');
                    this.shadowRoot.querySelector('.input-container').style.display = 'flex';
                    if (this.voiceModal) {
                        this.voiceModal.style.display = 'none';
                    }
                });
                
                voiceBtn.addEventListener('click', () => {
                    voiceBtn.classList.add('active');
                    textBtn.classList.remove('active');
                    this.shadowRoot.querySelector('.input-container').style.display = 'none';
                    this.showVoiceModal();
                });
            }
            
            // Create voice modal
            this.createVoiceModal();
        }
        
        /**
         * Create voice modal for better UX
         */
        createVoiceModal() {
            const window = this.shadowRoot.querySelector('.window');
            
            if (!window) return;
            
            const voiceModal = document.createElement('div');
            voiceModal.className = 'voice-modal';
            voiceModal.innerHTML = `
                <div class="voice-modal-content">
                    <h3>Nagrywanie głosu</h3>
                    <p>Kliknij przycisk, aby rozpocząć/zatrzymać nagrywanie</p>
                    <button class="voice-record-button">
                        <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3z"></path><path fill="currentColor" d="M17 11c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-3.08c3.39-.49 6-3.39 6-6.92h-2z"></path></svg>
                    </button>
                    <div class="voice-status">Gotowy do nagrywania</div>
                </div>
            `;
            
            window.appendChild(voiceModal);
            this.voiceModal = voiceModal;
            voiceModal.style.display = 'none';
            
            // Set up recording
            const recordButton = voiceModal.querySelector('.voice-record-button');
            const statusText = voiceModal.querySelector('.voice-status');
            
            let mediaRecorder;
            let audioChunks = [];
            let isRecording = false;
            
            recordButton.addEventListener('click', () => {
                if (isRecording) {
                    // Stop recording
                    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                        mediaRecorder.stop();
                        recordButton.classList.remove('recording');
                        statusText.textContent = 'Przetwarzanie...';
                        isRecording = false;
                    }
                } else {
                    // Start recording
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            isRecording = true;
                            recordButton.classList.add('recording');
                            statusText.textContent = 'Nagrywanie... (kliknij, aby zatrzymać)';
                            
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];
                            
                            mediaRecorder.ondataavailable = event => {
                                audioChunks.push(event.data);
                            };
                            
                            mediaRecorder.onstop = () => {
                                // Combine audio chunks into a blob
                                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                                
                                // Send audio to server
                                this.sendAudioToServer(audioBlob);
                                
                                // Release microphone
                                stream.getTracks().forEach(track => track.stop());
                                
                                // Reset UI
                                statusText.textContent = 'Gotowy do nagrywania';
                                
                                // Return to text mode
                                const textBtn = this.shadowRoot.querySelector('.mode-text');
                                const voiceBtn = this.shadowRoot.querySelector('.mode-voice');
                                
                                if (textBtn && voiceBtn) {
                                    textBtn.classList.add('active');
                                    voiceBtn.classList.remove('active');
                                }
                                
                                this.shadowRoot.querySelector('.input-container').style.display = 'flex';
                                voiceModal.style.display = 'none';
                            };
                            
                            // Start recording
                            mediaRecorder.start();
                            
                            // Auto-stop after 15 seconds if user forgets to stop
                            setTimeout(() => {
                                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                                    mediaRecorder.stop();
                                    recordButton.classList.remove('recording');
                                    isRecording = false;
                                }
                            }, 15000);
                        })
                        .catch(error => {
                            console.error("Error accessing microphone:", error);
                            
                            // Show error message
                            statusText.textContent = 'Błąd dostępu do mikrofonu';
                            
                            // Also add as message
                            this.addMessage('assistant', 'Nie udało się uzyskać dostępu do mikrofonu. Upewnij się, że masz włączony mikrofon i zezwoliłeś na dostęp do niego.');
                            
                            isRecording = false;
                            recordButton.classList.remove('recording');
                            
                            // Return to text mode
                            const textBtn = this.shadowRoot.querySelector('.mode-text');
                            const voiceBtn = this.shadowRoot.querySelector('.mode-voice');
                            
                            if (textBtn && voiceBtn) {
                                textBtn.classList.add('active');
                                voiceBtn.classList.remove('active');
                            }
                            
                            this.shadowRoot.querySelector('.input-container').style.display = 'flex';
                            voiceModal.style.display = 'none';
                        });
                }
            });
        }
        
        /**
         * Show voice modal
         */
        showVoiceModal() {
            if (this.voiceModal) {
                this.voiceModal.style.display = 'block';
            }
        }

        /**
         * Send audio to server for processing
         * @param {Blob} audioBlob Audio data to send
         */
        sendAudioToServer(audioBlob) {
            // Show typing indicator
            this.showTypingIndicator();
            
            // Create form data
            const formData = new FormData();
            formData.append('action', this.config.action);
            formData.append('audio', audioBlob);
            formData.append('thread_id', this.state.threadId);
            formData.append('nonce', this.config.nonce);
            formData.append('is_voice', 'true');
            
            // Get context information
            const context = this.getAttribute('context') || 'default';
            const procedureName = this.getAttribute('procedure') || '';
            
            if (context !== 'default') {
                formData.append('context', context);
            }
            
            if (procedureName) {
                formData.append('procedure_name', procedureName);
            }
            
            // Update state
            this.state.isTyping = true;
            
            // Send request
            fetch(this.config.apiEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Hide typing indicator
                this.hideTypingIndicator();
                
                // Update state
                this.state.isTyping = false;
                
                // Handle response
                if (data.success) {
                    // Add transcribed user message if available
                    if (data.data.transcription) {
                        this.addMessage('user', data.data.transcription);
                    }
                    
                    // Add assistant response
                    this.addMessage('assistant', data.data.message);
                    
                    // Store thread ID if provided
                    if (data.data.thread_id) {
                        this.state.threadId = data.data.thread_id;
                        localStorage.setItem('bc_assistant_thread_id', this.state.threadId);
                    }
                } else {
                    // Show error message
                    this.addMessage('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie później.');
                    
                    // Log error
                    if (this.config.debug) {
                        console.error('BC Assistant API Error:', data);
                    }
                }
            })
            .catch(error => {
                // Hide typing indicator
                this.hideTypingIndicator();
                
                // Update state
                this.state.isTyping = false;
                
                // Show error message
                this.addMessage('assistant', 'Przepraszam, wystąpił błąd połączenia. Spróbuj ponownie później.');
                
                // Log error
                if (this.config.debug) {
                    console.error('BC Assistant Error:', error);
                }
            });
        }
        
        /**
         * Force scroll messages to bottom
         * @param {Element} messagesContainer - The container element to scroll
         */
        forceScroll(messagesContainer) {
            if (!messagesContainer) return;
            
            try {
                // Method 1: Simple scrollTop
                messagesContainer.scrollTop = messagesContainer.scrollHeight + 1000; // Add buffer for safety
                
                // Method 2: Try scrollIntoView on the last child
                const children = messagesContainer.children;
                if (children && children.length > 0) {
                    children[children.length - 1].scrollIntoView({ behavior: 'smooth', block: 'end' });
                }
                
                // Method 3: Brute force approach - try multiple times with increasing delays
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight + 1000;
                }, 50);
                
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight + 1000;
                }, 200);
                
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight + 1000;
                    
                    // Additional check for last child
                    const children = messagesContainer.children;
                    if (children && children.length > 0) {
                        children[children.length - 1].scrollIntoView({ behavior: 'auto', block: 'end' });
                    }
                }, 500);
            } catch (error) {
                console.error('Error in forceScroll:', error);
            }
        }
        
        /**
         * Render component
         */
        render() {
            // Set position as attribute for CSS
            this.setAttribute('position', this.config.position || 'bottom-right');
            
            // Set theme as attribute for CSS
            this.setAttribute('theme', this.config.theme || 'light');
            
            // Inject styles and HTML template
            this.shadowRoot.innerHTML = `
                <style>
                    /* Base container */
                    :host {
                        position: fixed;
                        z-index: 999999;
                        display: block;
                        box-sizing: border-box;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                        line-height: 1.5;
                        font-size: 14px;
                    }
                    
                    /* Position variations */
                    :host([position="bottom-right"]) {
                        right: 20px;
                        bottom: 20px;
                    }
                    
                    :host([position="bottom-left"]) {
                        left: 20px;
                        bottom: 20px;
                    }
                    
                    :host([position="top-right"]) {
                        right: 20px;
                        top: 20px;
                    }
                    
                    :host([position="top-left"]) {
                        left: 20px;
                        top: 20px;
                    }
                    
                    /* Wrapper */
                    .wrapper {
                        position: relative;
                    }
                    
                    /* Chat bubble */
                    .bubble {
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        background-color: #A67C52;
                        color: white;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                        transition: transform 0.3s ease;
                        font-size: 24px;
                    }
                    
                    .bubble:hover {
                        transform: scale(1.1);
                    }
                    
                    /* Chat window */
                    .window {
                        position: absolute;
                        bottom: 70px;
                        right: 0;
                        width: 350px;
                        height: 500px;
                        border-radius: 10px;
                        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
                        background-color: #fff;
                        display: none;
                        flex-direction: column;
                        overflow: hidden;
                        z-index: 99999;
                    }
                    
                    :host([position="bottom-left"]) .window {
                        right: auto;
                        left: 0;
                    }
                    
                    :host([position="top-right"]) .window {
                        bottom: auto;
                        top: 70px;
                    }
                    
                    :host([position="top-left"]) .window {
                        bottom: auto;
                        top: 70px;
                        right: auto;
                        left: 0;
                    }
                    
                    /* Window header */
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 15px;
                        background-color: #A67C52;
                        color: white;
                        border-top-left-radius: 10px;
                        border-top-right-radius: 10px;
                        cursor: move;
                        user-select: none;
                    }
                    
                    .title {
                        font-weight: bold;
                        font-size: 16px;
                    }
                    
                    .controls {
                        display: flex;
                    }
                    
                    .control-button {
                        background: none;
                        border: none;
                        color: white;
                        cursor: pointer;
                        margin-left: 10px;
                        font-size: 16px;
                        padding: 0;
                        line-height: 1;
                    }
                    
                    /* Messages container - improved scrolling */
                    .messages {
                        flex: 1;
                        height: calc(100% - 145px); /* Adjusted for mode toggle */
                        overflow-y: auto !important;
                        overflow-x: hidden;
                        padding: 15px;
                        padding-bottom: 30px;
                        display: flex;
                        flex-direction: column;
                        background-color: #f5f5f5;
                        scroll-behavior: smooth;
                        position: relative;
                        overscroll-behavior: contain;
                        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
                    }
                    
                    /* Mode toggle */
                    .mode-toggle {
                        display: flex;
                        justify-content: center;
                        padding: 10px;
                        background-color: #f5f5f5;
                        border-bottom: 1px solid #eee;
                    }
                    
                    .mode-toggle button {
                        background-color: #fff;
                        border: 1px solid #ddd;
                        padding: 5px 12px;
                        margin: 0 5px;
                        border-radius: 15px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }
                    
                    .mode-toggle button.active {
                        background-color: #A67C52;
                        color: white;
                        border-color: #A67C52;
                    }
                    
                    /* Voice modal */
                    .voice-modal {
                        position: absolute;
                        bottom: 0;
                        left: 0;
                        right: 0;
                        background-color: white;
                        border-top: 1px solid #eee;
                        padding: 20px;
                        text-align: center;
                        z-index: 20;
                        height: calc(100% - 130px);
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    .voice-modal h3 {
                        margin-top: 0;
                        color: #333;
                    }
                    
                    .voice-record-button {
                        width: 80px;
                        height: 80px;
                        border-radius: 50%;
                        background-color: #A67C52;
                        color: white;
                        border: none;
                        margin: 20px 0;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }
                    
                    .voice-record-button.recording {
                        background-color: #e74c3c;
                        animation: pulse 1.5s infinite;
                    }
                    
                    .voice-status {
                        color: #666;
                        margin-top: 15px;
                    }
                    
                    /* Message styling */
                    .message {
                        margin-bottom: 15px;
                        max-width: 80%;
                        display: flex;
                        flex-direction: column;
                        position: relative;
                        z-index: 1;
                        word-break: break-word;
                    }
                    
                    .message.user {
                        align-self: flex-end;
                    }
                    
                    .message.assistant {
                        align-self: flex-start;
                    }
                    
                    .message-content {
                        padding: 10px 15px;
                        border-radius: 18px;
                        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                        word-wrap: break-word;
                        line-height: 1.4;
                    }
                    
                    .message.user .message-content {
                        background-color: #A67C52;
                        color: white;
                        border-bottom-right-radius: 5px;
                    }
                    
                    .message.assistant .message-content {
                        background-color: white;
                        color: #333;
                        border-bottom-left-radius: 5px;
                    }
                    
                    .message-timestamp {
                        font-size: 12px;
                        color: #888;
                        margin-top: 5px;
                        text-align: right;
                    }
                    
                    /* Input container */
                    .input-container {
                        padding: 10px;
                        border-top: 1px solid #eee;
                        background-color: white;
                        display: flex;
                        width: 100%;
                        box-sizing: border-box;
                        position: relative;
                        z-index: 10;
                        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
                    }
                    
                    .input {
                        flex: 1;
                        border: 1px solid #ddd;
                        border-radius: 20px;
                        padding: 10px 15px;
                        font-size: 14px;
                        resize: none;
                        outline: none;
                        min-height: 40px;
                        max-height: 80px;
                        overflow-y: auto;
                        font-family: inherit;
                        margin-right: 10px;
                        box-sizing: border-box;
                    }
                    
                    .input:focus {
                        border-color: #A67C52;
                        box-shadow: 0 0 0 2px rgba(166, 124, 82, 0.1);
                    }
                    
                    .send-button {
                        background-color: #A67C52;
                        color: white;
                        border: none;
                        border-radius: 50%;
                        width: 40px;
                        height: 40px;
                        margin-left: 10px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    /* Voice button styling - improved size */
                    .voice-button {
                        background-color: #A67C52;
                        color: white;
                        border: none;
                        border-radius: 50%;
                        width: 46px;
                        height: 46px;
                        margin-left: 10px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: background-color 0.3s ease;
                    }
                    
                    .voice-button svg {
                        width: 28px;
                        height: 28px;
                    }
                    
                    .voice-button:hover {
                        background-color: #8a6643;
                    }
                    
                    .voice-button.recording {
                        background-color: #e74c3c;
                        animation: pulse 1.5s infinite;
                    }
                    
                    @keyframes pulse {
                        0% {
                            transform: scale(1);
                        }
                        50% {
                            transform: scale(1.1);
                        }
                        100% {
                            transform: scale(1);
                        }
                    }
                    
                    /* Message content formatting */
                    .message-content a {
                        color: inherit;
                        text-decoration: underline;
                    }
                    
                    .message-content code {
                        background-color: rgba(0, 0, 0, 0.05);
                        padding: 2px 4px;
                        border-radius: 3px;
                        font-family: monospace;
                    }
                    
                    .message-content pre {
                        background-color: #1E1E1E;
                        padding: 10px;
                        border-radius: 5px;
                        overflow-x: auto;
                        margin: 10px 0;
                    }
                    
                    .message-content pre code {
                        background-color: transparent;
                        color: #FFFFFF;
                        padding: 0;
                    }
                    
                    /* Typing indicator */
                    .typing-indicator {
                        display: flex;
                        align-items: center;
                        margin-bottom: 15px;
                        align-self: flex-start;
                    }
                    
                    .typing-indicator .message-content {
                        padding: 8px 15px;
                    }
                    
                    .typing-dots {
                        display: flex;
                    }
                    
                    .typing-dot {
                        width: 8px;
                        height: 8px;
                        border-radius: 50%;
                        background-color: #A67C52;
                        margin: 0 2px;
                        animation: typing 1.4s infinite ease-in-out both;
                    }
                    
                    .typing-dot:nth-child(2) {
                        animation-delay: 0.2s;
                    }
                    
                    .typing-dot:nth-child(3) {
                        animation-delay: 0.4s;
                    }
                    
                    @keyframes typing {
                        0%, 80%, 100% {
                            transform: scale(0.75);
                            opacity: 0.2;
                        }
                        50% {
                            transform: scale(1);
                            opacity: 1;
                        }
                    }
                    
                    /* Dark theme */
                    :host([theme="dark"]) .window {
                        background-color: #222;
                        color: #fff;
                    }
                    
                    :host([theme="dark"]) .messages {
                        background-color: #333;
                    }
                    
                    :host([theme="dark"]) .message.assistant .message-content {
                        background-color: #444;
                        color: #fff;
                    }
                    
                    :host([theme="dark"]) .input-container {
                        background-color: #222;
                        border-top-color: #444;
                    }
                    
                    :host([theme="dark"]) .input {
                        background-color: #333;
                        border-color: #444;
                        color: #fff;
                    }
                    
                    :host([theme="dark"]) .mode-toggle {
                        background-color: #333;
                        border-bottom-color: #444;
                    }
                    
                    :host([theme="dark"]) .mode-toggle button:not(.active) {
                        background-color: #444;
                        color: #ddd;
                        border-color: #555;
                    }
                    
                    :host([theme="dark"]) .voice-modal {
                        background-color: #222;
                        border-top-color: #444;
                    }
                    
                    :host([theme="dark"]) .voice-modal h3 {
                        color: #ddd;
                    }
                    
                    :host([theme="dark"]) .voice-status {
                        color: #aaa;
                    }
                    
                    @media (max-width: 767px) {
                        :host {
                            position: fixed !important;
                            bottom: 140px !important;
                            z-index: 999999 !important;
                        }
                        
                        .bubble {
                            width: 50px;
                            height: 50px;
                            font-size: 20px;
                            position: fixed !important;
                        }
                        
                        .window {
                            width: 85vw;
                            max-width: 350px;
                            height: 70vh;
                            position: fixed !important;
                        }
                        
                        .message {
                            max-width: 90%;
                        }
                    }
                </style>
                
                <div class="wrapper">
                    <div class="bubble">
                        ${this.getIconHTML()}
                    </div>
                    
                    <div class="window">
                        <div class="header">
                            <div class="title">${this.config.title || 'BC Assistant'}</div>
                            <div class="controls">
                                <button class="control-button minimize-button">−</button>
                                <button class="control-button close-button">×</button>
                            </div>
                        </div>
                        
                        <div class="messages"></div>
                        
                        <div class="input-container">
                            <textarea class="input" placeholder="Wpisz swoje pytanie..."></textarea>
                            <button class="send-button">→</button>
                        </div>
                    </div>
                </div>
            `;
        }
        
        /**
         * Get icon HTML based on configuration
         */
        getIconHTML() {
            const iconType = this.config.bubble_icon || 'chat';
            
            switch (iconType) {
                case 'question':
                    return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92c-.5.51-.86.97-1.04 1.69-.08.32-.13.68-.13 1.14h-2v-.5c0-.46.08-.9.22-1.31.2-.58.53-1.1.95-1.52l1.24-1.26c.46-.44.68-1.1.55-1.8-.13-.72-.69-1.33-1.39-1.53-1.11-.31-2.14.32-2.47 1.27-.12.35-.47.56-.83.56-.5 0-.8-.4-.66-.85.3-1.63 1.87-2.87 3.66-2.87 1.78 0 3.27 1.24 3.6 2.89.26 1.32-.29 2.6-1.3 3.37z"></path></svg>';
                case 'info':
                    return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg>';
                case 'robot':
                    return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M20 9V7c0-1.1-.9-2-2-2h-3c0-1.66-1.34-3-3-3S9 3.34 9 5H6c-1.1 0-2 .9-2 2v2c-1.66 0-3 1.34-3 3s1.34 3 3 3v4c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-4c1.66 0 3-1.34 3-3s-1.34-3-3-3zM7.5 11.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5S9.83 13 9 13s-1.5-.67-1.5-1.5zM16 17H8v-2h8v2zm-.5-4.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"></path></svg>';
                case 'user':
                    return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>';
                case 'chat':
                default:
                    return '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"></path></svg>';
            }
        }
        
        /**
         * Make chat window draggable
         */
        makeDraggable() {
            const chatWindow = this.shadowRoot.querySelector('.window');
            const header = this.shadowRoot.querySelector('.header');
            
            if (!chatWindow || !header) return;
            
            let isDragging = false;
            let offsetX, offsetY;
            
            // Make window header draggable
            header.addEventListener('mousedown', startDrag);
            header.addEventListener('touchstart', handleTouchStart);
            
            // Add visual cue
            header.style.cursor = 'move';
            
            // Event handlers
            const self = this; // Store reference to the class instance
            
            function startDrag(e) {
                // Don't start dragging if we're clicking on control buttons
                if (e.target.closest('.control-button')) {
                    return;
                }
                
                isDragging = true;
                offsetX = e.clientX - chatWindow.getBoundingClientRect().left;
                offsetY = e.clientY - chatWindow.getBoundingClientRect().top;
                
                // Add event listeners
                document.addEventListener('mousemove', onDrag);
                document.addEventListener('mouseup', stopDrag);
                
                // Prevent text selection
                document.body.style.userSelect = 'none';
                chatWindow.style.transition = 'none';
            }
            
            function handleTouchStart(e) {
                // Don't start dragging if we're touching control buttons
                if (e.target.closest('.control-button')) {
                    return;
                }
                
                const touch = e.touches[0];
                isDragging = true;
                offsetX = touch.clientX - chatWindow.getBoundingClientRect().left;
                offsetY = touch.clientY - chatWindow.getBoundingClientRect().top;
                
                // Add event listeners
                document.addEventListener('touchmove', handleTouchMove);
                document.addEventListener('touchend', handleTouchEnd);
                
                // Prevent text selection
                document.body.style.userSelect = 'none';
                chatWindow.style.transition = 'none';
            }
            
            function onDrag(e) {
                if (!isDragging) return;
                
                const x = e.clientX - offsetX;
                const y = e.clientY - offsetY;
                
                // Stay within viewport boundaries
                const maxX = window.innerWidth - chatWindow.offsetWidth;
                const maxY = window.innerHeight - chatWindow.offsetHeight;
                
                const boundedX = Math.max(0, Math.min(x, maxX));
                const boundedY = Math.max(0, Math.min(y, maxY));
                
                // Set new position
                chatWindow.style.left = boundedX + 'px';
                chatWindow.style.top = boundedY + 'px';
                chatWindow.style.right = 'auto';
                chatWindow.style.bottom = 'auto';
            }
            
            function handleTouchMove(e) {
                if (!isDragging) return;
                
                const touch = e.touches[0];
                const x = touch.clientX - offsetX;
                const y = touch.clientY - offsetY;
                
                // Stay within viewport boundaries
                const maxX = window.innerWidth - chatWindow.offsetWidth;
                const maxY = window.innerHeight - chatWindow.offsetHeight;
                
                const boundedX = Math.max(0, Math.min(x, maxX));
                const boundedY = Math.max(0, Math.min(y, maxY));
                
                // Set new position
                chatWindow.style.left = boundedX + 'px';
                chatWindow.style.top = boundedY + 'px';
                chatWindow.style.right = 'auto';
                chatWindow.style.bottom = 'auto';
                
                // Prevent page scrolling
                e.preventDefault();
            }
            
            function stopDrag() {
                isDragging = false;
                
                // Remove event listeners
                document.removeEventListener('mousemove', onDrag);
                document.removeEventListener('mouseup', stopDrag);
                
                // Restore text selection
                document.body.style.userSelect = '';
                chatWindow.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            }
            
            function handleTouchEnd() {
                isDragging = false;
                
                // Remove event listeners
                document.removeEventListener('touchmove', handleTouchMove);
                document.removeEventListener('touchend', handleTouchEnd);
                
                // Restore text selection
                document.body.style.userSelect = '';
                chatWindow.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            }
        }
        
        /**
         * Toggle chat window
         */
        toggleWindow() {
            if (this.state.isOpen) {
                this.closeWindow();
            } else {
                this.openWindow();
            }
        }
        
        /**
         * Open chat window
         */
        openWindow() {
            const window = this.shadowRoot.querySelector('.window');
            const input = this.shadowRoot.querySelector('.input');
            const messagesContainer = this.shadowRoot.querySelector('.messages');
            
            if (!window) return;
            
            // Update state
            this.state.isOpen = true;
            
            // Show window with animation
            window.style.display = 'flex';
            window.style.opacity = '0';
            window.style.transform = 'translateY(10px)';
            
            // Animate opening
            setTimeout(() => {
                window.style.opacity = '1';
                window.style.transform = 'translateY(0)';
            }, 10);
            
            // Focus input field
            if (input) {
                setTimeout(() => {
                    input.focus();
                }, 300);
            }
            
            // Force scroll to bottom
            if (messagesContainer) {
                setTimeout(() => {
                    this.forceScroll(messagesContainer);
                }, 100);
            }
        }
        
        /**
         * Close chat window
         */
        closeWindow() {
            const window = this.shadowRoot.querySelector('.window');
            
            if (!window) return;
            
            // Update state
            this.state.isOpen = false;
            
            // Animate closing
            window.style.opacity = '0';
            window.style.transform = 'translateY(10px)';
            
            // Hide after animation completes
            setTimeout(() => {
                window.style.display = 'none';
            }, 300);
        }
        
        /**
         * Send message to API
         */
        async sendMessage() {
            const input = this.shadowRoot.querySelector('.input');
            
            if (!input) return;
            
            // Get message text
            const messageText = input.value.trim();
            
            // Skip empty messages or if already sending
            if (!messageText || this.state.isTyping) return;
            
            // Update state
            this.state.isTyping = true;
            
            // Add user message to chat
            this.addMessage('user', messageText);
            
            // Clear input field
            input.value = '';
            input.style.height = 'auto';
            
            // Show typing indicator
            this.showTypingIndicator();
            
            try {
                // Create form data
                const formData = new FormData();
                formData.append('action', this.config.action);
                formData.append('message', messageText);
                formData.append('thread_id', this.state.threadId);
                formData.append('nonce', this.config.nonce);
                
                // Add page context if needed
                const context = this.getAttribute('context') || 'default';
                const procedureName = this.getAttribute('procedure') || '';
                
                if (context !== 'default') {
                    formData.append('context', context);
                }
                
                if (procedureName) {
                    formData.append('procedure_name', procedureName);
                }
                
                // Send request
                const response = await fetch(this.config.apiEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                
                // Parse response
                const data = await response.json();
                
                // Hide typing indicator
                this.hideTypingIndicator();
                
                // Update state
                this.state.isTyping = false;
                
                // Handle response
                if (data.success) {
                    // Add assistant response
                    this.addMessage('assistant', data.data.message);
                    
                    // Store thread ID if provided
                    if (data.data.thread_id) {
                        this.state.threadId = data.data.thread_id;
                        localStorage.setItem('bc_assistant_thread_id', this.state.threadId);
                    }
                } else {
                    // Show error message
                    this.addMessage('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie później.');
                    
                    // Log error
                    if (this.config.debug) {
                        console.error('BC Assistant API Error:', data);
                    }
                }
            } catch (error) {
                // Hide typing indicator
                this.hideTypingIndicator();
                
                // Update state
                this.state.isTyping = false;
                
                // Show error message
                this.addMessage('assistant', 'Przepraszam, wystąpił błąd połączenia. Spróbuj ponownie później.');
                
                // Log error
                if (this.config.debug) {
                    console.error('BC Assistant Error:', error);
                }
            }
        }
        
        /**
         * Add message to chat
         */
        addMessage(role, content) {
            const messagesContainer = this.shadowRoot.querySelector('.messages');
            
            if (!messagesContainer) {
                console.error('Messages container not found');
                return;
            }
            
            // Create message element
            const messageElem = document.createElement('div');
            messageElem.className = `message ${role}`;
            
            // Create message content element
            const contentElem = document.createElement('div');
            contentElem.className = 'message-content';
            contentElem.innerHTML = this.formatMessage(content);
            
            // Create timestamp element
            const timestampElem = document.createElement('div');
            timestampElem.className = 'message-timestamp';
            timestampElem.textContent = this.formatTime(new Date());
            
            // Assemble message
            messageElem.appendChild(contentElem);
            messageElem.appendChild(timestampElem);
            
            // Add to container
            messagesContainer.appendChild(messageElem);
            
            // Store in state
            this.state.messages.push({
                role,
                content,
                timestamp: new Date()
            });
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        /**
         * Format message with Markdown-like syntax
         */
        formatMessage(text) {
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
        
        /**
         * Format time
         */
        formatTime(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        /**
         * Show typing indicator
         */
        showTypingIndicator() {
            const messagesContainer = this.shadowRoot.querySelector('.messages');
            
            if (!messagesContainer) return;
            
            // Create indicator element
            const indicatorElem = document.createElement('div');
            indicatorElem.className = 'typing-indicator';
            
            // Create content
            indicatorElem.innerHTML = `
                <div class="message-content">
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>
            `;
            
            // Add to container
            messagesContainer.appendChild(indicatorElem);
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        /**
         * Hide typing indicator
         */
        hideTypingIndicator() {
            const indicator = this.shadowRoot.querySelector('.typing-indicator');
            
            if (indicator) {
                indicator.remove();
            }
        }
        
        /**
         * Scroll messages to bottom
         */
        scrollToBottom() {
            const messagesContainer = this.shadowRoot.querySelector('.messages');
            
            if (!messagesContainer) {
                console.error('Messages container not found');
                return;
            }
            
            try {
                // Multiple aggressive scrolling techniques for compatibility
                
                // Method 1: Direct scrollTop
                messagesContainer.scrollTop = messagesContainer.scrollHeight + 2000; // Extra buffer
                
                // Method 2: Use scrollIntoView for the last message
                const messages = messagesContainer.querySelectorAll('.message');
                if (messages.length > 0) {
                    const lastMessage = messages[messages.length - 1];
                    lastMessage.scrollIntoView({ behavior: 'auto', block: 'end' });
                }
                
                // Method 3: Schedule delayed scrolls for when content renders
                setTimeout(() => {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight + 2000;
                }, 100);
                
                setTimeout(() => {
                    // Try again in case of dynamic content
                    messagesContainer.scrollTop = messagesContainer.scrollHeight + 2000;
                    
                    // Also try scrollIntoView again
                    const messages = messagesContainer.querySelectorAll('.message');
                    if (messages.length > 0) {
                        messages[messages.length - 1].scrollIntoView({ behavior: 'auto', block: 'end' });
                    }
                }, 300);
            } catch (error) {
                console.error('Error scrolling to bottom:', error);
            }
        }
        
        /**
         * Adjust layout based on screen size
         */
        adjustLayout() {
            const isMobile = window.innerWidth < 768;
            
            if (isMobile) {
                this.setAttribute('mobile', 'true');
            } else {
                this.removeAttribute('mobile');
            }
        }
    }
    
    // Register the custom element
    customElements.define('bc-assistant-widget', BCAssistantWidget);
    
    // Create element if not already exists
    document.addEventListener('DOMContentLoaded', () => {
        // Check if element already exists (might be added in templates)
        if (!document.querySelector('bc-assistant-widget')) {
            // Create element
            const widget = document.createElement('bc-assistant-widget');
            
            // Get page context
            const pageUrl = window.location.href;
            let context = 'default';
            let procedure = '';
            
            // Detect context based on URL
            if (pageUrl.includes('/laseroterapia/')) {
                context = 'procedure';
                procedure = 'Laseroterapia';
            } else if (pageUrl.includes('/kosmetologia/')) {
                context = 'procedure';
                procedure = 'Kosmetologia';
            } else if (pageUrl.includes('/medycyna-estetyczna/')) {
                context = 'procedure';
                procedure = 'Medycyna estetyczna';
            } else if (pageUrl.includes('/przeciwwskazania/')) {
                context = 'contraindications';
            } else if (pageUrl.includes('/cennik/')) {
                context = 'prices';
            }
            
            // Set attributes
            widget.setAttribute('context', context);
            if (procedure) {
                widget.setAttribute('procedure', procedure);
            }
            
            // Add to body
            document.body.appendChild(widget);
        }
    });
}

// Initialize Shadow DOM implementation if not already initialized elsewhere
if (typeof window !== 'undefined') {
    if (!window.bcAssistantShadowInitialized) {
        initShadowDOM();
        window.bcAssistantShadowInitialized = true;
    }
}