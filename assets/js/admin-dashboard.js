/**
 * ClickTally Element Event Tracker Dashboard JavaScript
 * Handles dashboard interactions, data fetching, and chart rendering
 */

(function() {
    'use strict';
    
    // Global variables
    let dashboardData = {
        summary: null,
        topElements: null,
        topPages: null,
        currentFilters: {}
    };
    
    let chartTooltip = null;
    
    /**
     * Initialize dashboard when DOM is ready
     */
    document.addEventListener('DOMContentLoaded', function() {
        clicktally_element_event_tracker_init_dashboard();
    });
    
    /**
     * Initialize dashboard functionality
     */
    function clicktally_element_event_tracker_init_dashboard() {
        // Set up filter change handlers
        clicktally_element_event_tracker_setup_filters();
        
        // Set up export handlers
        clicktally_element_event_tracker_setup_export_handlers();
        
        // Load initial data
        clicktally_element_event_tracker_load_dashboard_data();
        
        // Create chart tooltip
        clicktally_element_event_tracker_create_chart_tooltip();
    }
    
    /**
     * Set up filter change handlers
     */
    function clicktally_element_event_tracker_setup_filters() {
        const filters = ['clicktally-date-range', 'clicktally-device-filter', 'clicktally-user-filter'];
        
        filters.forEach(function(filterId) {
            const filterElement = document.getElementById(filterId);
            if (filterElement) {
                filterElement.addEventListener('change', function() {
                    clicktally_element_event_tracker_load_dashboard_data();
                });
            }
        });
    }
    
    /**
     * Set up export button handlers
     */
    function clicktally_element_event_tracker_setup_export_handlers() {
        const exportTopElements = document.getElementById('export-top-elements');
        const exportTopPages = document.getElementById('export-top-pages');
        
        if (exportTopElements) {
            exportTopElements.addEventListener('click', function() {
                clicktally_element_event_tracker_export_csv('top-elements');
            });
        }
        
        if (exportTopPages) {
            exportTopPages.addEventListener('click', function() {
                clicktally_element_event_tracker_export_csv('top-pages');
            });
        }
    }
    
    /**
     * Get current filter values
     */
    function clicktally_element_event_tracker_get_current_filters() {
        return {
            range: clicktally_element_event_tracker_get_filter_value('clicktally-date-range', '7d'),
            device: clicktally_element_event_tracker_get_filter_value('clicktally-device-filter', 'all'),
            user: clicktally_element_event_tracker_get_filter_value('clicktally-user-filter', 'all')
        };
    }
    
    /**
     * Get filter value with fallback
     */
    function clicktally_element_event_tracker_get_filter_value(elementId, defaultValue) {
        const element = document.getElementById(elementId);
        return element ? element.value : defaultValue;
    }
    
    /**
     * Load all dashboard data
     */
    function clicktally_element_event_tracker_load_dashboard_data() {
        dashboardData.currentFilters = clicktally_element_event_tracker_get_current_filters();
        
        // Show loading states
        clicktally_element_event_tracker_show_loading_states();
        
        // Load summary data
        clicktally_element_event_tracker_fetch_summary_data();
        
        // Load table data
        clicktally_element_event_tracker_fetch_top_elements_data();
        clicktally_element_event_tracker_fetch_top_pages_data();
    }
    
    /**
     * Show loading states across the dashboard
     */
    function clicktally_element_event_tracker_show_loading_states() {
        // KPI cards
        const kpiElements = ['total-clicks', 'unique-elements', 'top-page', 'events-today'];
        kpiElements.forEach(function(id) {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = '<span class="clicktally-loading">' + 
                    ClickTallyElementEventTrackerAdminConfig.i18n.loading + '</span>';
            }
        });
        
        // Tables
        const tableElements = ['top-elements-table', 'top-pages-table'];
        tableElements.forEach(function(id) {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = '<div class="clicktally-loading">' + 
                    ClickTallyElementEventTrackerAdminConfig.i18n.loading + '</div>';
                element.classList.add('loading');
            }
        });
        
        // Clear chart
        clicktally_element_event_tracker_clear_chart();
    }
    
    /**
     * Fetch summary data from REST API
     */
    function clicktally_element_event_tracker_fetch_summary_data() {
        const params = new URLSearchParams(dashboardData.currentFilters);
        const url = ClickTallyElementEventTrackerAdminConfig.restUrlBackcompat + 'stats/summary?' + params.toString();
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': ClickTallyElementEventTrackerAdminConfig.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            dashboardData.summary = data;
            clicktally_element_event_tracker_update_kpi_cards(data);
            clicktally_element_event_tracker_render_chart(data.timeseries || []);
        })
        .catch(function(error) {
            console.error('Error fetching summary data:', error);
            clicktally_element_event_tracker_show_error_in_kpis();
        });
    }
    
    /**
     * Fetch top elements data
     */
    function clicktally_element_event_tracker_fetch_top_elements_data() {
        const params = new URLSearchParams(Object.assign({}, dashboardData.currentFilters, { limit: 10 }));
        const url = ClickTallyElementEventTrackerAdminConfig.restUrlBackcompat + 'stats/top-elements?' + params.toString();
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': ClickTallyElementEventTrackerAdminConfig.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            dashboardData.topElements = data;
            clicktally_element_event_tracker_render_top_elements_table(data);
        })
        .catch(function(error) {
            console.error('Error fetching top elements data:', error);
            clicktally_element_event_tracker_show_error_in_table('top-elements-table');
        });
    }
    
    /**
     * Fetch top pages data
     */
    function clicktally_element_event_tracker_fetch_top_pages_data() {
        const params = new URLSearchParams(Object.assign({}, dashboardData.currentFilters, { limit: 10 }));
        const url = ClickTallyElementEventTrackerAdminConfig.restUrlBackcompat + 'stats/top-pages?' + params.toString();
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-WP-Nonce': ClickTallyElementEventTrackerAdminConfig.nonce,
                'Content-Type': 'application/json'
            }
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(function(data) {
            dashboardData.topPages = data;
            clicktally_element_event_tracker_render_top_pages_table(data);
        })
        .catch(function(error) {
            console.error('Error fetching top pages data:', error);
            clicktally_element_event_tracker_show_error_in_table('top-pages-table');
        });
    }
    
    /**
     * Update KPI cards with summary data
     */
    function clicktally_element_event_tracker_update_kpi_cards(data) {
        // Total clicks
        const totalClicksEl = document.getElementById('total-clicks');
        if (totalClicksEl) {
            totalClicksEl.textContent = clicktally_element_event_tracker_format_number(data.total_clicks || 0);
        }
        
        // Unique elements
        const uniqueElementsEl = document.getElementById('unique-elements');
        if (uniqueElementsEl) {
            uniqueElementsEl.textContent = clicktally_element_event_tracker_format_number(data.unique_elements || 0);
        }
        
        // Top page
        const topPageEl = document.getElementById('top-page');
        if (topPageEl) {
            if (data.top_page && data.top_page.page) {
                const pageTitle = data.top_page.title || clicktally_element_event_tracker_get_page_title_from_url(data.top_page.page);
                topPageEl.innerHTML = '<a href="' + clicktally_element_event_tracker_esc_attr(data.top_page.page) + '" target="_blank">' +
                    clicktally_element_event_tracker_esc_html(pageTitle) + '</a>' +
                    '<small>(' + clicktally_element_event_tracker_format_number(data.top_page.clicks) + ' clicks)</small>';
            } else {
                topPageEl.innerHTML = '<span>' + ClickTallyElementEventTrackerAdminConfig.i18n.noData + '</span>';
            }
        }
        
        // Events today
        const eventsTodayEl = document.getElementById('events-today');
        if (eventsTodayEl) {
            eventsTodayEl.textContent = clicktally_element_event_tracker_format_number(data.events_today || 0);
        }
    }
    
    /**
     * Render top elements table
     */
    function clicktally_element_event_tracker_render_top_elements_table(data) {
        const container = document.getElementById('top-elements-table');
        if (!container) return;
        
        container.classList.remove('loading');
        
        if (!data || data.length === 0) {
            container.innerHTML = '<div class="clicktally-no-data">' +
                '<span class="dashicons dashicons-chart-bar"></span><br>' +
                ClickTallyElementEventTrackerAdminConfig.i18n.noData + '</div>';
            return;
        }
        
        let html = '<table class="clicktally-table">';
        html += '<thead><tr>';
        html += '<th scope="col">Event Name</th>';
        html += '<th scope="col">Selector/Label</th>';
        html += '<th scope="col">Example Page</th>';
        html += '<th scope="col">Clicks</th>';
        html += '<th scope="col">% of Page Clicks</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        data.forEach(function(item) {
            html += '<tr>';
            html += '<td class="event-name">' + clicktally_element_event_tracker_esc_html(item.event_name || '') + '</td>';
            html += '<td><span class="selector-preview">' + clicktally_element_event_tracker_esc_html(item.selector_preview || item.selector_key || '') + '</span></td>';
            html += '<td class="page-url">';
            if (item.example_page) {
                html += '<a href="' + clicktally_element_event_tracker_esc_attr(item.example_page) + '" target="_blank">' +
                    clicktally_element_event_tracker_esc_html(clicktally_element_event_tracker_get_page_title_from_url(item.example_page)) + '</a>';
            } else {
                html += '-';
            }
            html += '</td>';
            html += '<td class="clicks-count">' + clicktally_element_event_tracker_format_number(item.total_clicks || item.clicks || 0) + '</td>';
            html += '<td class="percentage">' + (item.page_share ? (item.page_share * 100).toFixed(1) : (item.percentage || 0).toFixed(1)) + '%</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    /**
     * Render top pages table
     */
    function clicktally_element_event_tracker_render_top_pages_table(data) {
        const container = document.getElementById('top-pages-table');
        if (!container) return;
        
        container.classList.remove('loading');
        
        if (!data || data.length === 0) {
            container.innerHTML = '<div class="clicktally-no-data">' +
                '<span class="dashicons dashicons-admin-page"></span><br>' +
                ClickTallyElementEventTrackerAdminConfig.i18n.noData + '</div>';
            return;
        }
        
        let html = '<table class="clicktally-table">';
        html += '<thead><tr>';
        html += '<th scope="col">Page</th>';
        html += '<th scope="col">Clicks</th>';
        html += '<th scope="col">Top Event on Page</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        data.forEach(function(item) {
            html += '<tr>';
            html += '<td class="page-url">';
            if (item.page || item.page_url) {
                const pageUrl = item.page || item.page_url;
                const pageTitle = item.title || clicktally_element_event_tracker_get_page_title_from_url(pageUrl);
                html += '<a href="' + clicktally_element_event_tracker_esc_attr(pageUrl) + '" target="_blank">' +
                    clicktally_element_event_tracker_esc_html(pageTitle) + '</a>';
            } else {
                html += '-';
            }
            html += '</td>';
            html += '<td class="clicks-count">' + clicktally_element_event_tracker_format_number(item.total_clicks || item.clicks || 0) + '</td>';
            html += '<td>' + clicktally_element_event_tracker_esc_html(item.top_event || '-') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    /**
     * Render line chart with SVG
     */
    function clicktally_element_event_tracker_render_chart(timeseries) {
        const svg = document.getElementById('clicktally-line-chart');
        if (!svg || !timeseries || timeseries.length === 0) {
            clicktally_element_event_tracker_clear_chart();
            return;
        }
        
        // Clear existing content
        svg.innerHTML = '';
        
        const margin = { top: 20, right: 20, bottom: 40, left: 60 };
        const width = svg.clientWidth - margin.left - margin.right;
        const height = 300 - margin.top - margin.bottom;
        
        // Process data
        const maxClicks = Math.max(...timeseries.map(d => d.clicks || 0));
        const minClicks = 0;
        
        // Create scales
        const xStep = width / (timeseries.length - 1);
        const yScale = height / (maxClicks - minClicks);
        
        // Create group for chart content
        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.setAttribute('transform', 'translate(' + margin.left + ',' + margin.top + ')');
        svg.appendChild(g);
        
        // Draw grid lines
        for (let i = 0; i <= 5; i++) {
            const y = (height / 5) * i;
            const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('x1', 0);
            line.setAttribute('y1', y);
            line.setAttribute('x2', width);
            line.setAttribute('y2', y);
            line.setAttribute('class', 'clicktally-chart-grid');
            g.appendChild(line);
        }
        
        // Draw axes
        const xAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        xAxis.setAttribute('x1', 0);
        xAxis.setAttribute('y1', height);
        xAxis.setAttribute('x2', width);
        xAxis.setAttribute('y2', height);
        xAxis.setAttribute('class', 'clicktally-chart-axis');
        g.appendChild(xAxis);
        
        const yAxis = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        yAxis.setAttribute('x1', 0);
        yAxis.setAttribute('y1', 0);
        yAxis.setAttribute('x2', 0);
        yAxis.setAttribute('y2', height);
        yAxis.setAttribute('class', 'clicktally-chart-axis');
        g.appendChild(yAxis);
        
        // Draw line
        let pathData = '';
        timeseries.forEach(function(point, index) {
            const x = index * xStep;
            const y = height - ((point.clicks || 0) - minClicks) * yScale;
            pathData += (index === 0 ? 'M' : 'L') + x + ',' + y;
        });
        
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathData);
        path.setAttribute('class', 'clicktally-chart-line');
        g.appendChild(path);
        
        // Draw dots and add interactivity
        timeseries.forEach(function(point, index) {
            const x = index * xStep;
            const y = height - ((point.clicks || 0) - minClicks) * yScale;
            
            const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot.setAttribute('cx', x);
            dot.setAttribute('cy', y);
            dot.setAttribute('r', 3);
            dot.setAttribute('class', 'clicktally-chart-dot');
            
            // Add tooltip interaction
            dot.addEventListener('mouseenter', function(e) {
                clicktally_element_event_tracker_show_chart_tooltip(e, point);
            });
            
            dot.addEventListener('mouseleave', function() {
                clicktally_element_event_tracker_hide_chart_tooltip();
            });
            
            g.appendChild(dot);
        });
        
        // Add Y-axis labels
        for (let i = 0; i <= 5; i++) {
            const value = maxClicks - (maxClicks / 5) * i;
            const y = (height / 5) * i;
            
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', -10);
            text.setAttribute('y', y + 4);
            text.setAttribute('text-anchor', 'end');
            text.setAttribute('class', 'clicktally-chart-text');
            text.textContent = clicktally_element_event_tracker_format_number(Math.round(value));
            g.appendChild(text);
        }
        
        // Add X-axis labels (show every few days depending on range)
        const labelInterval = Math.max(1, Math.floor(timeseries.length / 7));
        timeseries.forEach(function(point, index) {
            if (index % labelInterval === 0 || index === timeseries.length - 1) {
                const x = index * xStep;
                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('x', x);
                text.setAttribute('y', height + 20);
                text.setAttribute('text-anchor', 'middle');
                text.setAttribute('class', 'clicktally-chart-text');
                text.textContent = clicktally_element_event_tracker_format_date(point.day);
                g.appendChild(text);
            }
        });
    }
    
    /**
     * Clear chart content
     */
    function clicktally_element_event_tracker_clear_chart() {
        const svg = document.getElementById('clicktally-line-chart');
        if (svg) {
            svg.innerHTML = '<text x="50%" y="50%" text-anchor="middle" class="clicktally-chart-text">' +
                ClickTallyElementEventTrackerAdminConfig.i18n.loading + '</text>';
        }
    }
    
    /**
     * Create chart tooltip element
     */
    function clicktally_element_event_tracker_create_chart_tooltip() {
        chartTooltip = document.createElement('div');
        chartTooltip.className = 'clicktally-chart-tooltip';
        document.body.appendChild(chartTooltip);
    }
    
    /**
     * Show chart tooltip
     */
    function clicktally_element_event_tracker_show_chart_tooltip(event, data) {
        if (!chartTooltip) return;
        
        chartTooltip.innerHTML = clicktally_element_event_tracker_format_date(data.day) + '<br>' +
            clicktally_element_event_tracker_format_number(data.clicks || 0) + ' clicks';
        chartTooltip.style.left = (event.pageX + 10) + 'px';
        chartTooltip.style.top = (event.pageY - 10) + 'px';
        chartTooltip.style.opacity = '1';
    }
    
    /**
     * Hide chart tooltip
     */
    function clicktally_element_event_tracker_hide_chart_tooltip() {
        if (chartTooltip) {
            chartTooltip.style.opacity = '0';
        }
    }
    
    /**
     * Export data as CSV
     */
    function clicktally_element_event_tracker_export_csv(type) {
        let data, filename, headers;
        
        if (type === 'top-elements' && dashboardData.topElements) {
            data = dashboardData.topElements;
            filename = 'clicktally-top-elements.csv';
            headers = ['Event Name', 'Selector Key', 'Example Page', 'Clicks', 'Percentage'];
        } else if (type === 'top-pages' && dashboardData.topPages) {
            data = dashboardData.topPages;
            filename = 'clicktally-top-pages.csv';
            headers = ['Page', 'Title', 'Clicks', 'Top Event'];
        } else {
            console.error('No data available for export');
            return;
        }
        
        if (!data || data.length === 0) {
            alert(ClickTallyElementEventTrackerAdminConfig.i18n.noData);
            return;
        }
        
        const csv = clicktally_element_event_tracker_convert_to_csv(data, headers, type);
        clicktally_element_event_tracker_download_csv(csv, filename);
    }
    
    /**
     * Convert data to CSV format
     */
    function clicktally_element_event_tracker_convert_to_csv(data, headers, type) {
        let csv = headers.join(',') + '\n';
        
        data.forEach(function(item) {
            let row = [];
            
            if (type === 'top-elements') {
                row = [
                    '"' + (item.event_name || '').replace(/"/g, '""') + '"',
                    '"' + (item.selector_key || item.selector_preview || '').replace(/"/g, '""') + '"',
                    '"' + (item.example_page || '').replace(/"/g, '""') + '"',
                    item.total_clicks || item.clicks || 0,
                    (item.page_share ? (item.page_share * 100).toFixed(1) : (item.percentage || 0).toFixed(1)) + '%'
                ];
            } else if (type === 'top-pages') {
                row = [
                    '"' + (item.page || item.page_url || '').replace(/"/g, '""') + '"',
                    '"' + (item.title || '').replace(/"/g, '""') + '"',
                    item.total_clicks || item.clicks || 0,
                    '"' + (item.top_event || '').replace(/"/g, '""') + '"'
                ];
            }
            
            csv += row.join(',') + '\n';
        });
        
        return csv;
    }
    
    /**
     * Download CSV file
     */
    function clicktally_element_event_tracker_download_csv(csv, filename) {
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }
    }
    
    /**
     * Show error in KPI cards
     */
    function clicktally_element_event_tracker_show_error_in_kpis() {
        const kpiElements = ['total-clicks', 'unique-elements', 'top-page', 'events-today'];
        kpiElements.forEach(function(id) {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = '<span class="clicktally-error">' + 
                    ClickTallyElementEventTrackerAdminConfig.i18n.error + '</span>';
            }
        });
    }
    
    /**
     * Show error in table
     */
    function clicktally_element_event_tracker_show_error_in_table(tableId) {
        const container = document.getElementById(tableId);
        if (container) {
            container.classList.remove('loading');
            container.innerHTML = '<div class="clicktally-error">' + 
                ClickTallyElementEventTrackerAdminConfig.i18n.error + '</div>';
        }
    }
    
    /**
     * Utility functions
     */
    function clicktally_element_event_tracker_format_number(num) {
        return new Intl.NumberFormat().format(num);
    }
    
    function clicktally_element_event_tracker_format_date(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
    
    function clicktally_element_event_tracker_get_page_title_from_url(url) {
        if (!url) return '';
        const parts = url.split('/').filter(Boolean);
        if (parts.length === 0) return 'Home';
        const lastPart = parts[parts.length - 1];
        return lastPart.charAt(0).toUpperCase() + lastPart.slice(1).replace(/-/g, ' ');
    }
    
    function clicktally_element_event_tracker_esc_html(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
    }
    
    function clicktally_element_event_tracker_esc_attr(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;');
    }
    
})();