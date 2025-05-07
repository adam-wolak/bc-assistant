/**
 * BC Assistant - Complete Fix
 * 
 * This script resolves:
 * 1. Click handling issues
 * 2. External script errors (CORS)
 * 3. Floating element conflicts
 * 4. Cross-browser compatibility
 */

(function() {
    "use strict";
    
    // Configuration
    const BC_ASSISTANT_FIX = {
        // Wait for DOM to fully load
        init: function() {
            // Remove problematic external scripts
            this.removeExternalTrackers();
            
            // Apply fixes when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', this.applyFixes.bind(this));
            } else {
                this.applyFixes();
            }
            
            // Also apply fixes when window loads (for late-loaded resources)
            window.addEventListener('load', this.applyFixes.bind(this));
            
            // Set up periodic check to ensure fixes remain applied
            setInterval(this.applyFixes.bind(this), 2000);
        },
        
        // Remove problematic external scripts causing CORS errors
        removeExternalTrackers: function() {
            // Find and remove external scripts that cause CORS errors
            const scriptSelectors = [
                'script[src*="amplitude"]',
                'script[src*="cloudflareinsights"]'
            ];
            
            // Check for scripts in head and remove them
            scriptSelectors.forEach(selector => {
                const scripts = document.querySelectorAll(selector);
                scripts.forEach(script => {
                    if (script && script.parentNode) {
                        script.parentNode.removeChild(script);
                        console.log('BC Assistant Fix: Removed problematic script:', selector);
                    }
                });
            });
        },
        
        // Apply all necessary fixes to make the assistant work
        applyFixes: function() {
            // Find the assistant wrapper
            const wrapper = document.querySelector('.bc-assistant-wrapper');
            if (!wrapper) {
                console.log('BC Assistant Fix: Wrapper not found, waiting...');
                return;
            }
            
            // Find the bubble and chat window
            const bubble = wrapper.querySelector('.bc-assistant-bubble');
            const chatWindow = wrapper.querySelector('.bc-assistant-window');
            
            if (!bubble || !chatWindow) {
                console.log('BC Assistant Fix: Bubble or window not found');
                return;
            }
            
            console.log('BC Assistant Fix: Components found, applying fixes');
            
            // 1. Fix visibility and positioning
            this.fixVisibility(wrapper, bubble, chatWindow);
            
            // 2. Fix click handling
            this.fixClickHandling(bubble, chatWindow);
            
            // 3. Check for other floating elements and adjust position
            this.adjustForFloatingElements(wrapper, bubble);
            
            // 4. Apply browser-specific fixes
            this.applyBrowserFixes(wrapper, bubble, chatWindow);
            
            console.log('BC Assistant Fix: Applied all fixes');
        },
        
        // Fix visibility issues
        fixVisibility: function(wrapper, bubble, chatWindow) {
            // Use maximum z-index to ensure visibility
            const maxZIndex = 2147483647;
            
            // Fix wrapper visibility
            wrapper.style.setProperty('position', 'fixed', 'important');
            wrapper.style.setProperty('display', 'block', 'important');
            wrapper.style.setProperty('visibility', 'visible', 'important');
            wrapper.style.setProperty('opacity', '1', 'important');
            wrapper.style.setProperty('z-index', maxZIndex, 'important');
            wrapper.style.setProperty('pointer-events', 'auto', 'important');
            
            // Fix bubble visibility
            bubble.style.setProperty('display', 'flex', 'important');
            bubble.style.setProperty('visibility', 'visible', 'important');
            bubble.style.setProperty('opacity', '1', 'important');
            bubble.style.setProperty('align-items', 'center', 'important');
            bubble.style.setProperty('justify-content', 'center', 'important');
            bubble.style.setProperty('z-index', maxZIndex, 'important');
            bubble.style.setProperty('cursor', 'pointer', 'important');
            bubble.style.setProperty('position', 'relative', 'important');
            
            // Fix chat window z-index
            chatWindow.style.setProperty('z-index', maxZIndex, 'important');
        },
        
        // Fix click handling
        fixClickHandling: function(bubble, chatWindow) {
            // Don't add duplicate handlers
            if (bubble.hasAttribute('data-fixed')) {
                return;
            }
            
            // Mark as fixed to prevent duplicate handlers
            bubble.setAttribute('data-fixed', 'true');
            
            // Create an enhanced tap area for better touch detection
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
            
            // Add to bubble if it doesn't already have one
            if (!bubble.querySelector('.bc-tap-area')) {
                bubble.appendChild(tapArea);
            }
            
            // Clear old event listeners by cloning and replacing elements
            const newBubble = bubble.cloneNode(true);
            bubble.parentNode.replaceChild(newBubble, bubble);
            
            // Get new references
            bubble = newBubble;
            tapArea = bubble.querySelector('.bc-tap-area');
            
            // Create reliable toggle function
            const toggleWindow = function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('BC Assistant Fix: Bubble clicked');
                
                if (chatWindow.style.display === 'flex') {
                    chatWindow.style.setProperty('display', 'none', 'important');
                } else {
                    chatWindow.style.setProperty('display', 'flex', 'important');
                    
                    // Focus input field if it exists
                    const input = chatWindow.querySelector('.bc-assistant-input');
                    if (input) {
                        setTimeout(() => {
                            input.focus();
                        }, 100);
                    }
                    
                    // Make sure messages container is scrolled to bottom
                    const messagesContainer = chatWindow.querySelector('.bc-assistant-messages');
                    if (messagesContainer) {
                        setTimeout(() => {
                            messagesContainer.scrollTop = messagesContainer.scrollHeight;
                        }, 100);
                    }
                }
                
                return false;
            };
            
            // Add multiple event types to ensure clickability
            this.addMultipleEventListeners(bubble, ['click', 'touchend', 'mousedown'], toggleWindow);
            
            if (tapArea) {
                this.addMultipleEventListeners(tapArea, ['click', 'touchend', 'mousedown'], toggleWindow);
            }
            
            // Fix window controls (close and minimize buttons)
            this.fixWindowControls(chatWindow);
            
            console.log('BC Assistant Fix: Click handlers applied');
        },
        
        // Fix window control buttons
        fixWindowControls: function(chatWindow) {
            // Get close and minimize buttons
            const closeButton = chatWindow.querySelector('.bc-assistant-close');
            const minimizeButton = chatWindow.querySelector('.bc-assistant-minimize');
            
            // Function to close window
            const closeWindow = function(e) {
                e.preventDefault();
                e.stopPropagation();
                chatWindow.style.setProperty('display', 'none', 'important');
                return false;
            };
            
            // Add event listeners to close button
            if (closeButton) {
                this.addMultipleEventListeners(closeButton, ['click', 'touchend'], closeWindow);
            }
            
            // Add event listeners to minimize button
            if (minimizeButton) {
                this.addMultipleEventListeners(minimizeButton, ['click', 'touchend'], closeWindow);
            }
        },
        
        // Helper to add multiple event listeners
        addMultipleEventListeners: function(element, events, handler) {
            if (!element) return;
            
            events.forEach(eventName => {
                element.addEventListener(eventName, handler, { capture: true, passive: false });
            });
        },
        
        // Check for other floating elements and adjust position
        adjustForFloatingElements: function(wrapper, bubble) {
            // Common selectors for floating elements
            const selectors = [
                '[class*="chat"]:not(.bc-assistant-wrapper *)',
                '[class*="widget"]:not(.bc-assistant-wrapper *)',
                '[class*="bubble"]:not(.bc-assistant-wrapper *)',
                '[class*="buy"]:not(.bc-assistant-wrapper *)',
                '[class*="kup"]:not(.bc-assistant-wrapper *)',
                '[id*="chat"]:not(.bc-assistant-wrapper *)',
                '[id*="widget"]:not(.bc-assistant-wrapper *)'
            ];
            
            // Find potential conflicting elements
            const elements = document.querySelectorAll(selectors.join(','));
            
            // Check if any floating elements are at the bottom right
            let hasBottomRightElements = false;
            elements.forEach(el => {
                const rect = el.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                const viewportWidth = window.innerWidth;
                
                // Check if element is in bottom right quadrant
                if (rect.bottom > viewportHeight - 200 && rect.right > viewportWidth - 200) {
                    hasBottomRightElements = true;
                }
            });
            
            // Get current position
            const position = wrapper.getAttribute('data-position') || 'bottom-right';
            
            // Adjust position based on device type and conflicts
            const isMobile = window.innerWidth < 768 || 
                /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobile) {
                // Mobile positioning - higher up to avoid other elements
                wrapper.style.setProperty('bottom', hasBottomRightElements ? '200px' : '140px', 'important');
                
                // Set bubble size for mobile
                bubble.style.setProperty('width', '50px', 'important');
                bubble.style.setProperty('height', '50px', 'important');
                bubble.style.setProperty('border', '2px solid #fff', 'important'); // Better visibility
                bubble.style.setProperty('box-shadow', '0 2px 10px rgba(0,0,0,0.3)', 'important');
            } else {
                // Desktop positioning
                if (hasBottomRightElements && position.includes('bottom-right')) {
                    // Move to bottom left if there are conflicts
                    wrapper.style.setProperty('bottom', '20px', 'important');
                    wrapper.style.setProperty('left', '20px', 'important');
                    wrapper.style.setProperty('right', 'auto', 'important');
                } else {
                    // Use configured position
                    if (position === 'bottom-right') {
                        wrapper.style.setProperty('bottom', '20px', 'important');
                        wrapper.style.setProperty('right', '20px', 'important');
                        wrapper.style.setProperty('left', 'auto', 'important');
                        wrapper.style.setProperty('top', 'auto', 'important');
                    } else if (position === 'bottom-left') {
                        wrapper.style.setProperty('bottom', '20px', 'important');
                        wrapper.style.setProperty('left', '20px', 'important');
                        wrapper.style.setProperty('right', 'auto', 'important');
                        wrapper.style.setProperty('top', 'auto', 'important');
                    } else if (position === 'top-right') {
                        wrapper.style.setProperty('top', '20px', 'important');
                        wrapper.style.setProperty('right', '20px', 'important');
                        wrapper.style.setProperty('bottom', 'auto', 'important');
                        wrapper.style.setProperty('left', 'auto', 'important');
                    } else if (position === 'top-left') {
                        wrapper.style.setProperty('top', '20px', 'important');
                        wrapper.style.setProperty('left', '20px', 'important');
                        wrapper.style.setProperty('bottom', 'auto', 'important');
                        wrapper.style.setProperty('right', 'auto', 'important');
                    }
                }
            }
        },
        
        // Apply browser-specific fixes
        applyBrowserFixes: function(wrapper, bubble, chatWindow) {
            const isFirefox = navigator.userAgent.indexOf('Firefox') !== -1;
            const isEdge = /Edge|Edg/.test(navigator.userAgent);
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            const isMobile = window.innerWidth < 768 || 
                /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            // Firefox-specific fixes
            if (isFirefox) {
                wrapper.style.setProperty('transform', 'none', 'important');
                bubble.style.setProperty('transform', 'none', 'important');
                
                if (isMobile) {
                    // Critical Firefox mobile fixes
                    wrapper.style.setProperty('min-width', '50px', 'important');
                    wrapper.style.setProperty('min-height', '50px', 'important');
                    wrapper.style.setProperty('clip-path', 'none', 'important');
                    wrapper.style.setProperty('clip', 'auto', 'important');
                    wrapper.style.setProperty('pointer-events', 'auto', 'important');
                    
                    bubble.style.setProperty('clip-path', 'none', 'important');
                    bubble.style.setProperty('pointer-events', 'auto', 'important');
                    
                    // Create a special touch target for Firefox Mobile
                    if (!bubble.querySelector('.ff-touch-helper')) {
                        const ffTouchHelper = document.createElement('div');
                        ffTouchHelper.className = 'ff-touch-helper';
                        ffTouchHelper.style.cssText = `
                            position: absolute;
                            top: -25px;
                            left: -25px;
                            right: -25px;
                            bottom: -25px;
                            z-index: 3;
                            background-color: transparent;
                        `;
                        bubble.appendChild(ffTouchHelper);
                        
                        // Add click handlers to FF helper
                        ffTouchHelper.addEventListener('touchstart', function(e) {
                            e.stopPropagation();
                            
                            // Manually trigger a click on the bubble
                            const clickEvent = new MouseEvent('click', {
                                bubbles: true,
                                cancelable: true,
                                view: window
                            });
                            bubble.dispatchEvent(clickEvent);
                            
                            return false;
                        }, { capture: true, passive: false });
                    }
                }
            }
            
            // Edge-specific fixes
            if (isEdge) {
                // Force hardware acceleration
                wrapper.style.setProperty('transform', 'translateZ(0)', 'important');
                bubble.style.setProperty('transform', 'translateZ(0)', 'important');
                
                if (isMobile) {
                    // Ensure proper sizing in Edge mobile
                    bubble.style.setProperty('width', '50px', 'important');
                    bubble.style.setProperty('height', '50px', 'important');
                    bubble.style.setProperty('display', 'flex', 'important');
                    bubble.style.setProperty('align-items', 'center', 'important');
                    bubble.style.setProperty('justify-content', 'center', 'important');
                }
            }
            
            // Safari-specific fixes
            if (isSafari) {
                wrapper.style.setProperty('-webkit-tap-highlight-color', 'rgba(0,0,0,0)', 'important');
                bubble.style.setProperty('-webkit-user-select', 'none', 'important');
                bubble.style.setProperty('user-select', 'none', 'important');
                
                if (isMobile) {
                    // Improve scrolling on Safari
                    const messagesContainer = chatWindow.querySelector('.bc-assistant-messages');
                    if (messagesContainer) {
                        messagesContainer.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
                    }
                }
            }
        }
    };
    
    // Initialize the fixes
    BC_ASSISTANT_FIX.init();
    
    // Add this script to the global scope so it can be accessed for debugging
    window.BC_ASSISTANT_FIX = BC_ASSISTANT_FIX;
    
})();