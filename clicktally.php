<?php
/**
 * Plugin Name: ClickTally - Element Event Tracker
 * Plugin URI: https://github.com/HusnainRKI/ClickTally
 * Description: A lightweight, privacy-first WordPress plugin that tracks clicks and events on elements without requiring GTM/GA.
 * Version: 1.0.0
 * Author: ClickTally Team
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clicktally
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLICKTALLY_VERSION', '1.0.0');
define('CLICKTALLY_PLUGIN_FILE', __FILE__);
define('CLICKTALLY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLICKTALLY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLICKTALLY_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main ClickTally Plugin Class
 */
class ClickTally {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Add plugin action links
        add_filter('plugin_action_links_' . CLICKTALLY_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // Add deactivation confirmation
        add_action('admin_enqueue_scripts', array($this, 'enqueue_deactivation_script'));
        add_action('wp_ajax_clicktally_deactivation_feedback', array($this, 'handle_deactivation_feedback'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-activator.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-capabilities.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-rules.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-rest.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-ingest.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-rollup.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-admin.php';
        
        // Load new dashboard classes with long prefixes
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-clicktally-element-event-tracker-admin-menu.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-clicktally-element-event-tracker-rest.php';
        require_once CLICKTALLY_PLUGIN_DIR . 'includes/class-clicktally-element-event-tracker-capabilities.php';
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Ensure capabilities are properly set up
        $this->ensure_capabilities();
        
        // Initialize components
        ClickTally_Capabilities::init();
        ClickTally_Rules::init();
        ClickTally_REST::init();
        ClickTally_Ingest::init();
        ClickTally_Rollup::init();
        
        // Initialize new dashboard components with long prefixes
        Clicktally_Element_Event_Tracker_Capabilities::clicktally_element_event_tracker_init();
        Clicktally_Element_Event_Tracker_REST::clicktally_element_event_tracker_init();
        
        // Load text domain
        load_plugin_textdomain('clicktally', false, dirname(CLICKTALLY_PLUGIN_BASENAME) . '/languages/');
        
        // Enqueue front-end tracker for non-admin users or if setting allows
        if (!current_user_can('manage_clicktally') || $this->should_track_admins()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_tracker'));
        }
    }
    
    /**
     * Ensure capabilities are properly granted
     */
    private function ensure_capabilities() {
        // Run silent migration for existing installations
        $this->migrate_capabilities();
        
        // Ensure administrator role has the primary capability
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('manage_clicktally_element_event_tracker')) {
            $admin_role->add_cap('manage_clicktally_element_event_tracker');
        }
    }
    
    /**
     * Silent capability migration for existing installations
     */
    private function migrate_capabilities() {
        // Get migration flag
        $migration_done = get_option('clicktally_capability_migration_done', false);
        if ($migration_done) {
            return; // Migration already completed
        }
        
        // Migrate all users who have old capabilities to new capability
        $users_with_old_cap = get_users(array(
            'meta_key' => 'wp_capabilities',
            'meta_value' => 'manage_clicktally',
            'meta_compare' => 'LIKE'
        ));
        
        foreach ($users_with_old_cap as $user) {
            if (user_can($user, 'manage_clicktally') && !user_can($user, 'manage_clicktally_element_event_tracker')) {
                $user->add_cap('manage_clicktally_element_event_tracker');
            }
        }
        
        // Migrate roles
        $wp_roles = wp_roles();
        foreach ($wp_roles->roles as $role_name => $role_info) {
            if (isset($role_info['capabilities']['manage_clicktally']) && 
                $role_info['capabilities']['manage_clicktally'] &&
                (!isset($role_info['capabilities']['manage_clicktally_element_event_tracker']) ||
                 !$role_info['capabilities']['manage_clicktally_element_event_tracker'])) {
                
                $role = get_role($role_name);
                if ($role) {
                    $role->add_cap('manage_clicktally_element_event_tracker');
                }
            }
        }
        
        // Set migration flag
        update_option('clicktally_capability_migration_done', true);
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        // Ensure capabilities are available
        $this->ensure_capabilities();
        
        // Use primary capability for menu access
        if (current_user_can('manage_clicktally_element_event_tracker')) {
            Clicktally_Element_Event_Tracker_Admin_Menu::init();
        }
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=clicktally-element-event-tracker-settings') . '">' . __('Settings', 'clicktally') . '</a>';
        $dashboard_link = '<a href="' . admin_url('admin.php?page=clicktally-element-event-tracker') . '">' . __('Dashboard', 'clicktally') . '</a>';
        
        array_unshift($links, $settings_link, $dashboard_link);
        return $links;
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        ClickTally_Activator::activate();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        ClickTally_Activator::deactivate();
    }
    
    /**
     * Enqueue front-end tracker script
     */
    public function enqueue_tracker() {
        wp_enqueue_script(
            'clicktally-element-event-tracker-frontend-script',
            CLICKTALLY_PLUGIN_URL . 'assets/js/tracker.js',
            array(),
            CLICKTALLY_VERSION,
            true
        );
        
        // Localize script with configuration
        wp_localize_script('clicktally-element-event-tracker-frontend-script', 'clickTallyConfig', array(
            'apiUrl' => rest_url('clicktally/v1/'),
            'nonce' => wp_create_nonce('clicktally_element_event_tracker_track'),
            'rulesVersion' => get_option('ct_rules_version', 1),
            'respectDNT' => $this->get_setting('respect_dnt', true),
            'sessionTracking' => $this->get_setting('session_tracking', false),
            'isLoggedIn' => is_user_logged_in(),
            'userRole' => is_user_logged_in() ? $this->get_user_role() : null,
        ));
    }
    
    /**
     * Check if admins should be tracked
     */
    private function should_track_admins() {
        return $this->get_setting('track_admins', false);
    }
    
    /**
     * Get plugin setting
     */
    private function get_setting($key, $default = null) {
        $settings = get_option('ct_settings', array());
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * Get current user role
     */
    private function get_user_role() {
        $user = wp_get_current_user();
        return !empty($user->roles) ? $user->roles[0] : null;
    }
    
    /**
     * Enqueue deactivation confirmation script
     */
    public function enqueue_deactivation_script($hook) {
        global $pagenow;
        
        // Only load on plugins page
        if ($pagenow === 'plugins.php') {
            wp_enqueue_script(
                'clicktally-element-event-tracker-deactivation-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/deactivation.js',
                array('jquery'),
                CLICKTALLY_VERSION,
                true
            );
            
            wp_localize_script('clicktally-element-event-tracker-deactivation-script', 'clickTallyElementEventTrackerDeactivation', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('clicktally_deactivation'),
                'pluginSlug' => CLICKTALLY_PLUGIN_BASENAME,
                'i18n' => array(
                    'title' => __('ClickTally Deactivation', 'clicktally'),
                    'message' => __('Before deactivating ClickTally, would you like to:', 'clicktally'),
                    'keepData' => __('Keep all tracking data', 'clicktally'),
                    'deleteData' => __('Delete all tracking data', 'clicktally'),
                    'justDeactivate' => __('Just deactivate (keep data)', 'clicktally'),
                    'cancel' => __('Cancel', 'clicktally'),
                    'proceed' => __('Proceed with Deactivation', 'clicktally'),
                    'warning' => __('Warning: This action cannot be undone!', 'clicktally')
                )
            ));
            
            wp_enqueue_style(
                'clicktally-element-event-tracker-deactivation-style',
                CLICKTALLY_PLUGIN_URL . 'assets/css/deactivation.css',
                array(),
                CLICKTALLY_VERSION
            );
        }
    }
    
    /**
     * Handle deactivation feedback
     */
    public function handle_deactivation_feedback() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'clicktally_deactivation')) {
            wp_die(__('Security check failed', 'clicktally'));
        }
        
        // Check capabilities
        if (!current_user_can('activate_plugins')) {
            wp_die(__('Insufficient permissions', 'clicktally'));
        }
        
        $action = sanitize_text_field($_POST['action_type'] ?? '');
        
        if ($action === 'delete_data') {
            // Store flag to delete data on uninstall
            update_option('clicktally_delete_on_uninstall', true);
        } else {
            // Keep data - remove the flag
            delete_option('clicktally_delete_on_uninstall');
        }
        
        wp_send_json_success(array('message' => __('Preference saved. You can now deactivate the plugin.', 'clicktally')));
    }
}

/**
 * Initialize the plugin
 */
function clicktally() {
    return ClickTally::instance();
}

// Start the plugin
clicktally();