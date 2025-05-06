/**
 * BC Assistant - Fixed Mobile Implementation
 * This script resolves conflicts with Droplabs and ensures reliable mobile display
 */

(function($) {
    "use strict";
	
// Create a dedicated namespace for BC Assistant to avoid global conflicts
window.BCAssistantNamespace = window.BCAssistantNamespace || {};

// Store previous global handlers to avoid overriding them
window.BCAssistantNamespace.originalHandlers = {
    scroll: window.onscroll,
    resize: window.onresize,
    click: window.onclick
};
    
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
		
init() {
    // Create the DOM elements
    this.createDOM();
    
    // Add event listeners
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
        console.warn('BC Assistant: Using default welcome message');
    }
    
    // Add welcome message
    this.addMessage('assistant', welcomeMessage);
    
    // Apply fixes for potential conflicts
    this.applyFixes();
    
    // Set up periodic visibility check
    setInterval(() => this.ensureVisibility(), 2000);
}

        constructor(config) {
            // Store configuration
            this.config = config;
            this.messages = [];
            this.isOpen = false;
            this.isTyping = false;
            this.threadId = localStorage.getItem('bc_assistant_thread_id') || '';
            this.isMobile = this.checkIfMobile();
            
            // Initialize the assistant
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
            // Create the DOM elements
            this.createDOM();
            
            // Add event listeners
            this.setupEvents();
            
            // Add initial welcome message
            this.addMessage('assistant', this.config.initialMessage);
            
            // Apply fixes for potential conflicts
            this.applyFixes();
            
            // Set up periodic visibility check
            setInterval(() => this.ensureVisibility(), 2000);
        }
        
        // Create all DOM elements
        createDOM() {
            // Create wrapper (highest level container)
            this.wrapper = document.createElement('div');
            this.wrapper.className = 'bc-assistant-wrapper';
            this.wrapper.setAttribute('data-position', this.config.position);
            
            // Create main container
            this.container = document.createElement('div');
            this.container.className = 'bc-assistant-container';
            
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
                    <button class="bc-assistant-minimize" title="Zminimalizuj">
                        <i class="fas fa-minus"></i>
                    </button>
                    <button class="bc-assistant-close" title="Zamknij">
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
                <button class="bc-assistant-send" title="Wyślij">
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
            // Bubble click - toggle chat window
            this.bubble.addEventListener('click', () => this.toggleWindow());
            
            // Close button
            if (this.closeButton) {
                this.closeButton.addEventListener('click', () => this.closeWindow());
            }
            
            // Minimize button
            if (this.minimizeButton) {
                this.minimizeButton.addEventListener('click', () => this.closeWindow());
            }
            
            // Send button
            if (this.sendButton) {
                this.sendButton.addEventListener('click', () => this.sendMessage());
            }
            
            // Input field - handle Enter key
            if (this.inputField) {
                this.inputField.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });
                
                // Auto-resize textarea
                this.inputField.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            }
            
            // Window resize event
            window.addEventListener('resize', () => {
                this.isMobile = this.checkIfMobile();
                this.applyFixes();
            });
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
            this.window.style.display = 'flex';
            
            // Focus input field
            if (this.inputField) {
                setTimeout(() => this.inputField.focus(), 100);
            }
            
            // Scroll to latest message
            this.scrollToBottom();
            
            // Add class to document body to fix scrolling issues
            document.body.classList.add('bc-assistant-open');
            document.documentElement.classList.add('bc-assistant-open');
        }
        
        // Close chat window
        closeWindow() {
            this.isOpen = false;
            this.window.style.display = 'none';
            
            // Remove body class
            document.body.classList.remove('bc-assistant-open');
            document.documentElement.classList.remove('bc-assistant-open');
        }
        
        // Add a message to the chat
        addMessage(role, content) {
            // Create message object
            const message = {
                role,
                content,
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
            contentElem.innerHTML = this.formatMessage(content);
            
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
        
formatMessage(text) {
    // Check if text is undefined or null
    if (!text) {
        console.warn('BC Assistant: Attempted to format undefined text');
        return ''; // Return empty string if text is undefined
    }
    
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
        
        // Apply fixes for known issues and conflicts
        applyFixes() {
            // Fix for conflict with Droplabs
            this.fixPositioning();
            
            // Fix z-index issues
            this.fixZIndex();
            
            // Fix mobile-specific issues
            if (this.isMobile) {
                this.fixMobileDisplay();
            }
        }
        
        // Fix positioning based on device and conflicts
        fixPositioning() {
            // Apply position based on config
            const position = this.config.position || 'bottom-right';
            
            // Set position-specific styles
            if (position === 'bottom-right') {
                Object.assign(this.wrapper.style, {
                    bottom: this.isMobile ? '100px' : '20px',
                    right: '20px',
                    left: 'auto',
                    top: 'auto'
                });
            } else if (position === 'bottom-left') {
                Object.assign(this.wrapper.style, {
                    bottom: this.isMobile ? '100px' : '20px',
                    left: '20px', 
                    right: 'auto',
                    top: 'auto'
                });
            } else if (position === 'top-right') {
                Object.assign(this.wrapper.style, {
                    top: '20px',
                    right: '20px',
                    bottom: 'auto',
                    left: 'auto'
                });
            } else if (position === 'top-left') {
                Object.assign(this.wrapper.style, {
                    top: '20px',
                    left: '20px',
                    bottom: 'auto',
                    right: 'auto'
                });
            }
            
            // Check for Droplabs presence
            const hasDroplabs = this.detectDroplabs();
            
            if (hasDroplabs && position.includes('bottom')) {
                // Move higher to avoid conflict with Droplabs
                this.wrapper.style.bottom = '150px';
            }
        }
        
        // Detect if Droplabs is present on the page
        detectDroplabs() {
            const droplabsElements = document.querySelectorAll(
                '.droplabs-container, .droplabs-widget, .droplabs-bubble, [id*="droplabs"]'
            );
            return droplabsElements.length > 0;
        }
        
        // Fix z-index issues
        fixZIndex() {
            // Ensure high z-index for all components
            const highZIndex = '9999999';
            
            this.wrapper.style.zIndex = highZIndex;
            
            if (this.container) {
                this.container.style.zIndex = highZIndex;
            }
            
            if (this.bubble) {
                this.bubble.style.zIndex = highZIndex;
            }
            
            if (this.window) {
                this.window.style.zIndex = highZIndex;
            }
        }
        
        // Fix mobile-specific display issues
        fixMobileDisplay() {
            // Ensure bubble is properly sized and visible
            if (this.bubble) {
                Object.assign(this.bubble.style, {
                    width: '50px',
                    height: '50px',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    visibility: 'visible',
                    opacity: '1'
                });
            }
            
            // Adjust window size for mobile
            if (this.window) {
                Object.assign(this.window.style, {
                    width: '85%',
                    height: '70vh',
                    maxWidth: '350px'
                });
            }
            
            // Firefox-specific fixes
            if (navigator.userAgent.indexOf('Firefox') !== -1) {
                if (this.wrapper) {
                    Object.assign(this.wrapper.style, {
                        minWidth: '50px',
                        minHeight: '50px',
                        clipPath: 'none',
                        transform: 'none',
                        pointerEvents: 'auto'
                    });
                }
            }
        }
        
        // Ensure visibility (called periodically)
        ensureVisibility() {
            if (this.wrapper) {
                this.wrapper.style.display = 'block';
                this.wrapper.style.visibility = 'visible';
                this.wrapper.style.opacity = '1';
                
                // Force all child elements to be visible too
                if (this.bubble) {
                    this.bubble.style.display = 'flex';
                    this.bubble.style.visibility = 'visible';
                    this.bubble.style.opacity = '1';
                }
                
                // Only make window visible if it's supposed to be open
                if (this.window) {
                    if (this.isOpen) {
                        this.window.style.display = 'flex';
                    } else {
                        this.window.style.display = 'none';
                    }
                }
            }
        }
    }
    
    // Initialize the assistant once DOM is loaded
    function initBCAssistant() {
        try {
            window.bcAssistant = new BCAssistant(bcConfig);
            console.log("BC Assistant initialized successfully");
        } catch (error) {
            console.error("BC Assistant initialization error:", error);
        }
    }
    
    // Initialize when document is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBCAssistant);
    } else {
        initBCAssistant();
    }
    
})(jQuery);

