/**
 * ClickTally Admin Rules JavaScript
 * For managing tracking rules
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Set up rule form handling
    const ruleForm = document.getElementById('rule-form');
    if (ruleForm) {
        ruleForm.addEventListener('submit', handleRuleFormSubmit);
    }
    
    // Remove inline onclick handlers and replace with proper event listeners
    setupRuleButtonHandlers();
});

function setupRuleButtonHandlers() {
    // Add Rule button
    const addRuleBtn = document.querySelector('button[data-action="add-rule"]');
    if (addRuleBtn) {
        addRuleBtn.addEventListener('click', function() {
            openRuleModal();
        });
    }
    
    // Edit/Delete buttons
    document.querySelectorAll('button[data-rule-id]').forEach(function(btn) {
        const ruleId = btn.getAttribute('data-rule-id');
        const action = btn.getAttribute('data-action');
        
        if (action === 'edit') {
            btn.addEventListener('click', function() {
                editRule(ruleId);
            });
        } else if (action === 'delete') {
            btn.addEventListener('click', function() {
                deleteRule(ruleId);
            });
        }
    });
    
    // Modal close buttons
    document.querySelectorAll('button[data-action="close-modal"], .clicktally-modal-close').forEach(function(btn) {
        btn.addEventListener('click', closeRuleModal);
    });
    
    // DOM picker button
    const domPickerBtn = document.querySelector('button[data-action="dom-picker"]');
    if (domPickerBtn) {
        domPickerBtn.addEventListener('click', openDOMPicker);
    }
}

function openRuleModal(ruleId) {
    const modal = document.getElementById('rule-modal');
    if (modal) {
        modal.style.display = 'block';
        if (ruleId) {
            // Load rule data for editing
            loadRuleData(ruleId);
        } else {
            // Reset form for new rule
            const form = document.getElementById('rule-form');
            if (form) {
                form.reset();
            }
            const ruleIdField = document.getElementById('rule-id');
            if (ruleIdField) {
                ruleIdField.value = '';
            }
        }
    }
}

function closeRuleModal() {
    const modal = document.getElementById('rule-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openDOMPicker() {
    // TODO: Implement DOM picker functionality
    alert('DOM picker functionality will be implemented in a future update.');
}

function loadRuleData(ruleId) {
    // TODO: Load rule data via AJAX
    console.log('Loading rule data for ID:', ruleId);
}

function editRule(ruleId) {
    openRuleModal(ruleId);
}

function deleteRule(ruleId) {
    if (confirm(clickTallyAdmin.strings.confirmDelete)) {
        // TODO: Implement rule deletion
        console.log('Deleting rule ID:', ruleId);
    }
}

function handleRuleFormSubmit(e) {
    e.preventDefault();
    // TODO: Implement form submission
    console.log('Rule form submitted');
}