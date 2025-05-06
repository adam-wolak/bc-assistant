/**
 * BC Assistant - Mobile & Cross-Browser Fix
 * Self-contained solution that doesn't depend on other plugins
 */

(function($) {
    "use strict";
    
    // Store a single instance reference
    let bcAssistantInstance = null;
    
    // Configuration object - will be populated from WordPress data
    const bcConfig = window.bcAssistantData || {
        model: "gpt-4o",
        apiEndpoint: "/wp-admin/admin-ajax.php",
        action: "bc_assistant_send_message",
        position: "bottom-right",
        title: "Asystent BC",
        initialMessage: "Witaj! W czym mogę pomóc?",
        nonce: ""
    };
    
    // Main BC Assistant class
    class BCAssistant {
        constructor(config) {
            // Store configuration
            this.config = config;
            this.messages = [];
            this.isOpen = false;
            this.isTyping = false;
            this.threadId = localStorage.getItem('bc_assistant_thread_id') || '';
            this.isMobile = this.checkIfMobile();
            
            // Initialize only once
            this.init();
            
            // Log initialization for debugging
            if (config.debug) {
                console.log("BC Assistant initialized with model:", this.config.model);
                console.log("Device type:", this.isMobile ? "Mobile" : "Desktop");
            }
        }
        
        // Check if current device is mobile
        checkIfMobile() {
            return window.innerWidth < 768 || 
                   /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
        
        // Initialize the assistant
        init() {
            this.createDOM();
            this.setupEvents();
            
            // Get welcome message from config or global variable or default
            let welcomeMessage = this.config.welcomeMessage;
            
            // If not in config, try global variable
            if (!welcomeMessage && typeof window.bcAssistantWelcomeMessage !== 'undefined') {
                welcomeMessage = window.bcAssistantWelcomeMessage;
            }
            
            // If still not found, use default
            if (!welcomeMessage) {
                welcomeMessage = 'Witaj! W czym mogę pomóc?';
            }
            
            // Add welcome message
            this.addMessage('assistant', welcomeMessage);
            
            // Apply fixes for display issues
            this.fixDisplay();
            
            // Set up periodic visibility check
            setInterval(() => this.ensureVisibility(), 2000);
        }
        
        // Create all DOM elements
        createDOM() {
            // Create wrapper
            this.wrapper = document.createElement('div');
            this.wrapper.className = 'bc-assistant-wrapper';
            this.wrapper.setAttribute('data-position', this.config.position);
            
            // Create main container
            this.container = document.createElement('div');
            this.container.className = 'bc-assistant-container';
            
            // Add theme class if specified
            if (this.config.theme) {
                this.container.classList.add('bc-assistant-' + this.config.theme);
            }
            
            // Create chat bubble
            this.bubble = document.createElement('div');
            this.bubble.className = 'bc-assistant-bubble';
            this.bubble.innerHTML = '<i class="fas fa-comments"></i>';
            
            // Create chat window
            this.window = document.createElement('div');
            this.window.className = 'bc-assistant-window';
            
            // Create window header
            const header = document.createElement('div');
            header.className = 'bc-assistant-header';
            header.innerHTML = `
                <div class="bc-assistant-title">${this.config.title}</div>
                <div class="bc-assistant-controls">
                    <button class="bc-assistant-minimize" type="button" title="Zminimalizuj">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="bc-assistant-close" type="button" title="Zamknij">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Create messages container
            this.messagesContainer = document.createElement('div');
            this.messagesContainer.className = 'bc-assistant-messages';
            
            // Create input container
            const inputContainer = document.createElement('div');
            inputContainer.className = 'bc-assistant-input-container';
            inputContainer.innerHTML = `
                <textarea class="bc-assistant-input" placeholder="Wpisz swoje pytanie..."></textarea>
                <button class="bc-assistant-send" type="button" title="Wyślij">
                    <i class="fas fa-paper-plane"></i>
                </button>
            `;
            
            // Assemble the components
            this.window.appendChild(header);
            this.window.appendChild(this.messagesContainer);
            this.window.appendChild(inputContainer);
            
            this.container.appendChild(this.bubble);
            this.container.appendChild(this.window);
            this.wrapper.appendChild(this.container);
            
            // Add to document body
            document.body.appendChild(this.wrapper);
            
            // Store references to elements we'll need to access later
            this.inputField = this.wrapper.querySelector('.bc-assistant-input');
            this.sendButton = this.wrapper.querySelector('.bc-assistant-send');
            this.closeButton = this.wrapper.querySelector('.bc-assistant-close');
            this.minimizeButton = this.wrapper.querySelector('.bc-assistant-minimize');
        }
        
        // Set up all event listeners
        setupEvents() {
            // Use direct handler methods with bound context to avoid issues
            // Bubble handlers
            this.bubbleClickHandler = this.handleBubbleClick.bind(this);
            this.bubble.addEventListener('click', this.bubbleClickHandler);
            this.bubble.addEventListener('touchend', this.bubbleClickHandler);
            
            // Close button handlers
            this.closeButtonHandler = this.handleCloseClick.bind(this);
            if (this.closeButton) {
                this.closeButton.addEventListener('click', this.closeButtonHandler);
                this.closeButton.addEventListener('touchend', this.closeButtonHandler);
            }
            
            // Minimize button handlers
            this.minimizeButtonHandler = this.handleMinimizeClick.bind(this);
            if (this.minimizeButton) {
                this.minimizeButton.addEventListener('click', this.minimizeButtonHandler);
                this.minimizeButton.addEventListener('touchend', this.minimizeButtonHandler);
            }
            
            // Send button handlers
            this.sendButtonHandler = this.handleSendClick.bind(this);
            if (this.sendButton) {
                this.sendButton.addEventListener('click', this.sendButtonHandler);
                this.sendButton.addEventListener('touchend', this.sendButtonHandler);
            }
            
            // Input field - handle Enter key
            this.inputKeyHandler = this.handleInputKeyDown.bind(this);
            if (this.inputField) {
                this.inputField.addEventListener('keydown', this.inputKeyHandler);
                
                // Auto-resize textarea
                this.inputField.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
            
            // Window resize event
            this.resizeHandler = this.handleWindowResize.bind(this);
            window.addEventListener('resize', this.resizeHandler);
            
            // Stop propagation for all events inside the wrapper
            this.wrapperClickHandler = this.handleWrapperClick.bind(this);
            this.wrapper.addEventListener('click', this.wrapperClickHandler, true);
            this.wrapper.addEventListener('touchstart', this.wrapperClickHandler, { passive: false, capture: true });
            this.wrapper.addEventListener('touchmove', this.handleWrapperTouchMove.bind(this), { passive: false, capture: true });
        }
        
        // Event handler methods
        handleBubbleClick(e) {
            e.preventDefault();
            e.stopPropagation();
            this.toggleWindow();
            return false;
        }
        
        handleCloseClick(e) {
            e.preventDefault();
            e.stopPropagation();
            this.closeWindow();
            return false;
        }
        
        handleMinimizeClick(e) {
            e.preventDefault();
            e.stopPropagation();
            this.closeWindow();
            return false;
        }
        
        handleSendClick(e) {
            e.preventDefault();
            e.stopPropagation();
            this.sendMessage();
            return false;
        }
        
        handleInputKeyDown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
                return false;
            }
        }
        
        handleWindowResize() {
            this.isMobile = this.checkIfMobile();
            this.fixDisplay();
        }
        
        handleWrapperClick(e) {
            // Only stop propagation for clicks inside our components
            if (e.target.closest('.bc-assistant-bubble, .bc-assistant-window')) {
                e.stopPropagation();
            }
        }
        
        handleWrapperTouchMove(e) {
            // Prevent touch events from affecting page scrolling
            if (e.target.closest('.bc-assistant-messages')) {
                // Allow scrolling within messages container
                e.stopPropagation();
            } else if (e.target.closest('.bc-assistant-bubble, .bc-assistant-window')) {
                // Prevent for other components
                e.preventDefault();
                e.stopPropagation();
            }
        }
        
        // Toggle chat window visibility
        toggleWindow() {
            if (this.isOpen) {
                this.closeWindow();
            } else {
                this.openWindow();
            }
        }
        
        // Open chat window
        openWindow() {
            this.isOpen = true;
            
            // Force display style with !important equivalent
            // Set the style properties directly rather than using style.display
            this.window.style.setProperty('display', 'flex', 'important');
            this.window.style.setProperty('opacity', '1', 'important');
            this.window.style.setProperty('visibility', 'visible', 'important');
            
            // Focus input field
            if (this.inputField) {
                setTimeout(() => this.inputField.focus(), 100);
            }
            
            // Scroll to latest message
            this.scrollToBottom();
        }
        
        // Close chat window
        closeWindow() {
            this.isOpen = false;
            this.window.style.setProperty('display', 'none', 'important');
        }
        
        // Add a message to the chat
        addMessage(role, content) {
            // Create message object
            const message = {
                role,
                content: content || '',
                timestamp: new Date()
            };
            
            // Store in messages array
            this.messages.push(message);
            
            // Create message element
            const messageElem = document.createElement('div');
            messageElem.className = `bc-message bc-message-${role}`;
            
            // Create message content element
            const contentElem = document.createElement('div');
            contentElem.className = 'bc-message-content';
            contentElem.innerHTML = this.formatMessage(content || '');
            
            // Add timestamp
            const timestamp = document.createElement('div');
            timestamp.className = 'bc-message-timestamp';
            timestamp.textContent = this.formatTime(new Date());
            
            // Assemble message
            messageElem.appendChild(contentElem);
            messageElem.appendChild(timestamp);
            
            // Add to messages container
            this.messagesContainer.appendChild(messageElem);
            
            // Scroll to bottom
            this.scrollToBottom();
        }
        
        // Format message with markdown
        formatMessage(text) {
            if (!text) return '';
            
            return text
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/`([^`]+)`/g, '<code>$1</code>')
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
        }
        
        // Format timestamp
        formatTime(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        
        // Scroll messages to bottom
        scrollToBottom() {
            if (!this.messagesContainer) return;
            
            // Immediate scroll
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            
            // Delayed scroll (for images, etc.)
            setTimeout(() => {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
                
                // Final scroll after full render
                setTimeout(() => {
                    this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
                    
                    // Force scroll with scrollIntoView as backup
                    const lastMessage = this.messagesContainer.lastElementChild;
                    if (lastMessage) {
                        lastMessage.scrollIntoView({ behavior: 'auto', block: 'end' });
                    }
                }, 300);
            }, 50);
        }
        
        // Send message to API
        async sendMessage() {
            // Get message from input field
            const message = this.inputField.value.trim();
            
            // Skip if empty or already processing
            if (!message || this.isTyping) return;
            
            // Add user message to chat
            this.addMessage('user', message);
            
            // Clear input field and reset height
            this.inputField.value = '';
            this.inputField.style.height = 'auto';
            
            // Show typing indicator
            this.isTyping = true;
            this.showTypingIndicator();
            
            try {
                // Prepare form data
                const formData = new FormData();
                formData.append('action', this.config.action);
                formData.append('message', message);
                formData.append('thread_id', this.threadId);
                formData.append('nonce', this.config.nonce);
                
                // Send to API
                const response = await fetch(this.config.apiEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                
                // Parse response
                const data = await response.json();
                
                // Hide typing indicator
                this.hideTypingIndicator();
                this.isTyping = false;
                
                if (data.success) {
                    // Add assistant response to chat
                    this.addMessage('assistant', data.data.message);
                    
                    // Store thread ID if provided
                    if (data.data.thread_id) {
                        this.threadId = data.data.thread_id;
                        localStorage.setItem('bc_assistant_thread_id', this.threadId);
                    }
                } else {
                    // Show error message
                    this.addMessage('assistant', 'Przepraszam, wystąpił błąd. Spróbuj ponownie później.');
                    console.error('BC Assistant API Error:', data);
                }
            } catch (error) {
                // Hide typing indicator
                this.hideTypingIndicator();
                this.isTyping = false;
                
                // Show error message
                this.addMessage('assistant', 'Przepraszam, wystąpił błąd połączenia. Spróbuj ponownie później.');
                console.error('BC Assistant Error:', error);
            }
        }
        
        // Show typing indicator
        showTypingIndicator() {
            const indicatorElem = document.createElement('div');
            indicatorElem.className = 'bc-message bc-message-assistant bc-typing-indicator';
            indicatorElem.innerHTML = `
                <div class="bc-message-content">
                    <div class="bc-typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `;
            
            this.messagesContainer.appendChild(indicatorElem);
            this.scrollToBottom();
        }
        
        // Hide typing indicator
        hideTypingIndicator() {
            const indicator = this.messagesContainer.querySelector('.bc-typing-indicator');
            if (indicator) {
                indicator.remove();
            }
        }
        
        // Fix all display issues - combined solutions for all browsers
        fixDisplay() {
            // Set high z-index for all components
            const highZIndex = '9999999';
            
            this.wrapper.style.setProperty('z-index', highZIndex, 'important');
            this.wrapper.style.setProperty('position', 'fixed', 'important');
            this.wrapper.style.setProperty('display', 'block', 'important');
            this.wrapper.style.setProperty('visibility', 'visible', 'important');
            this.wrapper.style.setProperty('opacity', '1', 'important');
            this.wrapper.style.setProperty('pointer-events', 'auto', 'important');
            
            // Apply position based on configuration
            const position = this.config.position || 'bottom-right';
            
            // Apply position
            if (position === 'bottom-right') {
                this.wrapper.style.setProperty('bottom', this.isMobile ? '100px' : '20px', 'important');
                this.wrapper.style.setProperty('right', '20px', 'important');
                this.wrapper.style.setProperty('left', 'auto', 'important');
                this.wrapper.style.setProperty('top', 'auto', 'important');
            } else if (position === 'bottom-left') {
                this.wrapper.style.setProperty('bottom', this.isMobile ? '100px' : '20px', 'important');
                this.wrapper.style.setProperty('left', '20px', 'important');
                this.wrapper.style.setProperty('right', 'auto', 'important');
                this.wrapper.style.setProperty('top', 'auto', 'important');
            } else if (position === 'top-right') {
                this.wrapper.style.setProperty('top', '20px', 'important');
                this.wrapper.style.setProperty('right', '20px', 'important');
                this.wrapper.style.setProperty('bottom', 'auto', 'important');
                this.wrapper.style.setProperty('left', 'auto', 'important');
            } else if (position === 'top-left') {
                this.wrapper.style.setProperty('top', '20px', 'important');
                this.wrapper.style.setProperty('left', '20px', 'important');
                this.wrapper.style.setProperty('bottom', 'auto', 'important');
                this.wrapper.style.setProperty('right', 'auto', 'important');
            }
            
            // Fix bubble display
            if (this.bubble) {
                this.bubble.style.setProperty('display', 'flex', 'important');
                this.bubble.style.setProperty('visibility', 'visible', 'important');
                this.bubble.style.setProperty('opacity', '1', 'important');
                this.bubble.style.setProperty('align-items', 'center', 'important');
                this.bubble.style.setProperty('justify-content', 'center', 'important');
                this.bubble.style.setProperty('z-index', highZIndex, 'important');
                this.bubble.style.setProperty('cursor', 'pointer', 'important');
                
                if (this.isMobile) {
                    this.bubble.style.setProperty('width', '50px', 'important');
                    this.bubble.style.setProperty('height', '50px', 'important');
                    this.bubble.style.setProperty('font-size', '20px', 'important');
                }
            }
            
            // Set window styles based on position
            if (this.window) {
                this.window.style.setProperty('z-index', highZIndex, 'important');
                
                // Default window positioning
                if (position.includes('right')) {
                    this.window.style.setProperty('right', '0', 'important');
                    this.window.style.setProperty('left', 'auto', 'important');
                } else {
                    this.window.style.setProperty('left', '0', 'important');
                    this.window.style.setProperty('right', 'auto', 'important');
                }
                
                if (position.includes('top')) {
                    this.window.style.setProperty('top', '70px', 'important');
                    this.window.style.setProperty('bottom', 'auto', 'important');
                } else {
                    this.window.style.setProperty('bottom', '70px', 'important');
                    this.window.style.setProperty('top', 'auto', 'important');
                }
                
                // Mobile adjustments
                if (this.isMobile) {
                    this.window.style.setProperty('width', '85vw', 'important');
                    this.window.style.setProperty('max-width', '350px', 'important');
                    this.window.style.setProperty('height', '70vh', 'important');
                }
            }
            
            // Browser-specific fixes
            this.applyBrowserSpecificFixes();
        }
        
        // Apply browser-specific fixes
        applyBrowserSpecificFixes() {
            const isFirefox = navigator.userAgent.indexOf('Firefox') !== -1;
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            
            if (isFirefox) {
                // Firefox-specific fixes
                if (this.wrapper) {
                    this.wrapper.style.setProperty('min-width', '50px', 'important');
                    this.wrapper.style.setProperty('min-height', '50px', 'important');
                    this.wrapper.style.setProperty('clip-path', 'none', 'important');
                    this.wrapper.style.setProperty('transform', 'none', 'important');
                }
                
                if (this.bubble) {
                    this.bubble.style.setProperty('transform', 'none', 'important');
                    this.bubble.style.setProperty('clip-path', 'none', 'important');
                    
                    // Add extra tap area for Firefox mobile
                    if (this.isMobile) {
                        const tapArea = document.createElement('div');
                        tapArea.style.setProperty('position', 'absolute', 'important');
                        tapArea.style.setProperty('width', '70px', 'important');
                        tapArea.style.setProperty('height', '70px', 'important');
                        tapArea.style.setProperty('top', '-10px', 'important');
                        tapArea.style.setProperty('left', '-10px', 'important');
                        tapArea.style.setProperty('right', '-10px', 'important');
                        tapArea.style.setProperty('bottom', '-10px', 'important');
                        tapArea.style.setProperty('z-index', '1', 'important');
                        
                        // Ensure bubble has position relative
                        this.bubble.style.setProperty('position', 'relative', 'important');
                        
                        // Only add if not already present
                        if (!this.bubble.querySelector('.bc-tap-area')) {
                            tapArea.className = 'bc-tap-area';
                            this.bubble.appendChild(tapArea);
                            
                            // Add event listeners to the tap area
                            tapArea.addEventListener('click', this.bubbleClickHandler);
                            tapArea.addEventListener('touchend', this.bubbleClickHandler);
                        }
                    }
                }
            }
            
            if (isSafari || isIOS) {
                // Safari and iOS fixes
                if (this.wrapper) {
                    this.wrapper.style.setProperty('-webkit-tap-highlight-color', 'rgba(0,0,0,0)', 'important');
                    this.wrapper.style.setProperty('touch-action', 'auto', 'important');
                }
                
                if (this.bubble) {
                    this.bubble.style.setProperty('-webkit-user-select', 'none', 'important');
                    this.bubble.style.setProperty('user-select', 'none', 'important');
                }
                
                // Add specific touch handling for iOS
                if (isIOS && this.bubble) {
                    // Replace all event listeners with iOS-specific ones
                    this.bubble.removeEventListener('click', this.bubbleClickHandler);
                    this.bubble.removeEventListener('touchend', this.bubbleClickHandler);
                    
                    // iOS-specific handler
                    const iosTouchHandler = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        setTimeout(() => this.toggleWindow(), 10);
                        return false;
                    };
                    
                    this.bubble.addEventListener('touchstart', iosTouchHandler, { passive: false });
                }
            }
            
            // Apply Edge-specific fixes
            if (/Edge/.test(navigator.userAgent)) {
                if (this.wrapper) {
                    this.wrapper.style.setProperty('transform', 'translateZ(0)', 'important');
                }
                
                if (this.bubble) {
                    this.bubble.style.setProperty('transform', 'translateZ(0)', 'important');
                }
            }
        }
        
        // Ensure visibility (called periodically)
        ensureVisibility() {
            if (!this.wrapper) return;
            
            this.wrapper.style.setProperty('display', 'block', 'important');
            this.wrapper.style.setProperty('visibility', 'visible', 'important');
            this.wrapper.style.setProperty('opacity', '1', 'important');
            
            if (this.bubble) {
                this.bubble.style.setProperty('display', 'flex', 'important');
                this.bubble.style.setProperty('visibility', 'visible', 'important');
                this.bubble.style.setProperty('opacity', '1', 'important');
            }
            
            if (this.window && this.isOpen) {
                this.window.style.setProperty('display', 'flex', 'important');
                this.window.style.setProperty('visibility', 'visible', 'important');
                this.window.style.setProperty('opacity', '1', 'important');
            }
            
            // Re-apply browser-specific fixes
            this.applyBrowserSpecificFixes();
        }
    }
    
    // Initialize the assistant once DOM is loaded
    function initBCAssistant() {
        // Only initialize once
        if (bcAssistantInstance) {
            console.log("BC Assistant already initialized");
            return;
        }
        
        try {
            // Make sure welcome message is set
            if (typeof window.bcAssistantWelcomeMessage === 'undefined' || !window.bcAssistantWelcomeMessage) {
                window.bcAssistantWelcomeMessage = 'Witaj! W czym mogę pomóc?';
            }
            
            // Update config with welcome message if needed
            if (window.bcAssistantData && !window.bcAssistantData.welcomeMessage) {
                window.bcAssistantData.welcomeMessage = window.bcAssistantWelcomeMessage;
            }
            
            // Remove any existing instances
            const existingWrapper = document.querySelector('.bc-assistant-wrapper');
            if (existingWrapper) {
                existingWrapper.remove();
            }
            
            // Create the assistant
            bcAssistantInstance = new BCAssistant(bcConfig);
            window.bcAssistant = bcAssistantInstance;
            console.log("BC Assistant initialized successfully");
        } catch (error) {
            console.error("BC Assistant initialization error:", error);
        }
    }
    
    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBCAssistant);
    } else {
        // Document already loaded, initialize now
        initBCAssistant();
    }
    
    // Add special initialization for mobile devices
    document.addEventListener('DOMContentLoaded', function() {
        // Add a slight delay for mobile devices to ensure all resources are loaded
        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            setTimeout(function() {
                if (!bcAssistantInstance) {
                    console.log("BC Assistant: Mobile-specific initialization");
                    initBCAssistant();
                }
                
                // Force visibility check for mobile
                if (bcAssistantInstance) {
                    bcAssistantInstance.ensureVisibility();
                }
            }, 1000);
        }
    });
    
    // Make the initBCAssistant function globally available
    window.initBCAssistant = initBCAssistant;
    
})(jQuery);