// New event handler to prevent propagation issues
document.addEventListener('DOMContentLoaded', function(e) {
    // Initialize BC Assistant safely
    if (typeof initBCAssistant === 'function') {
        initBCAssistant();
    }
    
    // Ensure events don't propagate beyond what's needed
    document.querySelectorAll('.bc-assistant-wrapper, .bc-assistant-bubble, .bc-assistant-window')
        .forEach(function(el) {
            el.addEventListener('touchmove', function(e) {
                e.stopPropagation();
            }, { passive: true });
        });
}, { passive: true });

(function() {
    document.addEventListener('DOMContentLoaded', function() {
        console.log('BC Assistant: Checking welcome message initialization');
        
        if (typeof window.bcAssistantWelcomeMessage === 'undefined' || !window.bcAssistantWelcomeMessage) {
            console.warn('BC Assistant: Welcome message not found in global scope, setting default');
            window.bcAssistantWelcomeMessage = 'Witaj! W czym mogę pomóc?';
        }
        
        if (typeof window.bcAssistantData === 'undefined' || !window.bcAssistantData.welcomeMessage) {
            console.warn('BC Assistant: welcomeMessage not found in bcAssistantData, using global welcome message');
            if (window.bcAssistantData) {
                window.bcAssistantData.welcomeMessage = window.bcAssistantWelcomeMessage;
            }
        }
        
        console.log('BC Assistant: Welcome message setup complete');
    });
})();
