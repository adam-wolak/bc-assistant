/**
 * BC Assistant - Enhanced Mobile Implementation
 * This script includes all necessary fixes for cross-browser compatibility
 */

(function($) {
    "use strict";
    
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
    
    // Store single instance reference
    let bcAssistantInstance = null;
    
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
            
            // Get welcome message from config or global variable or default
            let welcomeMessage = this.config.welcomeMessage;
            
            // If not in config, try global variable
            if (!welcomeMessage && typeof window.bcAssistantWelcomeMessage !== 'undefined') {
                welcomeMessage = window.bcAssistantWelcomeMessage;
            }
            
            // If still not found, use default
            if (!welcomeMessage) {
                welcomeMessage = 'Witaj! W czym mogę pomóc?';
                console.log('BC Assistant: Using default welcome message');
            }
            
            // Add welcome message
            this.addMessage('assistant', welcomeMessage);
            
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
            
            // Add theme class if specified
            if (this.config.theme) {
                this.container.classList.add('bc-assistant-' + this.config.theme);
            }
            
            // Create chat bubble
            this.bubble = document.createElement('div');
            this.bubble.className = 'bc-assistant-bubble';
            this.bubble.innerHTML = '<i class="fas fa-comments"></i>';
            
            // Create enhanced tap area for better click detection
            this.createEnhancedTapArea();
            
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
        
        // Create enhanced tap area for better mobile interaction
        createEnhancedTapArea() {
            const tapArea = document.createElement('div');
            tapArea.className = 'bc-tap-area';
            
            // Add the tap area to the bubble
            this.bubble.appendChild(tapArea);
        }
        
        // Set up all event listeners
        setupEvents() {
            // Enhanced click handling with multiple approaches
            this.setupClickHandlers();
            
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
            
            // Check for other floating elements
            this.checkForFloatingElements();
            
            // Add a MutationObserver to detect DOM changes that might add new floating elements
            this.observeDOMChanges();
        }
        
        // Set up multiple event handlers for maximum compatibility
        setupClickHandlers() {
            // Tap area click handling
            const tapArea = this.bubble.querySelector('.bc-tap-area');
            if (tapArea) {
                this.addMultipleEventListeners(tapArea, ['click', 'touchend'], (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.toggleWindow();
                    return false;
                });
            }
            
            // Bubble click handling (direct)
            this.addMultipleEventListeners(this.bubble, ['click', 'touchend'], (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.toggleWindow();
                return false;
            });
            
            // Close button
            if (this.closeButton) {
                this.addMultipleEventListeners(this.closeButton, ['click', 'touchend'], (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeWindow();
                    return false;
                });
            }
            
            // Minimize button
            if (this.minimizeButton) {
                this.addMultipleEventListeners(this.minimizeButton, ['click', 'touchend'], (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.closeWindow();
                    return false;
                });
            }
            
            // Send button
            if (this.sendButton) {
                this.addMultipleEventListeners(this.sendButton, ['click', 'touchend'], (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.sendMessage();
                    return false;
                });
            }
        }
        
        // Helper to add multiple event listeners to an element
        addMultipleEventListeners(element, events, handler) {
            for (const eventName of events) {
                element.addEventListener(eventName, handler, true);
            }
        }
        
        // Check for other floating elements at the bottom of the page
        checkForFloatingElements() {
            // Common selectors for floating elements
            const floatingElementSelectors = [
                '[class*="chat"]', 
                '[class*="widget"]', 
                '[class*="bubble"]',
                '[class*="float"]',
                '[class*="popup"]',
                '[class*="bot"]',
                '[class*="messenger"]',
                '[id*="chat"]',
                '[id*="widget"]',
                '[id*="bubble"]',
                '[id*="bot"]',
                '[id*="messenger"]',
                '[style*="position: fixed"]'
            ];
            
            // Find all floating elements
            const floatingElements = document.querySelectorAll(floatingElementSelectors.join(','));
            
            // Check if any are positioned at the bottom of the screen
            let hasConflictingElements = false;
            
            floatingElements.forEach(el => {
                // Skip our own elements
                if (el.closest('.bc-assistant-wrapper')) {
                    return;
                }
                
                const rect = el.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                
                // Check if element is in the bottom 200px of the screen
                if (rect.bottom > viewportHeight - 200) {
                    hasConflictingElements = true;
                }
            });
            
            // Adjust position if needed
            if (hasConflictingElements) {
                this.wrapper.classList.add('adjust-for-floating-elements');
            } else {
                this.wrapper.classList.remove('adjust-for-floating-elements');
            }
        }
        
        // Observe DOM changes to detect new floating elements
        observeDOMChanges() {
            // Create a MutationObserver to watch for DOM changes
            const observer = new MutationObserver(() => {
                // Check for floating elements whenever the DOM changes
                this.checkForFloatingElements();
            });
            
            // Start observing the document body for added/removed nodes
            observer.observe(document.body, { 
                childList: true, 
                subtree: true 
            });
        }
        
        // Toggle chat window visibility with enhanced reliability
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
            
            // Force display style with !important-like priority
            this.window.style.setProperty('display', 'flex', 'important');
            this.window.style.setProperty('visibility', 'visible', 'important');
            this.window.style.setProperty('opacity', '1', 'important');
            
            // Add transition class
            this.window.classList.add('bc-fade-in');
            
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
            this.window.style.setProperty('display', 'none', 'important');
            
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
        
        // Format message with markdown
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
            // Fix positioning
            this.fixPositioning();
            
            // Fix z-index issues
            this.fixZIndex();
            
            // Fix mobile-specific issues
            if (this.isMobile) {
                this.fixMobileDisplay();
            }
            
            // Apply browser-specific fixes
            this.applyBrowserSpecificFixes();
            
            // Check for other floating elements again
            this.checkForFloatingElements();
        }
        
        // Fix positioning
        fixPositioning() {
            // Get position from config
            const position = this.config.position || 'bottom-right';
            
            // Mobile positioning
            if (this.isMobile) {
                // Clear inline styles first
                this.wrapper.style.removeProperty('top');
                this.wrapper.style.removeProperty('left');
                this.wrapper.style.removeProperty('right');
                
                // Set bottom position higher on mobile
                this.wrapper.style.setProperty('bottom', '140px', 'important');
                
                // Left/right positioning based on config
                if (position.includes('left')) {
                    this.wrapper.style.setProperty('left', '20px', 'important');
                    this.wrapper.style.setProperty('right', 'auto', 'important');
                } else {
                    this.wrapper.style.setProperty('right', '20px', 'important');
                    this.wrapper.style.setProperty('left', 'auto', 'important');
                }
            } else {
                // Desktop positioning
                if (position === 'bottom-right') {
                    this.wrapper.style.setProperty('bottom', '20px', 'important');
                    this.wrapper.style.setProperty('right', '20px', 'important');
                    this.wrapper.style.setProperty('left', 'auto', 'important');
                    this.wrapper.style.setProperty('top', 'auto', 'important');
                } else if (position === 'bottom-left') {
                    this.wrapper.style.setProperty('bottom', '20px', 'important');
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
            }
            
            // Set window position based on position setting
            if (this.window) {
                if (position.includes('left')) {
                    this.window.style.setProperty('left', '0', 'important');
                    this.window.style.setProperty('right', 'auto', 'important');
                } else {
                    this.window.style.setProperty('right', '0', 'important');
                    this.window.style.setProperty('left', 'auto', 'important');
                }
                
                if (position.includes('top')) {
                    this.window.style.setProperty('top', '70px', 'important');
                    this.window.style.setProperty('bottom', 'auto', 'important');
                } else {
                    this.window.style.setProperty('bottom', '70px', 'important');
                    this.window.style.setProperty('top', 'auto', 'important');
                }
            }
        }
        
        // Fix z-index issues
        fixZIndex() {
            // Use maximum valid z-index
            const highZIndex = 2147483647;
            
            this.wrapper.style.setProperty('z-index', highZIndex, 'important');
            
            if (this.container) {
                this.container.style.setProperty('z-index', highZIndex, 'important');
            }
            
            if (this.bubble) {
                this.bubble.style.setProperty('z-index', highZIndex, 'important');
            }
            
            if (this.window) {
                this.window.style.setProperty('z-index', highZIndex, 'important');
            }
        }
        
        // Fix mobile-specific display issues
        fixMobileDisplay() {
            // Ensure bubble is properly sized and visible
            if (this.bubble) {
                this.bubble.style.setProperty('width', '50px', 'important');
                this.bubble.style.setProperty('height', '50px', 'important');
                this.bubble.style.setProperty('display', 'flex', 'important');
                this.bubble.style.setProperty('align-items', 'center', 'important');
                this.bubble.style.setProperty('justify-content', 'center', 'important');
                this.bubble.style.setProperty('visibility', 'visible', 'important');
                this.bubble.style.setProperty('opacity', '1', 'important');
                this.bubble.style.setProperty('border', '2px solid #fff', 'important');
                this.bubble.style.setProperty('box-shadow', '0 2px 10px rgba(0,0,0,0.3)', 'important');
            }
            
            // Adjust window size for mobile
            if (this.window) {
                this.window.style.setProperty('width', '85vw', 'important');
                this.window.style.setProperty('height', '70vh', 'important');
                this.window.style.setProperty('max-width', '350px', 'important');
            }
        }
        
        // Apply browser-specific fixes
        applyBrowserSpecificFixes() {
            const isFirefox = navigator.userAgent.indexOf('Firefox') !== -1;
            const isEdge = /Edge/.test(navigator.userAgent);
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            
            // Firefox-specific fixes
            if (isFirefox) {
                this.wrapper.style.setProperty('transform', 'none', 'important');
                this.bubble.style.setProperty('transform', 'none', 'important');
                
                // Mobile Firefox needs special handling
                if (this.isMobile) {
                    this.wrapper.style.setProperty('min-width', '50px', 'important');
                    this.wrapper.style.setProperty('min-height', '50px', 'important');
                    this.wrapper.style.setProperty('clip-path', 'none', 'important');
                    this.bubble.style.setProperty('clip-path', 'none', 'important');
                }
            }
            
            // Edge-specific fixes
            if (isEdge) {
                // Force hardware acceleration
                this.wrapper.style.setProperty('transform', 'translateZ(0)', 'important');
                this.bubble.style.setProperty('transform', 'translateZ(0)', 'important');
            }
            
            // Safari-specific fixes
            if (isSafari) {
                this.wrapper.style.setProperty('-webkit-tap-highlight-color', 'rgba(0,0,0,0)', 'important');
                this.bubble.style.setProperty('-webkit-user-select', 'none', 'important');
                this.bubble.style.setProperty('user-select', 'none', 'important');
            }
        }
        
        // Ensure visibility (called periodically)
        ensureVisibility() {
            if (this.wrapper) {
                this.wrapper.style.setProperty('display', 'block', 'important');
                this.wrapper.style.setProperty('visibility', 'visible', 'important');
                this.wrapper.style.setProperty('opacity', '1', 'important');
                this.wrapper.style.setProperty('position', 'fixed', 'important');
                
                // Force all child elements to be visible too
                if (this.bubble) {
                    this.bubble.style.setProperty('display', 'flex', 'important');
                    this.bubble.style.setProperty('visibility', 'visible', 'important');
                    this.bubble.style.setProperty('opacity', '1', 'important');
                }
                
                // Only make window visible if it's supposed to be open
                if (this.window) {
                    if (this.isOpen) {
                        this.window.style.setProperty('display', 'flex', 'important');
                        this.window.style.setProperty('visibility', 'visible', 'important');
                        this.window.style.setProperty('opacity', '1', 'important');
                    } else {
                        this.window.style.setProperty('display', 'none', 'important');
                    }
                }
                
                // Re-apply browser-specific fixes
                this.applyBrowserSpecificFixes();
                
                // Check for other floating elements
                this.checkForFloatingElements();
            }
        }
    }
    
    // Initialize the assistant once DOM is loaded
    function initBCAssistant() {
        try {
            // Check for existing instance first
            if (bcAssistantInstance) {
                console.log("BC Assistant already initialized");
                return;
            }
            
            // Initialize welcome message if needed
            if (typeof window.bcAssistantWelcomeMessage === 'undefined' || !window.bcAssistantWelcomeMessage) {
                window.bcAssistantWelcomeMessage = 'Witaj! W czym mogę pomóc?';
            }
            
            // Update config with welcome message if needed
            if (window.bcAssistantData && !window.bcAssistantData.welcomeMessage) {
                window.bcAssistantData.welcomeMessage = window.bcAssistantWelcomeMessage;
            }
            
            // Create new instance
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
        initBCAssistant();
    }
    
    // Also initialize on window load for good measure
    window.addEventListener('load', initBCAssistant);
    
})(jQuery);