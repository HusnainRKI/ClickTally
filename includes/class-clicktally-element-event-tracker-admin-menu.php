<?php
/**
 * ClickTally Element Event Tracker Admin Menu Class
 * Handles the top-level admin menu and dashboard with proper long prefixes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clicktally_Element_Event_Tracker_Admin_Menu {
    
    /**
     * Initialize admin menu
     */
    public static function init() {
        // Add the menu immediately since we're already in the admin_menu hook
        self::clicktally_element_event_tracker_add_admin_menu();
        
        // Still need to enqueue scripts for the admin pages
        add_action('admin_enqueue_scripts', array(__CLASS__, 'clicktally_element_event_tracker_enqueue_admin_scripts'));
    }
    
    /**
     * Add top-level admin menu with submenu items
     */
    public static function clicktally_element_event_tracker_add_admin_menu() {
        $capability = 'manage_clicktally_element_event_tracker';
        
        // Top-level menu page - ClickTally Dashboard
        add_menu_page(
            __('ClickTally', 'clicktally'),
            __('ClickTally', 'clicktally'),
            $capability,
            'clicktally-element-event-tracker',
            array(__CLASS__, 'clicktally_element_event_tracker_render_dashboard_page'),
            'dashicons-chart-area',
            26
        );
        
        // Dashboard submenu (same as parent, will be default)
        add_submenu_page(
            'clicktally-element-event-tracker',
            __('Dashboard', 'clicktally'),
            __('Dashboard', 'clicktally'),
            $capability,
            'clicktally-element-event-tracker',
            array(__CLASS__, 'clicktally_element_event_tracker_render_dashboard_page')
        );
        
        // Event Tracking Rules submenu
        add_submenu_page(
            'clicktally-element-event-tracker',
            __('Event Tracking Rules', 'clicktally'),
            __('Event Tracking Rules', 'clicktally'),
            $capability,
            'clicktally-element-event-tracker-rules',
            array(__CLASS__, 'clicktally_element_event_tracker_render_rules_page')
        );
        
        // Test Mode submenu
        add_submenu_page(
            'clicktally-element-event-tracker',
            __('Test Mode', 'clicktally'),
            __('Test Mode', 'clicktally'),
            $capability,
            'clicktally-element-event-tracker-test',
            array(__CLASS__, 'clicktally_element_event_tracker_render_test_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'clicktally-element-event-tracker',
            __('Settings', 'clicktally'),
            __('Settings', 'clicktally'),
            $capability,
            'clicktally-element-event-tracker-settings',
            array(__CLASS__, 'clicktally_element_event_tracker_render_settings_page')
        );
        
        // Diagnostics submenu
        add_submenu_page(
            'clicktally-element-event-tracker',
            __('Diagnostics', 'clicktally'),
            __('Diagnostics', 'clicktally'),
            $capability,
            'clicktally-element-event-tracker-diagnostics',
            array(__CLASS__, 'clicktally_element_event_tracker_render_diagnostics_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles only on our screens
     */
    public static function clicktally_element_event_tracker_enqueue_admin_scripts($hook_suffix) {
        // Only load on our dashboard screen
        if ($hook_suffix === 'toplevel_page_clicktally-element-event-tracker') {
            
            wp_enqueue_style(
                'clicktally-element-event-tracker-admin-dashboard-style',
                CLICKTALLY_PLUGIN_URL . 'assets/css/admin-dashboard.css',
                array(),
                CLICKTALLY_VERSION . '.1' // Bust cache
            );
            
            wp_enqueue_script(
                'clicktally-element-event-tracker-admin-dashboard-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/admin-dashboard.js',
                array('jquery', 'wp-util'), // Added proper dependencies
                CLICKTALLY_VERSION . '.1', // Bust cache
                true
            );
            
            // Localize dashboard config with standardized nonce
            wp_localize_script(
                'clicktally-element-event-tracker-admin-dashboard-script',
                'ClickTallyElementEventTrackerAdminConfig',
                array(
                    'nonce' => wp_create_nonce('clicktally_element_event_tracker_admin'),
                    'restUrl' => rest_url('clicktally-element-event-tracker/v1/'),
                    'restUrlBackcompat' => rest_url('clicktally/v1/'),
                    'defaultFilters' => array(
                        'range' => '7d',
                        'device' => 'all',
                        'user' => 'all'
                    ),
                    'i18n' => array(
                        'loading' => __('Loading...', 'clicktally'),
                        'error' => __('Error loading data. Please try again.', 'clicktally'),
                        'noData' => __('No data available.', 'clicktally'),
                        'exportSuccess' => __('Data exported successfully.', 'clicktally'),
                        'exportError' => __('Error exporting data.', 'clicktally'),
                        'accessDenied' => __('Access denied. Please check your permissions.', 'clicktally')
                    )
                )
            );
            
        } elseif ($hook_suffix === 'clicktally_page_clicktally-element-event-tracker-rules') {
            // Rules page - load React components for Add/Edit rule functionality
            wp_enqueue_script(
                'clicktally-element-event-tracker-rules-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/admin-rules.js',
                array('jquery', 'wp-element', 'wp-components', 'wp-api-fetch'), // Added React dependencies
                CLICKTALLY_VERSION . '.1', // Bust cache
                true
            );
            
            wp_localize_script('clicktally-element-event-tracker-rules-script', 'clickTallyAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('clicktally_element_event_tracker_admin'),
                'apiUrl' => rest_url('clicktally/v1/'),
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this event?', 'clicktally'),
                    'errorGeneral' => __('An error occurred. Please try again.', 'clicktally'),
                    'eventAdded' => __('Event added successfully.', 'clicktally'),
                    'eventUpdated' => __('Event updated successfully.', 'clicktally'),
                    'eventDeleted' => __('Event deleted successfully.', 'clicktally'),
                )
            ));
            
        } elseif (strpos($hook_suffix, 'clicktally-element-event-tracker') !== false) {
            // Load basic styles for other ClickTally pages
            wp_enqueue_style(
                'clicktally-element-event-tracker-admin-basic-style',
                CLICKTALLY_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                CLICKTALLY_VERSION . '.1' // Bust cache
            );
        }
    }
    
    /**
     * Render the main Dashboard page
     */
    public static function clicktally_element_event_tracker_render_dashboard_page() {
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'clicktally'));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('ClickTally — Dashboard', 'clicktally'); ?></h1>
            
            <!-- Dashboard Header with Filters -->
            <div class="clicktally-dashboard-header">
                <div class="clicktally-filters">
                    <select id="clicktally-date-range" class="clicktally-filter">
                        <option value="7d"><?php echo esc_html__('Last 7 days', 'clicktally'); ?></option>
                        <option value="30d"><?php echo esc_html__('Last 30 days', 'clicktally'); ?></option>
                        <option value="90d"><?php echo esc_html__('Last 90 days', 'clicktally'); ?></option>
                    </select>
                    
                    <select id="clicktally-device-filter" class="clicktally-filter">
                        <option value="all"><?php echo esc_html__('All Devices', 'clicktally'); ?></option>
                        <option value="desktop"><?php echo esc_html__('Desktop', 'clicktally'); ?></option>
                        <option value="mobile"><?php echo esc_html__('Mobile', 'clicktally'); ?></option>
                        <option value="tablet"><?php echo esc_html__('Tablet', 'clicktally'); ?></option>
                    </select>
                    
                    <select id="clicktally-user-filter" class="clicktally-filter">
                        <option value="all"><?php echo esc_html__('All Users', 'clicktally'); ?></option>
                        <option value="guests"><?php echo esc_html__('Guests', 'clicktally'); ?></option>
                        <option value="logged-in"><?php echo esc_html__('Logged-in', 'clicktally'); ?></option>
                    </select>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="clicktally-kpi-cards">
                <div class="clicktally-kpi-card">
                    <h3><?php echo esc_html__('Total Clicks', 'clicktally'); ?></h3>
                    <div class="clicktally-kpi-value" id="total-clicks">
                        <span class="clicktally-loading"><?php echo esc_html__('Loading...', 'clicktally'); ?></span>
                    </div>
                </div>
                
                <div class="clicktally-kpi-card">
                    <h3><?php echo esc_html__('Unique Elements Clicked', 'clicktally'); ?></h3>
                    <div class="clicktally-kpi-value" id="unique-elements">
                        <span class="clicktally-loading"><?php echo esc_html__('Loading...', 'clicktally'); ?></span>
                    </div>
                </div>
                
                <div class="clicktally-kpi-card">
                    <h3><?php echo esc_html__('Top Page', 'clicktally'); ?></h3>
                    <div class="clicktally-kpi-value" id="top-page">
                        <span class="clicktally-loading"><?php echo esc_html__('Loading...', 'clicktally'); ?></span>
                    </div>
                </div>
                
                <div class="clicktally-kpi-card">
                    <h3><?php echo esc_html__('Events Today', 'clicktally'); ?></h3>
                    <div class="clicktally-kpi-value" id="events-today">
                        <span class="clicktally-loading"><?php echo esc_html__('Loading...', 'clicktally'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Mini Line Chart -->
            <div class="clicktally-chart-section">
                <h2><?php echo esc_html__('Clicks by Day', 'clicktally'); ?></h2>
                <div class="clicktally-chart-container">
                    <svg id="clicktally-line-chart" width="100%" height="300"></svg>
                </div>
            </div>
            
            <!-- Data Tables Container -->
            <div class="clicktally-tables-container">
                <!-- Top Elements Table -->
                <div class="clicktally-table-section">
                    <div class="clicktally-table-header">
                        <h2><?php echo esc_html__('Top Elements', 'clicktally'); ?></h2>
                        <button type="button" class="button" id="export-top-elements">
                            <?php echo esc_html__('Export CSV', 'clicktally'); ?>
                        </button>
                    </div>
                    <div id="top-elements-table" class="clicktally-table-wrapper">
                        <div class="clicktally-loading"><?php echo esc_html__('Loading...', 'clicktally'); ?></div>
                    </div>
                </div>
                
                <!-- Top Pages Table -->
                <div class="clicktally-table-section">
                    <div class="clicktally-table-header">
                        <h2><?php echo esc_html__('Top Pages', 'clicktally'); ?></h2>
                        <button type="button" class="button" id="export-top-pages">
                            <?php echo esc_html__('Export CSV', 'clicktally'); ?>
                        </button>
                    </div>
                    <div id="top-pages-table" class="clicktally-table-wrapper">
                        <div class="clicktally-loading"><?php echo esc_html__('Loading...', 'clicktally'); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Privacy Notice (if tracking disabled) -->
            <?php if (!self::clicktally_element_event_tracker_is_tracking_enabled()): ?>
            <div class="notice notice-info">
                <p><?php echo esc_html__('Tracking is currently disabled in settings. Enable tracking to see data.', 'clicktally'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render Tracking Rules page (delegates to existing functionality for now)
     */
    public static function clicktally_element_event_tracker_render_rules_page() {
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'clicktally'));
        }
        
        // For now, use existing rules page functionality
        if (class_exists('ClickTally_Admin')) {
            ClickTally_Admin::render_rules_page();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Tracking Rules', 'clicktally') . '</h1>';
            echo '<p>' . esc_html__('Rules functionality will be available soon.', 'clicktally') . '</p></div>';
        }
    }
    
    /**
     * Render Test Mode page (delegates to existing functionality for now)
     */
    public static function clicktally_element_event_tracker_render_test_page() {
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'clicktally'));
        }
        
        // For now, use existing test page functionality
        if (class_exists('ClickTally_Admin')) {
            ClickTally_Admin::render_test_page();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Test Mode', 'clicktally') . '</h1>';
            echo '<p>' . esc_html__('Test mode functionality will be available soon.', 'clicktally') . '</p></div>';
        }
    }
    
    /**
     * Render Settings page (delegates to existing functionality for now)
     */
    public static function clicktally_element_event_tracker_render_settings_page() {
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'clicktally'));
        }
        
        // For now, use existing settings page functionality
        if (class_exists('ClickTally_Admin')) {
            ClickTally_Admin::render_settings_page();
        } else {
            echo '<div class="wrap"><h1>' . esc_html__('Settings', 'clicktally') . '</h1>';
            echo '<p>' . esc_html__('Settings functionality will be available soon.', 'clicktally') . '</p></div>';
        }
    }
    
    /**
     * Render Diagnostics page
     */
    public static function clicktally_element_event_tracker_render_diagnostics_page() {
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'clicktally'));
        }
        
        // Get diagnostics data
        $diagnostics = self::clicktally_element_event_tracker_get_diagnostics_data();
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('ClickTally — Diagnostics', 'clicktally'); ?></h1>
            <p><?php echo esc_html__('Use this panel to troubleshoot capability, nonce, and REST API issues.', 'clicktally'); ?></p>
            
            <!-- Capability Status -->
            <div class="clicktally-diagnostic-section">
                <h2><?php echo esc_html__('Capability Status', 'clicktally'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Primary Capability (manage_clicktally_element_event_tracker)', 'clicktally'); ?></th>
                            <td>
                                <span class="clicktally-status-<?php echo $diagnostics['primary_capability'] ? 'ok' : 'error'; ?>">
                                    <?php echo $diagnostics['primary_capability'] ? '✓ ' . __('Granted', 'clicktally') : '✗ ' . __('Missing', 'clicktally'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Legacy Capability (manage_clicktally)', 'clicktally'); ?></th>
                            <td>
                                <span class="clicktally-status-<?php echo $diagnostics['legacy_capability'] ? 'ok' : 'warning'; ?>">
                                    <?php echo $diagnostics['legacy_capability'] ? '✓ ' . __('Granted', 'clicktally') : '- ' . __('Not granted', 'clicktally'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Current User Role', 'clicktally'); ?></th>
                            <td><?php echo esc_html(implode(', ', $diagnostics['user_roles'])); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Migration Status', 'clicktally'); ?></th>
                            <td>
                                <span class="clicktally-status-<?php echo $diagnostics['migration_done'] ? 'ok' : 'warning'; ?>">
                                    <?php echo $diagnostics['migration_done'] ? '✓ ' . __('Completed', 'clicktally') : '⏳ ' . __('Pending/Running', 'clicktally'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Editor Role Access (should be read-only)', 'clicktally'); ?></th>
                            <td>
                                <span class="clicktally-status-<?php echo $diagnostics['editor_readonly'] ? 'ok' : 'warning'; ?>">
                                    <?php echo $diagnostics['editor_readonly'] ? '✓ ' . __('Correctly denied', 'clicktally') : '⚠ ' . __('Has write access (unexpected)', 'clicktally'); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Nonce Status -->
            <div class="clicktally-diagnostic-section">
                <h2><?php echo esc_html__('Nonce Status', 'clicktally'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Admin Nonce Action', 'clicktally'); ?></th>
                            <td><code>clicktally_element_event_tracker_admin</code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Frontend Tracking Nonce Action', 'clicktally'); ?></th>
                            <td><code>clicktally_element_event_tracker_track</code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Current Admin Nonce', 'clicktally'); ?></th>
                            <td><code><?php echo esc_html(wp_create_nonce('clicktally_element_event_tracker_admin')); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Current Tracking Nonce', 'clicktally'); ?></th>
                            <td><code><?php echo esc_html(wp_create_nonce('clicktally_element_event_tracker_track')); ?></code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- REST API Status -->
            <div class="clicktally-diagnostic-section">
                <h2><?php echo esc_html__('REST API Status', 'clicktally'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('REST API Enabled', 'clicktally'); ?></th>
                            <td>
                                <span class="clicktally-status-<?php echo $diagnostics['rest_enabled'] ? 'ok' : 'error'; ?>">
                                    <?php echo $diagnostics['rest_enabled'] ? '✓ ' . __('Enabled', 'clicktally') : '✗ ' . __('Disabled', 'clicktally'); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Primary Namespace', 'clicktally'); ?></th>
                            <td><code><?php echo esc_html(rest_url('clicktally-element-event-tracker/v1/')); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Legacy Namespace', 'clicktally'); ?></th>
                            <td><code><?php echo esc_html(rest_url('clicktally/v1/')); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Permalinks', 'clicktally'); ?></th>
                            <td>
                                <span class="clicktally-status-<?php echo $diagnostics['permalinks_enabled'] ? 'ok' : 'warning'; ?>">
                                    <?php echo $diagnostics['permalinks_enabled'] ? '✓ ' . __('Pretty permalinks enabled', 'clicktally') : '⚠ ' . __('Plain permalinks (may affect REST)', 'clicktally'); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h3><?php echo esc_html__('REST Endpoint Test', 'clicktally'); ?></h3>
                <p>
                    <button type="button" class="button" id="test-rest-endpoints"><?php echo esc_html__('Test REST Endpoints', 'clicktally'); ?></button>
                    <span id="rest-test-result"></span>
                </p>
            </div>
            
            <!-- Debug Information -->
            <div class="clicktally-diagnostic-section">
                <h2><?php echo esc_html__('Debug Information', 'clicktally'); ?></h2>
                <textarea readonly style="width: 100%; height: 200px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($diagnostics['debug_info']); ?></textarea>
            </div>
            
            <!-- Troubleshooting Guide -->
            <div class="clicktally-diagnostic-section">
                <h2><?php echo esc_html__('Troubleshooting Guide', 'clicktally'); ?></h2>
                <div class="clicktally-troubleshooting">
                    <h4><?php echo esc_html__('Common Issues:', 'clicktally'); ?></h4>
                    <ul>
                        <li><strong><?php echo esc_html__('403 Access Denied:', 'clicktally'); ?></strong> <?php echo esc_html__('Check that your user has the manage_clicktally_element_event_tracker capability and that nonces match.', 'clicktally'); ?></li>
                        <li><strong><?php echo esc_html__('404 REST Endpoints:', 'clicktally'); ?></strong> <?php echo esc_html__('Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules.', 'clicktally'); ?></li>
                        <li><strong><?php echo esc_html__('Dashboard not loading:', 'clicktally'); ?></strong> <?php echo esc_html__('Check browser console for JavaScript errors and verify REST API is enabled.', 'clicktally'); ?></li>
                        <li><strong><?php echo esc_html__('Migration issues:', 'clicktally'); ?></strong> <?php echo esc_html__('Deactivate and reactivate the plugin to re-run capability migration.', 'clicktally'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <script>
        document.getElementById('test-rest-endpoints').addEventListener('click', function() {
            const button = this;
            const result = document.getElementById('rest-test-result');
            
            button.disabled = true;
            button.textContent = '<?php echo esc_js(__('Testing...', 'clicktally')); ?>';
            result.textContent = '';
            
            const nonce = '<?php echo wp_create_nonce('clicktally_element_event_tracker_admin'); ?>';
            
            Promise.all([
                fetch('<?php echo rest_url('clicktally/v1/stats/summary'); ?>', {
                    headers: { 'X-WP-Nonce': nonce }
                }),
                fetch('<?php echo rest_url('clicktally-element-event-tracker/v1/stats/summary'); ?>', {
                    headers: { 'X-WP-Nonce': nonce }
                })
            ]).then(responses => {
                const legacy = responses[0];
                const primary = responses[1];
                
                let message = 'Legacy endpoint: ' + legacy.status + ' (' + legacy.statusText + '), ';
                message += 'Primary endpoint: ' + primary.status + ' (' + primary.statusText + ')';
                
                if (legacy.ok && primary.ok) {
                    result.innerHTML = '<span class="clicktally-status-ok">✓ ' + message + '</span>';
                } else {
                    result.innerHTML = '<span class="clicktally-status-error">✗ ' + message + '</span>';
                }
            }).catch(error => {
                result.innerHTML = '<span class="clicktally-status-error">✗ Error: ' + error.message + '</span>';
            }).finally(() => {
                button.disabled = false;
                button.textContent = '<?php echo esc_js(__('Test REST Endpoints', 'clicktally')); ?>';
            });
        });
        </script>
        
        <style>
        .clicktally-diagnostic-section {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .clicktally-status-ok { color: #46b450; font-weight: bold; }
        .clicktally-status-warning { color: #ffb900; font-weight: bold; }
        .clicktally-status-error { color: #dc3232; font-weight: bold; }
        .clicktally-troubleshooting ul {
            list-style-type: disc;
            margin-left: 20px;
        }
        .clicktally-troubleshooting li {
            margin-bottom: 8px;
        }
        </style>
        <?php
    }
    
    /**
     * Check if tracking is enabled
     */
    private static function clicktally_element_event_tracker_is_tracking_enabled() {
        $settings = get_option('ct_settings', array());
        return !isset($settings['disable_tracking']) || !$settings['disable_tracking'];
    }
    
    /**
     * Get diagnostics data for troubleshooting
     */
    private static function clicktally_element_event_tracker_get_diagnostics_data() {
        $current_user = wp_get_current_user();
        
        // Check if editor role has proper read-only access
        $editor_role = get_role('editor');
        $editor_readonly = true;
        if ($editor_role) {
            $editor_readonly = !$editor_role->has_cap('manage_clicktally_element_event_tracker');
        }
        
        return array(
            'primary_capability' => current_user_can('manage_clicktally_element_event_tracker'),
            'legacy_capability' => current_user_can('manage_clicktally'),
            'user_roles' => $current_user->roles,
            'migration_done' => get_option('clicktally_capability_migration_done', false),
            'editor_readonly' => $editor_readonly,
            'rest_enabled' => function_exists('rest_get_server'),
            'permalinks_enabled' => get_option('permalink_structure') !== '',
            'debug_info' => self::clicktally_element_event_tracker_get_debug_info()
        );
    }
    
    /**
     * Get debug information
     */
    private static function clicktally_element_event_tracker_get_debug_info() {
        global $wp_version;
        
        $debug_info = array(
            'WordPress Version: ' . $wp_version,
            'Plugin Version: ' . CLICKTALLY_VERSION,
            'PHP Version: ' . PHP_VERSION,
            'Site URL: ' . home_url(),
            'Admin URL: ' . admin_url(),
            'REST URL: ' . rest_url(),
            'User ID: ' . get_current_user_id(),
            'User Login: ' . wp_get_current_user()->user_login,
            'User Roles: ' . implode(', ', wp_get_current_user()->roles),
            'Capabilities: ' . implode(', ', array_keys(wp_get_current_user()->allcaps)),
            'Permalink Structure: ' . get_option('permalink_structure'),
            'Multisite: ' . (is_multisite() ? 'Yes' : 'No'),
            'Active Plugins: ' . implode(', ', get_option('active_plugins', array())),
            'Database Tables: ' . self::clicktally_element_event_tracker_check_tables()
        );
        
        return implode("\n", $debug_info);
    }
    
    /**
     * Check if database tables exist
     */
    private static function clicktally_element_event_tracker_check_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'ct_events_raw',
            $wpdb->prefix . 'ct_rollup_daily'
        );
        
        $existing = array();
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            $existing[] = basename($table) . ': ' . ($exists ? 'EXISTS' : 'MISSING');
        }
        
        return implode(', ', $existing);
    }
}