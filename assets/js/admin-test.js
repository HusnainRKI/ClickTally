/**
 * ClickTally Admin Test Page JavaScript
 * For testing tracking functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Setup test page functionality
    setupTestPageHandlers();
    
    // Initialize test results tracking
    initializeTestTracking();
});

let testResults = [];
let pickerEnabled = false;
let currentSelector = '';

function setupTestPageHandlers() {
    // Load test page button
    const loadBtn = document.querySelector('button[data-action="load-test-page"]');
    if (loadBtn) {
        loadBtn.addEventListener('click', loadTestPage);
    }
    
    // Element picker toggle
    const pickerCheckbox = document.getElementById('enable-picker');
    if (pickerCheckbox) {
        pickerCheckbox.addEventListener('change', toggleElementPicker);
    }
    
    // Set default URL
    const testUrlInput = document.getElementById('test-url');
    if (testUrlInput && !testUrlInput.value) {
        testUrlInput.placeholder = window.location.origin;
    }
    
    // Handle Enter key in URL input
    if (testUrlInput) {
        testUrlInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadTestPage();
            }
        });
    }
}

function loadTestPage() {
    const testUrlInput = document.getElementById('test-url');
    const testIframe = document.getElementById('test-iframe');
    
    if (testUrlInput && testIframe) {
        const url = testUrlInput.value.trim() || window.location.origin;
        
        // Validate URL
        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            alert('Please enter a valid URL starting with http:// or https://');
            return;
        }
        
        // Show loading state
        const resultsDiv = document.getElementById('test-results-content');
        if (resultsDiv) {
            resultsDiv.innerHTML = '<p>Loading page...</p>';
        }
        
        // Load iframe
        testIframe.src = url;
        
        // Clear previous results
        testResults = [];
        updateTestResults();
        
        // Setup iframe load handler
        testIframe.onload = function() {
            try {
                setupIframeInteraction();
                if (resultsDiv) {
                    resultsDiv.innerHTML = '<p>Page loaded successfully. ' + 
                        (pickerEnabled ? 'Click elements to inspect them.' : 'Enable element picker to inspect elements.') + '</p>';
                }
            } catch (error) {
                console.error('Error setting up iframe interaction:', error);
                if (resultsDiv) {
                    resultsDiv.innerHTML = '<p class="error">Page loaded but may have cross-origin restrictions. Some features may not work.</p>';
                }
            }
        };
        
        testIframe.onerror = function() {
            if (resultsDiv) {
                resultsDiv.innerHTML = '<p class="error">Failed to load page. Please check the URL and try again.</p>';
            }
        };
    }
}

function toggleElementPicker() {
    const pickerCheckbox = document.getElementById('enable-picker');
    pickerEnabled = pickerCheckbox ? pickerCheckbox.checked : false;
    
    if (pickerEnabled) {
        setupIframeInteraction();
    }
    
    updateTestResults();
}

function setupIframeInteraction() {
    const testIframe = document.getElementById('test-iframe');
    if (!testIframe || !testIframe.contentWindow) return;
    
    try {
        const iframeDoc = testIframe.contentDocument || testIframe.contentWindow.document;
        
        if (pickerEnabled) {
            // Remove existing listeners
            iframeDoc.removeEventListener('click', handleIframeClick);
            iframeDoc.removeEventListener('mouseover', handleIframeMouseover);
            iframeDoc.removeEventListener('mouseout', handleIframeMouseout);
            
            // Add new listeners
            iframeDoc.addEventListener('click', handleIframeClick);
            iframeDoc.addEventListener('mouseover', handleIframeMouseover);
            iframeDoc.addEventListener('mouseout', handleIframeMouseout);
            
            // Add CSS for highlighting
            addPickerStyles(iframeDoc);
        }
    } catch (error) {
        console.error('Cannot access iframe content due to cross-origin restrictions:', error);
    }
}

function addPickerStyles(doc) {
    // Remove existing style
    const existingStyle = doc.getElementById('clicktally-picker-style');
    if (existingStyle) {
        existingStyle.remove();
    }
    
    // Add picker styles
    const style = doc.createElement('style');
    style.id = 'clicktally-picker-style';
    style.textContent = `
        .clicktally-highlight {
            outline: 2px solid #007cba !important;
            outline-offset: 2px !important;
            cursor: pointer !important;
        }
        .clicktally-selected {
            outline: 3px solid #d63638 !important;
            outline-offset: 2px !important;
        }
    `;
    doc.head.appendChild(style);
}

function handleIframeClick(e) {
    if (!pickerEnabled) return;
    
    e.preventDefault();
    e.stopPropagation();
    
    const element = e.target;
    currentSelector = generateSelector(element);
    
    // Highlight selected element
    clearHighlights(e.target.ownerDocument);
    element.classList.add('clicktally-selected');
    
    // Update test results
    const testResult = {
        timestamp: new Date().toLocaleTimeString(),
        element: element.tagName.toLowerCase(),
        selector: currentSelector,
        text: element.textContent ? element.textContent.trim().substring(0, 50) + '...' : '',
        id: element.id || '',
        classes: Array.from(element.classList).join(' '),
        matches: countSelectorMatches(currentSelector, e.target.ownerDocument)
    };
    
    testResults.unshift(testResult);
    updateTestResults();
}

function handleIframeMouseover(e) {
    if (!pickerEnabled) return;
    
    e.target.classList.add('clicktally-highlight');
}

function handleIframeMouseout(e) {
    if (!pickerEnabled) return;
    
    e.target.classList.remove('clicktally-highlight');
}

function clearHighlights(doc) {
    const highlighted = doc.querySelectorAll('.clicktally-highlight, .clicktally-selected');
    highlighted.forEach(el => {
        el.classList.remove('clicktally-highlight', 'clicktally-selected');
    });
}

function generateSelector(element) {
    // Simple selector generation
    if (element.id) {
        return '#' + element.id;
    }
    
    if (element.className) {
        const classes = Array.from(element.classList);
        if (classes.length > 0) {
            return '.' + classes[0];
        }
    }
    
    // Fallback to tag name
    return element.tagName.toLowerCase();
}

function countSelectorMatches(selector, doc) {
    try {
        return doc.querySelectorAll(selector).length;
    } catch (error) {
        return 0;
    }
}

function updateTestResults() {
    const resultsDiv = document.getElementById('test-results-content');
    if (!resultsDiv) return;
    
    if (testResults.length === 0) {
        resultsDiv.innerHTML = '<p>' + 
            (pickerEnabled ? 'Click elements in the iframe to inspect them.' : 'Enable element picker and load a page to start testing.') + 
            '</p>';
        return;
    }
    
    let html = '<h4>Element Inspection Results:</h4>';
    html += '<table class="wp-list-table widefat striped">';
    html += '<thead><tr>';
    html += '<th>Time</th><th>Element</th><th>Selector</th><th>Matches</th><th>Text Preview</th>';
    html += '</tr></thead><tbody>';
    
    testResults.slice(0, 10).forEach(result => {
        html += '<tr>';
        html += '<td>' + result.timestamp + '</td>';
        html += '<td>' + result.element + '</td>';
        html += '<td><code>' + result.selector + '</code></td>';
        html += '<td>' + result.matches + '</td>';
        html += '<td>' + (result.text || '<em>no text</em>') + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    
    if (testResults.length > 10) {
        html += '<p><em>Showing latest 10 results of ' + testResults.length + ' total.</em></p>';
    }
    
    html += '<p><button type="button" class="button" onclick="clearTestResults()">Clear Results</button></p>';
    
    resultsDiv.innerHTML = html;
}

function clearTestResults() {
    testResults = [];
    updateTestResults();
}

function initializeTestTracking() {
    // This could be enhanced to actually track events in real-time
    console.log('Test tracking initialized');
}