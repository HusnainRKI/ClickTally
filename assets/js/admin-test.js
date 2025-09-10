/**
 * ClickTally Admin Test Page JavaScript
 * For testing tracking functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Setup test page functionality
    setupTestPageHandlers();
});

function setupTestPageHandlers() {
    // Load test page button
    const loadBtn = document.querySelector('button[data-action="load-test-page"]');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadTestPage);
    }
    
    // Set default URL
    const testUrlInput = document.getElementById('test-url');
    if (testUrlInput && !testUrlInput.value) {
        testUrlInput.placeholder = window.location.origin;
    }
}

function loadTestPage() {
    const testUrlInput = document.getElementById('test-url');
    const testIframe = document.getElementById('test-iframe');
    
    if (testUrlInput && testIframe) {
        const url = testUrlInput.value || window.location.origin;
        testIframe.src = url;
        
        // Update results
        const resultsDiv = document.getElementById('test-results-content');
        if (resultsDiv) {
            resultsDiv.innerHTML = '<p>Page loaded. Interact with elements to see tracking results.</p>';
        }
    }
}