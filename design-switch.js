/**
 * Feature switch for enabling the new LibreSpeed design
 * 
 * This script checks for:
 * 1. URL parameter: ?design=new or ?design=old
 * 2. Configuration file: config.json with useNewDesign flag
 * 
 * Default behavior: Shows the old design
 */
(function() {
    'use strict';
    
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
    fetch('config.json')
        .then(response => {
            if (!response.ok) {
                // If config.json doesn't exist or can't be loaded, use old design
                return { useNewDesign: false };
            }
            return response.json();
        })
        .then(config => {
            if (config.useNewDesign === true) {
                redirectToNewDesign();
            }
            // Otherwise stay on old design (default)
        })
        .catch(() => {
            // If there's any error, default to old design
            console.log('Using default (old) design');
        });
    
    function redirectToNewDesign() {
        // Preserve any URL parameters when redirecting
        const currentParams = window.location.search;
        window.location.href = 'frontend/index.html' + currentParams;
    }
})();
