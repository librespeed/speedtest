/**
 * Feature switch for enabling the new LibreSpeed design
 * 
 * This script checks for:
 * 1. URL parameter: ?design=new or ?design=old
 * 2. Configuration file: config.json with useNewDesign flag
 * 
 * Default behavior: Shows the old design
 * 
 * Note: This script is only loaded on the root index.html, not on frontend/index.html
 */
(function() {
    'use strict';
    
    // Don't run this script if we're already in the frontend directory
    // This prevents infinite redirect loops
    if (window.location.pathname.includes('/frontend/')) {
        return;
    }
    
    // Check URL parameters first (they override config)
    const urlParams = new URLSearchParams(window.location.search);
    const designParam = urlParams.get('design');
    
    if (designParam === 'new') {
        redirectToNewDesign();
        return;
    }
    
    if (designParam === 'old') {
        // Stay on old design, don't check config
        return;
    }
    
    // Check config.json for design preference
    try {
        const xhr = new XMLHttpRequest();
        // Use a synchronous request to prevent a flash of the old design before redirecting
        xhr.open('GET', 'config.json', false);
        xhr.send(null);

        // Check for a successful response, but not 304 Not Modified, which can have an empty response body
        if (xhr.status >= 200 && xhr.status < 300) {
            const config = JSON.parse(xhr.responseText);
            if (config.useNewDesign === true) {
                redirectToNewDesign();
            }
        }
        // Otherwise, stay on the old design (default for 404, errors, or useNewDesign: false)
    } catch (error) {
        // If there's any error (e.g., network, JSON parse), default to old design
        console.log('Using default (old) design:', error.message || 'config error');
    }
    
    function redirectToNewDesign() {
        // Preserve any URL parameters when redirecting
        const currentParams = window.location.search;
        window.location.href = 'frontend/index.html' + currentParams;
    }
})();
