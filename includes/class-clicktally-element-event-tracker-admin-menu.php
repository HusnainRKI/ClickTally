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
                CLICKTALLY_VERSION
            );
            
            wp_enqueue_script(
                'clicktally-element-event-tracker-admin-dashboard-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/admin-dashboard.js',
                array(),
                CLICKTALLY_VERSION,
                true
            );
            
            // Localize dashboard config
            wp_localize_script(
                'clicktally-element-event-tracker-admin-dashboard-script',
                'ClickTallyElementEventTrackerAdminConfig',
                array(
                    'nonce' => wp_create_nonce('clicktally_element_event_tracker_dashboard'),
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
                        'exportError' => __('Error exporting data.', 'clicktally')
                    )
                )
            );
            
        } elseif (strpos($hook_suffix, 'clicktally-element-event-tracker') !== false) {
            // Load basic styles for other ClickTally pages
            wp_enqueue_style(
                'clicktally-element-event-tracker-admin-basic-style',
                CLICKTALLY_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                CLICKTALLY_VERSION
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
     * Check if tracking is enabled
     */
    private static function clicktally_element_event_tracker_is_tracking_enabled() {
        $settings = get_option('ct_settings', array());
        return !isset($settings['disable_tracking']) || !$settings['disable_tracking'];
    }
}