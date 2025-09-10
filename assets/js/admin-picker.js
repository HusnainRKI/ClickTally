/**
 * ClickTally Admin DOM Picker
 * Handles element selection and rule management
 */

(function($) {
    'use strict';
    
    var ClickTallyAdmin = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            // Rule form submission
            $('#rule-form').on('submit', this.handleRuleSubmission);
            
            // Modal close events
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    ClickTallyAdmin.closeModal();
                }
            });
            
            $('.clicktally-modal').on('click', function(e) {
                if (e.target === this) {
                    ClickTallyAdmin.closeModal();
                }
            });
        },
        
        handleRuleSubmission: function(e) {
            e.preventDefault();
            
            var formData = $(this).serializeArray();
            var ruleData = {};
            
            $.each(formData, function(i, field) {
                if (field.name === 'once_per_view') {
                    ruleData[field.name] = true;
                } else if (field.name === 'roles[]') {
                    if (!ruleData.roles) ruleData.roles = [];
                    ruleData.roles.push(field.value);
                } else {
                    ruleData[field.name] = field.value;
                }
            });
            
            // Validate required fields
            if (!ruleData.selector_type || !ruleData.selector_value || !ruleData.event_name) {
                ClickTallyAdmin.showNotice('Please fill in all required fields.', 'error');
                return;
            }
            
            // Validate selector
            if (!ClickTallyAdmin.validateSelector(ruleData.selector_type, ruleData.selector_value)) {
                ClickTallyAdmin.showNotice('Invalid selector format.', 'error');
                return;
            }
            
            var isEdit = ruleData.rule_id && ruleData.rule_id !== '';
            var action = isEdit ? 'update' : 'create';
            
            ClickTallyAdmin.saveRule(action, ruleData);
        },
        
        saveRule: function(action, ruleData) {
            var $form = $('#rule-form');
            var $submitBtn = $form.find('button[type="submit"]');
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Saving...');
            
            $.ajax({
                url: clickTallyAdmin.apiUrl + 'rules/manage',
                method: 'POST',
                data: JSON.stringify(ruleData),
                contentType: 'application/json',
                headers: {
                    'X-WP-Nonce': clickTallyAdmin.nonce
                },
                data: {
                    action: action,
                    rule_data: ruleData
                }
            })
            .done(function(response) {
                if (response.success) {
                    ClickTallyAdmin.showNotice(clickTallyAdmin.strings.ruleAdded, 'success');
                    ClickTallyAdmin.closeModal();
                    location.reload(); // Refresh to show new rule
                } else {
                    ClickTallyAdmin.showNotice(response.message || clickTallyAdmin.strings.errorGeneral, 'error');
                }
            })
            .fail(function() {
                ClickTallyAdmin.showNotice(clickTallyAdmin.strings.errorGeneral, 'error');
            })
            .always(function() {
                $submitBtn.prop('disabled', false).text('Save Rule');
            });
        },
        
        deleteRule: function(ruleId) {
            if (!confirm(clickTallyAdmin.strings.confirmDelete)) {
                return;
            }
            
            $.ajax({
                url: clickTallyAdmin.apiUrl + 'rules/manage',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': clickTallyAdmin.nonce
                },
                data: {
                    action: 'delete',
                    rule_id: ruleId
                }
            })
            .done(function(response) {
                if (response.success) {
                    ClickTallyAdmin.showNotice(clickTallyAdmin.strings.ruleDeleted, 'success');
                    location.reload();
                } else {
                    ClickTallyAdmin.showNotice(response.message || clickTallyAdmin.strings.errorGeneral, 'error');
                }
            })
            .fail(function() {
                ClickTallyAdmin.showNotice(clickTallyAdmin.strings.errorGeneral, 'error');
            });
        },
        
        validateSelector: function(type, value) {
            if (!value) return false;
            
            switch (type) {
                case 'id':
                    return /^[a-zA-Z][a-zA-Z0-9_-]*$/.test(value);
                case 'class':
                    return /^[a-zA-Z][a-zA-Z0-9_-]*$/.test(value);
                case 'css':
                    // Basic CSS selector validation
                    try {
                        document.querySelector(value);
                        return true;
                    } catch (e) {
                        return false;
                    }
                case 'xpath':
                    // Basic XPath validation
                    return value.length > 0;
                case 'data':
                    return /^[a-zA-Z][a-zA-Z0-9_-]*$/.test(value);
                default:
                    return false;
            }
        },
        
        closeModal: function() {
            $('.clicktally-modal').hide();
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div class="clicktally-notice ' + type + '">' + message + '</div>');
            
            // Remove existing notices
            $('.clicktally-notice').remove();
            
            // Add notice after the page title
            $('.wrap h1').after($notice);
            
            // Auto-hide success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 3000);
            }
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 300);
        },
        
        // DOM Picker functionality (placeholder)
        openDOMPicker: function() {
            // This would open an iframe or new window with the site
            // and allow clicking on elements to generate selectors
            alert('DOM picker functionality will be implemented in a future update.');
        },
        
        generateSelectorForElement: function(element) {
            // Generate a stable CSS selector for an element
            var selector = '';
            
            // Try ID first
            if (element.id) {
                return '#' + element.id;
            }
            
            // Try unique class combination
            if (element.className) {
                var classes = element.className.split(' ').filter(function(cls) {
                    return cls && !cls.match(/^(active|hover|focus|selected)$/);
                });
                
                if (classes.length) {
                    selector = '.' + classes.join('.');
                    
                    // Check if this selector is unique
                    if (document.querySelectorAll(selector).length === 1) {
                        return selector;
                    }
                }
            }
            
            // Fall back to tag + nth-child
            var tag = element.tagName.toLowerCase();
            var parent = element.parentElement;
            
            if (parent) {
                var siblings = Array.from(parent.children).filter(function(child) {
                    return child.tagName.toLowerCase() === tag;
                });
                
                var index = siblings.indexOf(element) + 1;
                
                if (index > 1) {
                    selector = tag + ':nth-child(' + index + ')';
                } else {
                    selector = tag;
                }
                
                // Build path up the DOM
                var path = [selector];
                var current = parent;
                
                while (current && current !== document.body && path.length < 5) {
                    var currentSelector = current.tagName.toLowerCase();
                    
                    if (current.id) {
                        currentSelector = '#' + current.id;
                        path.unshift(currentSelector);
                        break;
                    }
                    
                    if (current.className) {
                        var currentClasses = current.className.split(' ').filter(function(cls) {
                            return cls && !cls.match(/^(active|hover|focus|selected)$/);
                        });
                        
                        if (currentClasses.length) {
                            currentSelector += '.' + currentClasses[0];
                        }
                    }
                    
                    path.unshift(currentSelector);
                    current = current.parentElement;
                }
                
                return path.join(' > ');
            }
            
            return tag;
        }
    };
    
    // Global functions for inline event handlers
    window.openRuleModal = function(ruleId) {
        $('#rule-modal').show();
        
        if (ruleId) {
            // Load rule data for editing
            // This would make an AJAX call to get the rule data
            console.log('Loading rule for editing:', ruleId);
        } else {
            // Reset form for new rule
            $('#rule-form')[0].reset();
            $('#rule-id').val('');
        }
    };
    
    window.closeRuleModal = function() {
        ClickTallyAdmin.closeModal();
    };
    
    window.editRule = function(ruleId) {
        window.openRuleModal(ruleId);
    };
    
    window.deleteRule = function(ruleId) {
        ClickTallyAdmin.deleteRule(ruleId);
    };
    
    window.openDOMPicker = function() {
        ClickTallyAdmin.openDOMPicker();
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        ClickTallyAdmin.init();
    });
    
})(jQuery);