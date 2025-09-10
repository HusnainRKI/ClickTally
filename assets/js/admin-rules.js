/**
 * ClickTally Admin Event Rules JavaScript
 * For managing tracking events with event delegation and proper error handling
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Use event delegation for better handling of dynamic content
    setupEventDelegation();
    
    // Set up event form handling
    const eventForm = document.getElementById('event-form');
    if (eventForm) {
        eventForm.addEventListener('submit', handleEventFormSubmit);
    }
});

function setupEventDelegation() {
    // Use event delegation on document body to handle dynamically added buttons
    document.body.addEventListener('click', function(e) {
        const target = e.target;
        
        // Prevent default navigation for buttons
        if (target.tagName === 'BUTTON' || target.getAttribute('role') === 'button') {
            e.preventDefault();
        }
        
        // Handle different button actions
        if (target.getAttribute('data-action') === 'add-event') {
            e.preventDefault();
            openEventModal();
        } else if (target.getAttribute('data-action') === 'edit') {
            e.preventDefault();
            const eventId = target.getAttribute('data-rule-id');
            if (eventId) {
                editEvent(eventId);
            }
        } else if (target.getAttribute('data-action') === 'delete') {
            e.preventDefault();
            const eventId = target.getAttribute('data-rule-id');
            if (eventId) {
                deleteEvent(eventId);
            }
        } else if (target.getAttribute('data-action') === 'close-modal' || target.classList.contains('clicktally-modal-close')) {
            e.preventDefault();
            closeEventModal();
        } else if (target.getAttribute('data-action') === 'dom-picker') {
            e.preventDefault();
            openDOMPicker();
        }
    });
}

function openEventModal(eventId) {
    const modal = document.getElementById('event-modal');
    if (modal) {
        modal.style.display = 'block';
        if (eventId) {
            // Load event data for editing
            loadEventData(eventId);
        } else {
            // Reset form for new event
            const form = document.getElementById('event-form');
            if (form) {
                form.reset();
            }
            const eventIdField = document.getElementById('event-id');
            if (eventIdField) {
                eventIdField.value = '';
            }
        }
    }
}

function closeEventModal() {
    const modal = document.getElementById('event-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openDOMPicker() {
    // Basic DOM picker implementation
    // In a real implementation, this would open a modal with an iframe
    
    const selectorValueField = document.getElementById('selector-value');
    if (!selectorValueField) return;
    
    // For now, provide a simple prompt-based approach
    const selector = prompt('Enter a CSS selector or element ID/class:\n\nExamples:\n- #my-button (for ID)\n- .my-class (for class)\n- button.primary (for CSS selector)\n- //button[@id="submit"] (for XPath)');
    
    if (selector && selector.trim()) {
        selectorValueField.value = selector.trim();
        
        // Try to determine selector type automatically
        const selectorTypeField = document.getElementById('selector-type');
        if (selectorTypeField) {
            if (selector.startsWith('#')) {
                selectorTypeField.value = 'id';
                selectorValueField.value = selector.substring(1); // Remove the #
            } else if (selector.startsWith('.') && !selector.includes(' ')) {
                selectorTypeField.value = 'class';
                selectorValueField.value = selector.substring(1); // Remove the .
            } else if (selector.startsWith('//')) {
                selectorTypeField.value = 'xpath';
            } else if (selector.includes('[data-')) {
                selectorTypeField.value = 'data';
            } else {
                selectorTypeField.value = 'css';
            }
        }
        
        // Auto-generate event name if empty
        const eventNameField = document.getElementById('event-name');
        if (eventNameField && !eventNameField.value.trim()) {
            let autoName = selector;
            if (selector.startsWith('#')) {
                autoName = selector.substring(1) + ' Click';
            } else if (selector.startsWith('.')) {
                autoName = selector.substring(1).replace(/-/g, ' ') + ' Click';
            } else {
                autoName = 'Element Click';
            }
            eventNameField.value = autoName.charAt(0).toUpperCase() + autoName.slice(1);
        }
    }
}

function loadEventData(eventId) {
    // For now, we'll implement a simple approach that populates the form
    // In a real implementation, this would fetch data via AJAX
    
    // Show loading in modal
    const modal = document.getElementById('event-modal');
    if (modal) {
        const form = document.getElementById('event-form');
        if (form) {
            // Set the event ID
            const eventIdField = document.getElementById('event-id');
            if (eventIdField) {
                eventIdField.value = eventId;
            }
            
            // TODO: Fetch actual event data from server
            // For now, just log that we're loading
            console.log('Loading event data for ID:', eventId);
            
            // In a future implementation, we would:
            // 1. Make an AJAX request to get the event data
            // 2. Populate the form fields with the returned data
            // 3. Handle any errors appropriately
        }
    }
}

function editEvent(eventId) {
    openEventModal(eventId);
}

function deleteEvent(eventId) {
    if (confirm(clickTallyAdmin.strings.confirmDelete)) {
        // Show loading state
        const deleteBtn = document.querySelector(`button[data-rule-id="${eventId}"][data-action="delete"]`);
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting...';
        }
        
        // Submit deletion via REST API
        const url = clickTallyAdmin.apiUrl + 'rules/manage';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': clickTallyAdmin.nonce
            },
            body: JSON.stringify({
                action: 'delete',
                rule_id: eventId
            })
        })
        .then(response => {
            if (!response.ok) {
                // Handle different error types
                if (response.status === 403) {
                    throw new Error('Permission denied. Check your capabilities.');
                } else if (response.status === 404) {
                    throw new Error('REST endpoint not found. Try flushing permalinks.');
                } else {
                    throw new Error('Network response was not ok: ' + response.status);
                }
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Show success message
                alert(clickTallyAdmin.strings.eventDeleted);
                
                // Refresh the page to show updated list
                location.reload();
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error deleting event:', error);
            alert(clickTallyAdmin.strings.errorGeneral);
        })
        .finally(() => {
            // Restore button state
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Delete';
            }
        });
    }
}

function handleEventFormSubmit(e) {
    e.preventDefault();
    
    const form = document.getElementById('event-form');
    if (!form) return;
    
    // Validate form
    const selectorType = form.querySelector('#selector-type').value;
    const selectorValue = form.querySelector('#selector-value').value.trim();
    const eventName = form.querySelector('#event-name').value.trim();
    
    if (!selectorType || !selectorValue || !eventName) {
        alert('Please fill in all required fields.');
        return;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Saving...';
    
    // Prepare data
    const eventId = form.querySelector('#event-id').value;
    const formData = new FormData(form);
    const eventData = {
        selector_type: selectorType,
        selector_value: selectorValue,
        event_name: eventName,
        event_type: formData.get('event_type') || 'click',
        label_template: formData.get('label_template') || '',
        throttle_ms: parseInt(formData.get('throttle_ms')) || 0,
        once_per_view: formData.get('once_per_view') ? true : false
    };
    
    // Submit via REST API
    const url = clickTallyAdmin.apiUrl + 'rules/manage';
    const isUpdate = eventId && eventId !== '';
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': clickTallyAdmin.nonce
        },
        body: JSON.stringify({
            action: isUpdate ? 'update' : 'create',
            rule_id: isUpdate ? eventId : undefined,
            ...eventData
        })
    })
    .then(response => {
        if (!response.ok) {
            // Handle different error types
            if (response.status === 403) {
                throw new Error('Permission denied. Check your capabilities.');
            } else if (response.status === 404) {
                throw new Error('REST endpoint not found. Try flushing permalinks.');
            } else {
                throw new Error('Network response was not ok: ' + response.status);
            }
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            alert(isUpdate ? clickTallyAdmin.strings.eventUpdated : clickTallyAdmin.strings.eventAdded);
            
            // Close modal
            closeEventModal();
            
            // Refresh the page to show updated list
            location.reload();
        } else {
            throw new Error(data.message || 'Unknown error');
        }
    })
    .catch(error => {
        console.error('Error saving event:', error);
        alert(clickTallyAdmin.strings.errorGeneral);
    })
    .finally(() => {
        // Restore button state
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}