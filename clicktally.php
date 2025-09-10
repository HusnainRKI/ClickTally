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
     * Add admin menu
     */
    public function admin_menu() {
        // Initialize new dashboard menu with long prefixes
        if (current_user_can('manage_clicktally_element_event_tracker')) {
            Clicktally_Element_Event_Tracker_Admin_Menu::init();
        }
        // Keep backward compatibility for old capability
        elseif (current_user_can('manage_clicktally')) {
            ClickTally_Admin::init();
        }
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
            'clicktally-tracker',
            CLICKTALLY_PLUGIN_URL . 'assets/js/tracker.js',
            array(),
            CLICKTALLY_VERSION,
            true
        );
        
        // Localize script with configuration
        wp_localize_script('clicktally-tracker', 'clickTallyConfig', array(
            'apiUrl' => rest_url('clicktally/v1/'),
            'nonce' => wp_create_nonce('clicktally_track'),
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
}

/**
 * Initialize the plugin
 */
function clicktally() {
    return ClickTally::instance();
}

// Start the plugin
clicktally();