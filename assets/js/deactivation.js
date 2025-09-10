/**
 * ClickTally Deactivation Confirmation Script
 */

jQuery(document).ready(function($) {
    
    let deactivationUrl = '';
    let selectedOption = '';
    
    // Intercept deactivation link click
    $('tr[data-slug="' + clickTallyDeactivation.pluginSlug + '"] .deactivate a').on('click', function(e) {
        e.preventDefault();
        deactivationUrl = $(this).attr('href');
        showDeactivationModal();
    });
    
    function showDeactivationModal() {
        const modalHtml = `
            <div class="clicktally-deactivation-modal" id="clicktally-deactivation-modal">
                <div class="clicktally-deactivation-content">
                    <div class="clicktally-deactivation-header">
                        <h2>${clickTallyDeactivation.i18n.title}</h2>
                    </div>
                    <div class="clicktally-deactivation-body">
                        <p>${clickTallyDeactivation.i18n.message}</p>
                        <div class="clicktally-deactivation-options">
                            <div class="clicktally-deactivation-option" data-action="keep_data">
                                <label>
                                    <input type="radio" name="deactivation_action" value="keep_data" style="margin-right: 10px;">
                                    <strong>${clickTallyDeactivation.i18n.keepData}</strong>
                                    <br><small>Your analytics data will be preserved for future use.</small>
                                </label>
                            </div>
                            <div class="clicktally-deactivation-option" data-action="delete_data">
                                <label>
                                    <input type="radio" name="deactivation_action" value="delete_data" style="margin-right: 10px;">
                                    <strong>${clickTallyDeactivation.i18n.deleteData}</strong>
                                    <br><small>All tracking data, rules, and settings will be permanently deleted.</small>
                                </label>
                            </div>
                        </div>
                        <div class="clicktally-deactivation-warning" id="delete-warning" style="display: none;">
                            ⚠️ ${clickTallyDeactivation.i18n.warning}
                        </div>
                    </div>
                    <div class="clicktally-deactivation-footer">
                        <button type="button" class="button" id="clicktally-cancel-deactivation">
                            ${clickTallyDeactivation.i18n.cancel}
                        </button>
                        <button type="button" class="button button-primary" id="clicktally-proceed-deactivation" disabled>
                            ${clickTallyDeactivation.i18n.proceed}
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        $('#clicktally-deactivation-modal').show();
        
        // Handle option selection
        $('.clicktally-deactivation-option').on('click', function() {
            $('.clicktally-deactivation-option').removeClass('selected');
            $(this).addClass('selected');
            $(this).find('input[type="radio"]').prop('checked', true);
            
            selectedOption = $(this).data('action');
            $('#clicktally-proceed-deactivation').prop('disabled', false);
            
            // Show warning for delete option
            if (selectedOption === 'delete_data') {
                $('#delete-warning').show();
            } else {
                $('#delete-warning').hide();
            }
        });
        
        // Handle radio button clicks
        $('input[name="deactivation_action"]').on('change', function() {
            const $option = $(this).closest('.clicktally-deactivation-option');
            $('.clicktally-deactivation-option').removeClass('selected');
            $option.addClass('selected');
            
            selectedOption = $(this).val();
            $('#clicktally-proceed-deactivation').prop('disabled', false);
            
            // Show warning for delete option
            if (selectedOption === 'delete_data') {
                $('#delete-warning').show();
            } else {
                $('#delete-warning').hide();
            }
        });
        
        // Handle cancel
        $('#clicktally-cancel-deactivation').on('click', function() {
            $('#clicktally-deactivation-modal').remove();
        });
        
        // Handle proceed
        $('#clicktally-proceed-deactivation').on('click', function() {
            const $button = $(this);
            $button.prop('disabled', true).text('Processing...');
            
            // Send preference to server
            $.ajax({
                url: clickTallyDeactivation.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'clicktally_deactivation_feedback',
                    action_type: selectedOption,
                    nonce: clickTallyDeactivation.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Close modal and proceed with deactivation
                        $('#clicktally-deactivation-modal').remove();
                        window.location.href = deactivationUrl;
                    } else {
                        alert('Error: ' + (response.data?.message || 'Unknown error occurred'));
                        $button.prop('disabled', false).text(clickTallyDeactivation.i18n.proceed);
                    }
                },
                error: function() {
                    alert('Error: Failed to save preference');
                    $button.prop('disabled', false).text(clickTallyDeactivation.i18n.proceed);
                }
            });
        });
        
        // Close modal on outside click
        $(document).on('click', function(e) {
            if ($(e.target).is('#clicktally-deactivation-modal')) {
                $('#clicktally-deactivation-modal').remove();
            }
        });
        
        // Prevent modal content clicks from closing modal
        $('.clicktally-deactivation-content').on('click', function(e) {
            e.stopPropagation();
        });
    }
});