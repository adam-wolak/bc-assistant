/**
 * BC Assistant - Modern Implementation with Shadow DOM
 * Version 2.0.0
 */

class BCAssistantWidget extends HTMLElement {
    constructor() {
        super();
        
        // Create shadow root for style encapsulation
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
            welcomeMessage: 'How can I help you?',
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
        return 'Hello! How can I help you?';
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
                /* Base styles */
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
                
                /* Container */
                .container {
                    position: relative;
                }
                
                /* Bubble button */
                .bubble {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    background-color: #A67C52;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                    transition: transform 0.3s ease;
                    color: white;
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
                    background-color: #fff;
                    border-radius: 10px;
                    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
                    display: none;
                    flex-direction: column;
                    overflow: hidden;
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
                
                /* Chat header */
                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 15px;
                    background-color: #A67C52;
                    color: #fff;
                    border-top-left-radius: 10px;
                    border-top-right-radius: 10px;
                }
                
                .title {
                    font-weight: bold;
                    font-size: 16px;
                }
                
                .controls {
                    display: flex;
                    align-items: center;
                }
                
                .minimize,
                .close {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 16px;
                    cursor: pointer;
                    padding: 0;
                    margin-left: 10px;
                    line-height: 1;
                }
                
                /* Messages container */
                .messages {
                    flex: 1;
                    overflow-y: auto;
                    padding: 15px;
                    background-color: #f5f5f5;
                    display: flex;
                    flex-direction: column;
                    scroll-behavior: smooth;
                }
                
                /* Message styling */
                .message {
                    margin-bottom: 15px;
                    max-width: 80%;
                    display: flex;
                    flex-direction: column;
                }
                
                .message-user {
                    align-self: flex-end;
                }
                
                .message-assistant {
                    align-self: flex-start;
                }
                
                .message-content {
                    padding: 10px 15px;
                    border-radius: 18px;
                    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
                    word-wrap: break-word;
                    line-height: 1.4;
                }
                
                .message-user .message-content {
                    background-color: #A67C52;
                    color: #fff;
                    border-bottom-right-radius: 5px;
                }
                
                .message-assistant .message-content {
                    background-color: #fff;
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
                    display: flex;
                    padding: 10px;
                    border-top: 1px solid #eee;
                    background-color: #fff;
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
                    max-height: 100px;
                    overflow-y: auto;
                    font-family: inherit;
                }
                
                .input:focus {
                    border-color: #A67C52;
                    box-shadow: 0 0 0 2px rgba(166, 124, 82, 0.1);
                }
                
                .send {
                    background-color: #A67C52;
                    color: #fff;
                    border: none;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    margin-left: 10px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transition: background-color 0.3s;
                }
                
                .send:hover {
                    background-color: #8D6848;
                }
                
                /* Typing indicator */
                .typing-indicator {
                    margin-bottom: 10px;
                }
                
                .typing-dots {
                    display: flex;
                    align-items: center;
                }
                
                .typing-dot {
                    width: 8px;
                    height: 8px;
                    margin: 0 2px;
                    background-color: #A67C52;
                    border-radius: 50%;
                    display: inline-block;
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
                
                /* Dark theme */
                :host([theme="dark"]) .window {
                    background-color: #222;
                    color: #fff;
                }
                
                :host([theme="dark"]) .messages {
                    background-color: #333;
                }
                
                :host([theme="dark"]) .message-assistant .message-content {
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
                
                /* Mobile styles */
                @media (max-width: 767px) {
                    .bubble {
                        width: 50px;
                        height: 50px;
                        font-size: 20px;
                    }
                    
                    .window {
                        width: 85vw;
                        max-width: 350px;
                        height: 70vh;
                        max-height: 500px;
                    }
                    
                    :host {
                        bottom: 140px !important;
                    }
                    
                    .message {
                        max-width: 90%;
                    }
                    
                    .input {
                        font-size: 16px;
                        min-height: 44px;
                    }
                    
                    .send {
                        width: 44px;
                        height: 44px;
                    }
                }
                
                /* Portrait orientation specific fixes */
                @media (max-width: 767px) and (orientation: portrait) {
                    :host {
                        bottom: 140px !important;
                    }
                    
                    .window {
                        height: 60vh;
                    }
                }
                
                /* Landscape orientation specific fixes */
                @media (max-width: 767px) and (orientation: landscape) {
                    :host {
                        bottom: 100px !important;
                    }
                    
                    .window {
                        height: 80vh;
                        bottom: 60px;
                    }
                }
            </style>
            
            <div class="container">
                <div class="bubble">
                    <i class="icon">
                        <!-- Using Font Awesome classes instead of <i> to avoid Font Awesome dependencies -->
                        ${this.getIconHTML()}
                    </i>
                </div>
                
                <div class="window">
                    <div class="header">
                        <div class="title">${this.config.title || 'BC Assistant'}</div>
                        <div class="controls">
                            <button class="minimize" title="Minimize">−</button>
                            <button class="close" title="Close">×</button>
                        </div>
                    </div>
                    
                    <div class="messages"></div>
                    
                    <div class="input-container">
                        <textarea class="input" placeholder="Type your message..."></textarea>
                        <button class="send" title="Send">→</button>
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
                return '?';
            case 'info':
                return 'i';
            case 'robot':
                return '🤖';
            case 'user':
                return '👨‍⚕️';
            case 'chat':
            default:
                return '💬';
        }
    }
    
    /**
     * Add event listeners
     */
    addEventListeners() {
        // Get elements
        const bubble = this.shadowRoot.querySelector('.bubble');
        const closeBtn = this.shadowRoot.querySelector('.close');
        const minimizeBtn = this.shadowRoot.querySelector('.minimize');
        const sendBtn = this.shadowRoot.querySelector('.send');
        const input = this.shadowRoot.querySelector('.input');
        
        // Toggle chat window
        if (bubble) {
            bubble.addEventListener('click', () => this.toggleWindow());
        }
        
        // Close window
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.closeWindow());
        }
        
        // Minimize window
        if (minimizeBtn) {
            minimizeBtn.addEventListener('click', () => this.closeWindow());
        }
        
        // Send message
        if (sendBtn) {
            sendBtn.addEventListener('click', () => this.sendMessage());
        }

// Handle Enter key in input field
        if (input) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            
            // Auto-resize input field
            input.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        }
        
        // Window resize event
        window.addEventListener('resize', () => {
            this.adjustLayout();
        });
    }
    
    /**
     * Toggle chat window
     */
    toggleWindow() {
        const window = this.shadowRoot.querySelector('.window');
        
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
            window.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        }, 10);
        
        // Focus input field
        if (input) {
            setTimeout(() => {
                input.focus();
            }, 300);
        }
        
        // Scroll to latest messages
        this.scrollToBottom();
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
                this.addMessage('assistant', 'Sorry, an error occurred. Please try again later.');
                
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
            this.addMessage('assistant', 'Sorry, a connection error occurred. Please try again later.');
            
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
        
        if (!messagesContainer) return;
        
        // Create message element
        const messageElem = document.createElement('div');
        messageElem.className = `message message-${role}`;
        
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
        indicatorElem.className = 'message message-assistant typing-indicator';
        
        // Create content
        indicatorElem.innerHTML = `
            <div class="message-content">
                <div class="typing-dots">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
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
        
        if (!messagesContainer) return;
        
        // Immediate scroll
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        
        // Delayed scroll for images and dynamic content
        setTimeout(() => {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }, 100);
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

// Initialize widget when DOM is loaded
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