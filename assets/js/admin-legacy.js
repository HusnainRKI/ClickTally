/**
 * ClickTally Admin Legacy JavaScript
 * For backward compatibility with old admin interface
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    
    // Auto-refresh data when filters change
    ['ct-date-range', 'ct-device', 'ct-user-type'].forEach(function(id) {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('change', function() {
                refreshDashboardData();
            });
        }
    });
    
    // Export button handler (remove onclick and add proper event listener)
    const exportBtn = document.querySelector('button[onclick*="clickTallyExportData"]');
    if (exportBtn) {
        exportBtn.removeAttribute('onclick'); // Remove inline onclick
        exportBtn.addEventListener('click', clickTallyExportData);
    }
});

function refreshDashboardData() {
    var range = document.getElementById('ct-date-range').value;
    var device = document.getElementById('ct-device').value;
    var userType = document.getElementById('ct-user-type').value;
    
    // Show loading state
    document.querySelectorAll('.clicktally-stat-number, .clicktally-stat-text').forEach(function(el) {
        el.style.opacity = '0.5';
    });
    
    // Make AJAX request
    jQuery.post(ajaxurl, {
        action: 'clicktally_get_stats',
        nonce: clickTallyAdmin.nonce,
        range: range,
        device: device,
        user_type: userType
    }, function(response) {
        if (response.success) {
            updateDashboardStats(response.data);
        }
    }).always(function() {
        // Remove loading state
        document.querySelectorAll('.clicktally-stat-number, .clicktally-stat-text').forEach(function(el) {
            el.style.opacity = '1';
        });
    });
}

function updateDashboardStats(data) {
    // Update summary cards
    const totalClicksEl = document.querySelector('.clicktally-summary-cards .clicktally-stat-number');
    if (totalClicksEl) {
        totalClicksEl.textContent = new Intl.NumberFormat().format(data.total_clicks);
    }
    
    // Update tables
    if (data.top_elements_html) {
        const elementsTable = document.getElementById('ct-top-elements-table');
        if (elementsTable) {
            elementsTable.innerHTML = data.top_elements_html;
        }
    }
    if (data.top_pages_html) {
        const pagesTable = document.getElementById('ct-top-pages-table');
        if (pagesTable) {
            pagesTable.innerHTML = data.top_pages_html;
        }
    }
}

function clickTallyExportData() {
    var range = document.getElementById('ct-date-range').value;
    var device = document.getElementById('ct-device').value;
    var userType = document.getElementById('ct-user-type').value;
    
    var params = new URLSearchParams({
        action: 'clicktally_export_data',
        nonce: clickTallyAdmin.nonce,
        range: range,
        device: device,
        user_type: userType
    });
    
    window.location.href = ajaxurl + '?' + params.toString();
}