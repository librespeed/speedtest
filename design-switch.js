/**
 * Feature switch for enabling the new LibreSpeed design
 * 
 * This script checks for:
 * 1. URL parameter: ?design=new or ?design=old
 * 2. Configuration file: config.json with useNewDesign flag
 * 
 * Default behavior: Shows the old design
 * 
 * Note: This script is only loaded on the root index.html
 */
(function() {
    'use strict';
    
    // Don't run this script if we're already on a specific design page
    // This prevents infinite redirect loops
    const currentPath = window.location.pathname;
    if (currentPath.includes('index-classic.html') || currentPath.includes('index-modern.html')) {
        return;
    }
    
    // Check URL parameters first (they override config)
    const urlParams = new URLSearchParams(window.location.search);
    const designParam = urlParams.get('design');
    
    if (designParam === 'new') {
        redirectToNewDesign();
        return;
    }
    
    if (designParam === 'old' || designParam === 'classic') {
        redirectToOldDesign();
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
            } else {
                redirectToOldDesign();
            }
        } else {
            // Config not found or error - default to old design
            redirectToOldDesign();
        }
    } catch (error) {
        // If there's any error (e.g., network, JSON parse), default to old design
        console.log('Using default (old) design:', error.message || 'config error');
        redirectToOldDesign();
    }
    
    function redirectToNewDesign() {
        // Preserve any URL parameters when redirecting
        const currentParams = window.location.search;
        window.location.href = 'index-modern.html' + currentParams;
    }
    
    function redirectToOldDesign() {
        // Preserve any URL parameters when redirecting
        const currentParams = window.location.search;
        window.location.href = 'index-classic.html' + currentParams;
    }
})();
