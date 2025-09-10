/**
 * ClickTally Admin Event Rules JavaScript
 * For managing tracking events
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Set up event form handling
    const eventForm = document.getElementById('event-form');
    if (eventForm) {
        eventForm.addEventListener('submit', handleEventFormSubmit);
    }
    
    // Remove inline onclick handlers and replace with proper event listeners
    setupEventButtonHandlers();
});

function setupEventButtonHandlers() {
    // Add Event button
    const addEventBtn = document.querySelector('button[data-action="add-event"]');
    if (addEventBtn) {
        addEventBtn.addEventListener('click', function() {
            openEventModal();
        });
    }
    
    // Edit/Delete buttons
    document.querySelectorAll('button[data-rule-id]').forEach(function(btn) {
        const eventId = btn.getAttribute('data-rule-id');
        const action = btn.getAttribute('data-action');
        
        if (action === 'edit') {
            btn.addEventListener('click', function() {
                editEvent(eventId);
            });
        } else if (action === 'delete') {
            btn.addEventListener('click', function() {
                deleteEvent(eventId);
            });
        }
    });
    
    // Modal close buttons
    document.querySelectorAll('button[data-action="close-modal"], .clicktally-modal-close').forEach(function(btn) {
        btn.addEventListener('click', closeEventModal);
    });
    
    // DOM picker button
    const domPickerBtn = document.querySelector('button[data-action="dom-picker"]');
    if (domPickerBtn) {
        domPickerBtn.addEventListener('click', openDOMPicker);
    }
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
    // TODO: Implement DOM picker functionality
    alert('DOM picker functionality will be implemented in a future update.');
}

function loadEventData(eventId) {
    // TODO: Load event data via AJAX
    console.log('Loading event data for ID:', eventId);
}

function editEvent(eventId) {
    openEventModal(eventId);
}

function deleteEvent(eventId) {
    if (confirm(clickTallyAdmin.strings.confirmDelete)) {
        // TODO: Implement event deletion
        console.log('Deleting event ID:', eventId);
    }
}

function handleEventFormSubmit(e) {
    e.preventDefault();
    // TODO: Implement form submission
    console.log('Event form submitted');
}