// Add inline CSS fixes - these will take precedence over stylesheet rules
document.addEventListener('DOMContentLoaded', function() {
    // Create a style element for critical CSS fixes
    const styleEl = document.createElement('style');
    styleEl.id = 'bc-assistant-critical-fixes';
    styleEl.innerHTML = `
        /* Critical fixes that override any other styles */
        .bc-assistant-wrapper {
            position: fixed !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 9999999 !important;
            pointer-events: auto !important;
        }
        
        .bc-assistant-bubble {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            z-index: 9999999 !important;
        }
        
        @media (max-width: 767px) {
            .bc-assistant-wrapper {
                bottom: 100px !important;
            }
            
            .bc-assistant-bubble {
                width: 50px !important;
                height: 50px !important;
            }
            
            .bc-assistant-window {
                width: 85vw !important;
                max-width: 350px !important;
                height: 70vh !important;
            }
        }
        
        /* Firefox mobile fixes */
        @-moz-document url-prefix() {
            @media (max-width: 767px) {
                .bc-assistant-wrapper {
                    min-width: 50px !important;
                    min-height: 50px !important;
                    clip: auto !important;
                    pointer-events: auto !important;
                    transform: none !important;
                }
                
                .bc-assistant-bubble {
                    transform: none !important;
                    clip-path: none !important;
                }
            }
        }
        
        /* iOS fixes */
        @supports (-webkit-touch-callout: none) {
            .bc-assistant-wrapper {
                pointer-events: auto !important;
            }
            
            .bc-assistant-bubble {
                cursor: pointer !important;
                -webkit-user-select: none !important;
                user-select: none !important;
            }
        }
    `;
    
    // Add the style element to the head
    document.head.appendChild(styleEl);
});