/**
 * BC Assistant - Emergency Fix
 * This solution specifically targets the clickability issues and positioning problems
 */

(function() {
    "use strict";
    
    // Create a clear separation between our code and other scripts
    const BC_ASSISTANT = {
        init: function() {
            // Wait for DOM to be fully loaded
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.setup.bind(this));
            } else {
                this.setup();
            }
            
            // Also run the setup when window is fully loaded (for images and other resources)
            window.addEventListener('load', this.setup.bind(this));
            
            // Add auto-retrying every 2 seconds for 30 seconds
            let retryCount = 0;
            const maxRetries = 15;
            const retryInterval = setInterval(() => {
                if (retryCount >= maxRetries) {
                    clearInterval(retryInterval);
                    return;
                }
                this.setup();
                retryCount++;
            }, 2000);
        },
        
        setup: function() {
            console.log('BC Assistant: Running emergency fix...');
            
            // Find the assistant wrapper
            const wrapper = document.querySelector('.bc-assistant-wrapper');
            if (!wrapper) {
                console.log('BC Assistant: Wrapper not found, waiting...');
                return;
            }
            
            // Find the bubble
            const bubble = wrapper.querySelector('.bc-assistant-bubble');
            if (!bubble) {
                console.log('BC Assistant: Bubble not found');
                return;
            }
            
            // Find the window
            const chatWindow = wrapper.querySelector('.bc-assistant-window');
            if (!chatWindow) {
                console.log('BC Assistant: Window not found');
                return;
            }
            
            // ----- Apply fixes -----
            
            // 1. Fix z-index and visibility
            this.fixZIndex(wrapper, bubble, chatWindow);
            
            // 2. Fix positioning
            this.fixPositioning(wrapper, bubble, chatWindow);
            
            // 3. Fix clickability
            this.fixClickability(bubble, chatWindow);
            
            // 4. Fix mobile-specific issues
            this.fixMobileIssues(wrapper, bubble, chatWindow);
            
            // Log success
            console.log('BC Assistant: Emergency fix applied');
        },
        
        fixZIndex: function(wrapper, bubble, chatWindow) {
            // Force extremely high z-index to ensure visibility
            const highZIndex = 2147483647; // maximum valid z-index value
            
            wrapper.style.setProperty('z-index', highZIndex, 'important');
            bubble.style.setProperty('z-index', highZIndex, 'important');
            chatWindow.style.setProperty('z-index', highZIndex, 'important');
            
            // Ensure visibility
            wrapper.style.setProperty('display', 'block', 'important');
            wrapper.style.setProperty('visibility', 'visible', 'important');
            wrapper.style.setProperty('opacity', '1', 'important');
            
            bubble.style.setProperty('display', 'flex', 'important');
            bubble.style.setProperty('visibility', 'visible', 'important');
            bubble.style.setProperty('opacity', '1', 'important');
            
            // Fix potential transform issues
            wrapper.style.setProperty('transform', 'none', 'important');
            bubble.style.setProperty('transform', 'none', 'important');
        },
        
        fixPositioning: function(wrapper, bubble, chatWindow) {
            // Get device type
            const isMobile = window.innerWidth < 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            // Adjust position based on device
            if (isMobile) {
                wrapper.style.setProperty('bottom', '140px', 'important');
                wrapper.style.setProperty('right', '20px', 'important');
                wrapper.style.setProperty('left', 'auto', 'important');
                wrapper.style.setProperty('top', 'auto', 'important');
                
                // Fix overlap with Droplabs if present
                const hasDroplabs = document.querySelector('.droplabs-container, .droplabs-widget, .droplabs-bubble, [id*="droplabs"]');
                if (hasDroplabs) {
                    wrapper.style.setProperty('bottom', '200px', 'important');
                }
            } else {
                // Desktop positioning
                wrapper.style.setProperty('bottom', '20px', 'important');
                wrapper.style.setProperty('right', '20px', 'important');
                wrapper.style.setProperty('position', 'fixed', 'important');
            }
            
            // Ensure the bubble has the right size
            if (isMobile) {
                bubble.style.setProperty('width', '50px', 'important');
                bubble.style.setProperty('height', '50px', 'important');
            } else {
                bubble.style.setProperty('width', '60px', 'important');
                bubble.style.setProperty('height', '60px', 'important');
            }
            
            // Fix chat window positioning
            chatWindow.style.setProperty('bottom', '70px', 'important');
            chatWindow.style.setProperty('right', '0', 'important');
            
            if (isMobile) {
                chatWindow.style.setProperty('width', '85vw', 'important');
                chatWindow.style.setProperty('max-width', '350px', 'important');
                chatWindow.style.setProperty('height', '70vh', 'important');
            }
        },
        
        fixClickability: function(bubble, chatWindow) {
            // Remove any existing click handlers
            const oldBubble = bubble.cloneNode(true);
            bubble.parentNode.replaceChild(oldBubble, bubble);
            bubble = oldBubble; // Update the reference
            
            // Create oversized tap area for bubble
            const tapArea = document.createElement('div');
            tapArea.className = 'bc-tap-area';
            tapArea.style.cssText = `
                position: absolute !important;
                top: -20px !important;
                left: -20px !important;
                right: -20px !important;
                bottom: -20px !important;
                z-index: 2 !important;
                cursor: pointer !important;
                background-color: transparent !important;
            `;
            
            // Add tap area to bubble
            bubble.style.setProperty('position', 'relative', 'important');
            bubble.appendChild(tapArea);
            
            // Add new event listener using capture phase
            const toggleWindow = function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (chatWindow.style.display === 'flex') {
                    chatWindow.style.setProperty('display', 'none', 'important');
                } else {
                    chatWindow.style.setProperty('display', 'flex', 'important');
                    
                    // Focus input field if it exists
                    const input = chatWindow.querySelector('.bc-assistant-input');
                    if (input) {
                        setTimeout(function() {
                            input.focus();
                        }, 100);
                    }
                }
                
                return false;
            };
            
            // Add multiple event listeners to ensure clickability
            bubble.addEventListener('click', toggleWindow, true);
            tapArea.addEventListener('click', toggleWindow, true);
            
            // Add touch events
            bubble.addEventListener('touchend', toggleWindow, true);
            tapArea.addEventListener('touchend', toggleWindow, true);
            
            // Also add mousedown and touchstart
            bubble.addEventListener('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, true);
            
            tapArea.addEventListener('mousedown', function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, true);
            
            // Get close and minimize buttons
            const closeButton = chatWindow.querySelector('.bc-assistant-close');
            const minimizeButton = chatWindow.querySelector('.bc-assistant-minimize');
            
            // Add close handlers
            const closeWindow = function(e) {
                e.preventDefault();
                e.stopPropagation();
                chatWindow.style.setProperty('display', 'none', 'important');
                return false;
            };
            
            if (closeButton) {
                closeButton.addEventListener('click', closeWindow, true);
                closeButton.addEventListener('touchend', closeWindow, true);
            }
            
            if (minimizeButton) {
                minimizeButton.addEventListener('click', closeWindow, true);
                minimizeButton.addEventListener('touchend', closeWindow, true);
            }
            
            // Get send button
            const sendButton = chatWindow.querySelector('.bc-assistant-send');
            const inputField = chatWindow.querySelector('.bc-assistant-input');
            
            // Add send handler if original functionality exists
            if (sendButton && inputField && window.bcAssistant && typeof window.bcAssistant.sendMessage === 'function') {
                const sendMessage = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.bcAssistant.sendMessage();
                    return false;
                };
                
                sendButton.addEventListener('click', sendMessage, true);
                sendButton.addEventListener('touchend', sendMessage, true);
                
                // Handle Enter key in input field
                inputField.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        window.bcAssistant.sendMessage();
                        return false;
                    }
                });
            }
        },
        
        fixMobileIssues: function(wrapper, bubble, chatWindow) {
            // Check for mobile browser
            const isFirefox = navigator.userAgent.indexOf('Firefox') !== -1;
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            const isEdge = /Edge/.test(navigator.userAgent);
            const isMobile = window.innerWidth < 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (!isMobile) return; // Only apply mobile fixes on mobile devices
            
            // Common mobile fixes
            wrapper.style.setProperty('position', 'fixed', 'important');
            wrapper.style.setProperty('touch-action', 'auto', 'important');
            wrapper.style.setProperty('-webkit-tap-highlight-color', 'rgba(0,0,0,0)', 'important');
            
            bubble.style.setProperty('-webkit-user-select', 'none', 'important');
            bubble.style.setProperty('user-select', 'none', 'important');
            bubble.style.setProperty('touch-action', 'manipulation', 'important');
            
            // Firefox-specific mobile fixes
            if (isFirefox) {
                wrapper.style.setProperty('min-width', '50px', 'important');
                wrapper.style.setProperty('min-height', '50px', 'important');
                wrapper.style.setProperty('clip', 'auto', 'important');
                wrapper.style.setProperty('clip-path', 'none', 'important');
                
                bubble.style.setProperty('transform', 'none', 'important');
                bubble.style.setProperty('clip-path', 'none', 'important');
                
                // Apply stacking context fix
                const elements = [wrapper, bubble, chatWindow];
                elements.forEach(function(el) {
                    el.style.setProperty('transform', 'translateZ(0)', 'important');
                });
            }
            
            // Edge-specific mobile fixes
            if (isEdge) {
                wrapper.style.setProperty('transform', 'translateZ(0)', 'important');
                bubble.style.setProperty('transform', 'translateZ(0)', 'important');
                
                // Add explicit height and width
                bubble.style.setProperty('width', '50px', 'important');
                bubble.style.setProperty('height', '50px', 'important');
                
                // Force hardware acceleration
                elements = [wrapper, bubble, chatWindow];
                elements.forEach(function(el) {
                    el.style.setProperty('transform', 'translateZ(0)', 'important');
                    el.style.setProperty('-ms-transform', 'translateZ(0)', 'important');
                });
            }
            
            // Safari and iOS fixes
            if (isSafari) {
                bubble.style.setProperty('-webkit-user-select', 'none', 'important');
                bubble.style.setProperty('user-select', 'none', 'important');
                
                // Fix for iOS scrolling issues
                const messagesContainer = chatWindow.querySelector('.bc-assistant-messages');
                if (messagesContainer) {
                    messagesContainer.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
                }
            }
        }
    };
    
    // Run our fix immediately and after window loads
    BC_ASSISTANT.init();
    
    // Create a style element for critical CSS fixes
    const styleEl = document.createElement('style');
    styleEl.id = 'bc-assistant-emergency-fix';
    styleEl.textContent = `
        /* Critical fixes that override any other styles */
        .bc-assistant-wrapper {
            position: fixed !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            z-index: 2147483647 !important;
            pointer-events: auto !important;
            transform: none !important;
        }
        
        .bc-assistant-bubble {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            align-items: center !important;
            justify-content: center !important;
            cursor: pointer !important;
            z-index: 2147483647 !important;
            transform: none !important;
        }
        
        .bc-assistant-window {
            z-index: 2147483647 !important;
        }
        
        .bc-tap-area {
            position: absolute !important;
            top: -20px !important;
            left: -20px !important;
            right: -20px !important;
            bottom: -20px !important;
            z-index: 2 !important;
            cursor: pointer !important;
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
                
                .bc-assistant-bubble::before {
                    content: "" !important;
                    position: absolute !important;
                    top: -20px !important;
                    right: -20px !important;
                    bottom: -20px !important;
                    left: -20px !important;
                    z-index: 1 !important;
                }
            }
        }
        
        /* Mobile position adjustment */
        @media (max-width: 767px) {
            .bc-assistant-wrapper {
                bottom: 140px !important;
            }
            
            .bc-assistant-bubble {
                width: 50px !important;
                height: 50px !important;
                border: 2px solid #fff !important;
                box-shadow: 0 2px 10px rgba(0,0,0,0.3) !important;
            }
        }
    `;
    
    // Add the style element to the head
    document.head.appendChild(styleEl);
})();