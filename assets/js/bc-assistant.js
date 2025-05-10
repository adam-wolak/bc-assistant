/**
 * BC Assistant - Unified Implementation (Dispatcher)
 * Version 2.0.0
 * 
 * This file determines which implementation to use (Shadow DOM or Traditional DOM)
 * based on the configuration option.
 */

// Set initialization flag to prevent duplicate initialization
window.bcAssistantInitialized = false;

// Initialize on document ready
jQuery(document).ready(function($) {
    // Check if already initialized
    if (window.bcAssistantInitialized) {
        return;
    }
    
    // Determine if we should use Shadow DOM
    const useShadowDOM = window.bcAssistantData && window.bcAssistantData.useShadowDOM;
    
    // Log for debugging
    if (window.bcAssistantData && window.bcAssistantData.debug) {
        console.log('BC Assistant: Initializing with ' + (useShadowDOM ? 'Shadow DOM' : 'Traditional DOM') + ' implementation');
        console.log('BC Assistant: Using model: ' + (window.bcAssistantData.model || 'default'));
    }
    
    // Initialize appropriate implementation
    // The actual initialization is handled in the respective implementation files
    // which are enqueued by WordPress based on the configuration
    
    // Mark as initialized
    window.bcAssistantInitialized = true;
});