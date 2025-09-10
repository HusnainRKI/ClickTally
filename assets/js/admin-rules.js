/**
 * ClickTally Rules Admin JavaScript
 * Modern AJAX-based CRUD implementation for event tracking rules
 */

// Global namespace for rules admin functionality
window.ClickTallyRulesAdmin = (function() {
    'use strict';
    
    // Private variables
    let isFormVisible = true;
    let currentEditingRuleId = null;
    let isSubmitting = false;
    
    // DOM elements (cached for performance)
    const elements = {};
    
    /**
     * Initialize the rules admin interface
     */
    function init() {
        cacheElements();
        bindEvents();
        updateFormVisibility();
        updateSelectorHelp();
    }
    
    /**
     * Cache DOM elements for better performance
     */
    function cacheElements() {
        elements.formPanel = document.getElementById('clicktally-form-panel');
        elements.formTitle = document.getElementById('clicktally-form-title');
        elements.formToggle = document.getElementById('clicktally-form-toggle');
        elements.formBody = document.getElementById('clicktally-form-body');
        elements.eventForm = document.getElementById('clicktally-event-form');
        elements.rulesList = document.getElementById('clicktally-rules-list');
        elements.messagesContainer = document.getElementById('clicktally-messages-container');
        elements.addNewBtn = document.getElementById('add-new-event-btn');
        elements.cancelBtn = document.getElementById('cancel-event-btn');
        elements.saveBtn = document.getElementById('save-event-btn');
        elements.selectorType = document.getElementById('selector-type');
        elements.selectorValue = document.getElementById('selector-value');
        elements.selectorPickerBtn = document.getElementById('selector-picker-btn');
        elements.selectorHelpText = document.getElementById('selector-help-text');
        elements.eventName = document.getElementById('event-name');
        elements.eventId = document.getElementById('event-id');
    }
    
    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Form toggle
        if (elements.formToggle) {
            elements.formToggle.addEventListener('click', toggleForm);
        }
        
        // Add new event button
        if (elements.addNewBtn) {
            elements.addNewBtn.addEventListener('click', showAddForm);
        }
        
        // Cancel button
        if (elements.cancelBtn) {
            elements.cancelBtn.addEventListener('click', cancelEdit);
        }
        
        // Form submission
        if (elements.eventForm) {
            elements.eventForm.addEventListener('submit', handleFormSubmit);
        }
        
        // Selector type change
        if (elements.selectorType) {
            elements.selectorType.addEventListener('change', updateSelectorHelp);
        }
        
        // Selector picker
        if (elements.selectorPickerBtn) {
            elements.selectorPickerBtn.addEventListener('click', openSelectorPicker);
        }
        
        // Event delegation for table actions
        if (elements.rulesList) {
            elements.rulesList.addEventListener('click', handleTableAction);
        }
        
        // Auto-generate event name from selector
        if (elements.selectorValue && elements.eventName) {
            elements.selectorValue.addEventListener('input', autoGenerateEventName);
        }
    }
    
    /**
     * Toggle form visibility
     */
    function toggleForm() {
        isFormVisible = !isFormVisible;
        updateFormVisibility();
    }
    
    /**
     * Update form visibility state
     */
    function updateFormVisibility() {
        if (!elements.formPanel || !elements.formToggle) return;
        
        const icon = elements.formToggle.querySelector('.dashicons');
        
        if (isFormVisible) {
            elements.formBody.style.display = 'block';
            elements.formPanel.classList.remove('collapsed');
            icon.className = 'dashicons dashicons-minus';
            elements.formToggle.title = 'Hide Form';
        } else {
            elements.formBody.style.display = 'none';
            elements.formPanel.classList.add('collapsed');
            icon.className = 'dashicons dashicons-plus-alt2';
            elements.formToggle.title = 'Show Form';
        }
    }
    
    /**
     * Show form for adding new event
     */
    function showAddForm() {
        resetForm();
        currentEditingRuleId = null;
        elements.formTitle.textContent = 'Add New Tracking Event';
        elements.saveBtn.textContent = 'Save Event';
        
        if (!isFormVisible) {
            isFormVisible = true;
            updateFormVisibility();
        }
        
        // Focus on event name field
        if (elements.eventName) {
            elements.eventName.focus();
        }
    }
    
    /**
     * Cancel editing and hide form
     */
    function cancelEdit() {
        resetForm();
        currentEditingRuleId = null;
        
        if (hasExistingRules()) {
            isFormVisible = false;
            updateFormVisibility();
        }
    }
    
    /**
     * Check if there are existing rules
     */
    function hasExistingRules() {
        const table = elements.rulesList.querySelector('.clicktally-rules-table');
        return table && table.querySelector('tbody tr');
    }
    
    /**
     * Reset form to initial state
     */
    function resetForm() {
        if (elements.eventForm) {
            elements.eventForm.reset();
        }
        if (elements.eventId) {
            elements.eventId.value = '';
        }
        updateSelectorHelp();
        clearMessages();
    }
    
    /**
     * Handle form submission
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) {
            return;
        }
        
        if (!validateForm()) {
            return;
        }
        
        const formData = new FormData(elements.eventForm);
        const eventData = {
            event_name: formData.get('event_name'),
            selector_type: formData.get('selector_type'),
            selector_value: formData.get('selector_value'),
            event_type: formData.get('event_type'),
            label_template: formData.get('label_template') || '',
            throttle_ms: parseInt(formData.get('throttle_ms')) || 0,
            once_per_view: formData.get('once_per_view') ? true : false
        };
        
        const isUpdate = currentEditingRuleId !== null;
        
        setFormLoading(true);
        
        const requestData = {
            action: isUpdate ? 'update' : 'create',
            ...eventData
        };
        
        if (isUpdate) {
            requestData.rule_id = currentEditingRuleId;
        }
        
        // Make API request
        fetch(clickTallyAdmin.apiUrl + 'rules/manage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': clickTallyAdmin.nonce
            },
            body: JSON.stringify(requestData)
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 403) {
                    throw new Error('Permission denied. Please check your user capabilities.');
                } else if (response.status === 404) {
                    throw new Error('REST endpoint not found. Try refreshing the page or contact support.');
                } else {
                    throw new Error('Request failed with status: ' + response.status);
                }
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const message = isUpdate ? 'Event updated successfully!' : 'Event created successfully!';
                showMessage(message, 'success');
                
                // Refresh the rules list
                refreshRulesList();
                
                // Reset form and hide it if we have rules
                resetForm();
                currentEditingRuleId = null;
                
                if (hasExistingRules()) {
                    isFormVisible = false;
                    updateFormVisibility();
                }
            } else {
                throw new Error(data.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Form submission error:', error);
            showMessage(error.message || 'An error occurred while saving the event.', 'error');
        })
        .finally(() => {
            setFormLoading(false);
        });
    }
    
    /**
     * Validate form data
     */
    function validateForm() {
        const eventName = elements.eventName.value.trim();
        const selectorType = elements.selectorType.value;
        const selectorValue = elements.selectorValue.value.trim();
        
        if (!eventName) {
            showMessage('Event name is required.', 'error');
            elements.eventName.focus();
            return false;
        }
        
        if (!selectorType) {
            showMessage('Selector type is required.', 'error');
            elements.selectorType.focus();
            return false;
        }
        
        if (!selectorValue) {
            showMessage('Selector value is required.', 'error');
            elements.selectorValue.focus();
            return false;
        }
        
        return true;
    }
    
    /**
     * Set form loading state
     */
    function setFormLoading(loading) {
        isSubmitting = loading;
        
        if (elements.saveBtn) {
            elements.saveBtn.disabled = loading;
            elements.saveBtn.textContent = loading ? 'Saving...' : 
                (currentEditingRuleId ? 'Update Event' : 'Save Event');
        }
        
        if (elements.cancelBtn) {
            elements.cancelBtn.disabled = loading;
        }
        
        // Disable form inputs
        const inputs = elements.eventForm.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => {
            if (input !== elements.saveBtn && input !== elements.cancelBtn) {
                input.disabled = loading;
            }
        });
    }
    
    /**
     * Handle table action clicks (edit, delete)
     */
    function handleTableAction(e) {
        const action = e.target.getAttribute('data-action');
        const ruleId = e.target.getAttribute('data-rule-id');
        
        if (!action || !ruleId) {
            return;
        }
        
        e.preventDefault();
        
        switch (action) {
            case 'edit-rule':
                editRule(ruleId);
                break;
            case 'delete-rule':
                const eventName = e.target.getAttribute('data-event-name');
                deleteRule(ruleId, eventName);
                break;
        }
    }
    
    /**
     * Edit a rule
     */
    function editRule(ruleId) {
        // Find the rule data from the table
        const row = document.querySelector(`tr[data-rule-id="${ruleId}"]`);
        if (!row) {
            showMessage('Rule not found.', 'error');
            return;
        }
        
        // For now, we'll need to fetch the rule data via API
        // This is a simplified implementation - in production you'd fetch full rule data
        showMessage('Loading rule data...', 'info');
        
        fetch(clickTallyAdmin.apiUrl + 'rules/get?id=' + ruleId, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': clickTallyAdmin.nonce
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load rule data');
            }
            return response.json();
        })
        .then(ruleData => {
            loadRuleIntoForm(ruleData);
        })
        .catch(error => {
            console.error('Error loading rule:', error);
            // Fallback: try to extract basic data from the table
            loadBasicRuleDataFromTable(ruleId);
        });
    }
    
    /**
     * Load rule data into form (fallback method)
     */
    function loadBasicRuleDataFromTable(ruleId) {
        const row = document.querySelector(`tr[data-rule-id="${ruleId}"]`);
        if (!row) return;
        
        const eventName = row.querySelector('.event-name strong').textContent.trim();
        const selectorText = row.querySelector('.selector-display').textContent.trim();
        const eventType = row.querySelector('.event-type').textContent.toLowerCase().trim();
        
        // Parse selector text (format: "type: value")
        const selectorParts = selectorText.split(':', 2);
        const selectorType = selectorParts[0].trim();
        const selectorValue = selectorParts[1] ? selectorParts[1].trim() : '';
        
        // Populate form
        currentEditingRuleId = ruleId;
        elements.eventId.value = ruleId;
        elements.eventName.value = eventName;
        elements.selectorType.value = selectorType;
        elements.selectorValue.value = selectorValue;
        document.getElementById('event-type').value = eventType;
        
        // Update form UI
        elements.formTitle.textContent = 'Edit Tracking Event';
        elements.saveBtn.textContent = 'Update Event';
        
        // Show form
        if (!isFormVisible) {
            isFormVisible = true;
            updateFormVisibility();
        }
        
        clearMessages();
    }
    
    /**
     * Load rule data into form
     */
    function loadRuleIntoForm(ruleData) {
        currentEditingRuleId = ruleData.id;
        elements.eventId.value = ruleData.id;
        elements.eventName.value = ruleData.event_name || '';
        elements.selectorType.value = ruleData.selector_type || 'id';
        elements.selectorValue.value = ruleData.selector_value || '';
        document.getElementById('event-type').value = ruleData.event_type || 'click';
        document.getElementById('label-template').value = ruleData.label_template || '';
        document.getElementById('throttle-ms').value = ruleData.throttle_ms || 0;
        document.getElementById('once-per-view').checked = ruleData.once_per_view || false;
        
        // Update form UI
        elements.formTitle.textContent = 'Edit Tracking Event';
        elements.saveBtn.textContent = 'Update Event';
        
        // Show form
        if (!isFormVisible) {
            isFormVisible = true;
            updateFormVisibility();
        }
        
        updateSelectorHelp();
        clearMessages();
    }
    
    /**
     * Delete a rule
     */
    function deleteRule(ruleId, eventName) {
        const message = eventName ? 
            `Are you sure you want to delete the event "${eventName}"?` :
            'Are you sure you want to delete this event?';
            
        if (!confirm(message + '\n\nThis action cannot be undone.')) {
            return;
        }
        
        const deleteBtn = document.querySelector(`button[data-rule-id="${ruleId}"][data-action="delete-rule"]`);
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting...';
        }
        
        fetch(clickTallyAdmin.apiUrl + 'rules/manage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': clickTallyAdmin.nonce
            },
            body: JSON.stringify({
                action: 'delete',
                rule_id: ruleId
            })
        })
        .then(response => {
            if (!response.ok) {
                if (response.status === 403) {
                    throw new Error('Permission denied. You do not have permission to delete rules.');
                } else {
                    throw new Error('Delete request failed');
                }
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showMessage('Event deleted successfully!', 'success');
                refreshRulesList();
                
                // If we were editing this rule, clear the form
                if (currentEditingRuleId === ruleId) {
                    cancelEdit();
                }
            } else {
                throw new Error(data.message || 'Delete failed');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            showMessage(error.message || 'Failed to delete event.', 'error');
        })
        .finally(() => {
            if (deleteBtn) {
                deleteBtn.disabled = false;
                deleteBtn.textContent = 'Delete';
            }
        });
    }
    
    /**
     * Refresh the rules list
     */
    function refreshRulesList() {
        // For now, reload the page to refresh the list
        // In a full implementation, you'd fetch and re-render the table via AJAX
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
    
    /**
     * Update selector help text based on selected type
     */
    function updateSelectorHelp() {
        if (!elements.selectorType || !elements.selectorHelpText) return;
        
        const selectorType = elements.selectorType.value;
        const helpTexts = {
            'id': 'Enter the element ID (without #). Example: signup-button',
            'class': 'Enter the CSS class name (without .). Example: btn-primary',
            'css': 'Enter a CSS selector. Example: .header .nav-menu a',
            'xpath': 'Enter an XPath expression. Example: //button[@id="submit"]',
            'data': 'Enter the data attribute name. Example: action (for data-action)'
        };
        
        elements.selectorHelpText.textContent = helpTexts[selectorType] || 'Enter the selector value.';
        
        // Update placeholder
        const placeholders = {
            'id': 'signup-button',
            'class': 'btn-primary',
            'css': '.header .nav-menu a',
            'xpath': '//button[@id="submit"]',
            'data': 'action'
        };
        
        if (elements.selectorValue) {
            elements.selectorValue.placeholder = placeholders[selectorType] || '';
        }
    }
    
    /**
     * Auto-generate event name from selector
     */
    function autoGenerateEventName() {
        if (!elements.eventName || !elements.selectorValue || !elements.selectorType) return;
        
        // Only auto-generate if event name is empty
        if (elements.eventName.value.trim() !== '') return;
        
        const selectorValue = elements.selectorValue.value.trim();
        const selectorType = elements.selectorType.value;
        
        if (!selectorValue) return;
        
        let eventName = '';
        
        if (selectorType === 'id') {
            eventName = selectorValue.replace(/[-_]/g, ' ') + ' Click';
        } else if (selectorType === 'class') {
            eventName = selectorValue.replace(/[-_]/g, ' ') + ' Click';
        } else {
            eventName = 'Element Click';
        }
        
        // Capitalize first letter of each word
        eventName = eventName.replace(/\b\w/g, l => l.toUpperCase());
        
        elements.eventName.value = eventName;
    }
    
    /**
     * Open selector picker (placeholder for future enhancement)
     */
    function openSelectorPicker() {
        alert('Element picker feature coming soon!\n\nFor now, please enter selectors manually. Use browser developer tools to inspect elements and copy their selectors.');
    }
    
    /**
     * Show message to user
     */
    function showMessage(message, type = 'info') {
        if (!elements.messagesContainer) return;
        
        // Clear existing messages
        clearMessages();
        
        const messageEl = document.createElement('div');
        messageEl.className = `clicktally-message ${type}`;
        messageEl.textContent = message;
        
        elements.messagesContainer.appendChild(messageEl);
        
        // Auto-remove after 5 seconds for non-error messages
        if (type !== 'error') {
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.parentNode.removeChild(messageEl);
                }
            }, 5000);
        }
        
        // Scroll to message
        messageEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    
    /**
     * Clear all messages
     */
    function clearMessages() {
        if (elements.messagesContainer) {
            elements.messagesContainer.innerHTML = '';
        }
    }
    
    // Public API
    return {
        init: init,
        showAddForm: showAddForm,
        editRule: editRule,
        deleteRule: deleteRule,
        showMessage: showMessage
    };
    
})();