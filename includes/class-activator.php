<?php
/**
 * ClickTally Activator Class
 * Handles plugin activation, deactivation, and database setup
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_capabilities();
        self::set_default_settings();
        self::create_auto_track_rules();
        
        // Initialize new capabilities system
        if (class_exists('Clicktally_Element_Event_Tracker_Capabilities')) {
            Clicktally_Element_Event_Tracker_Capabilities::clicktally_element_event_tracker_create_capabilities();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('clicktally_rollup_daily');
        
        // Remove new capabilities if needed (optional, commented out to preserve user access)
        // if (class_exists('Clicktally_Element_Event_Tracker_Capabilities')) {
        //     Clicktally_Element_Event_Tracker_Capabilities::clicktally_element_event_tracker_remove_capabilities();
        // }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Raw events table (short retention)
        $table_events = $wpdb->prefix . 'ct_events_raw';
        $sql_events = "CREATE TABLE $table_events (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            ts DATETIME NOT NULL,
            page_url TEXT NOT NULL,
            page_hash CHAR(16) NOT NULL,
            event_name VARCHAR(120) NOT NULL,
            selector_key CHAR(16) NOT NULL,
            event_type ENUM('click','submit','change','view') DEFAULT 'click',
            label VARCHAR(255) NULL,
            device ENUM('desktop','mobile','tablet','unknown') DEFAULT 'unknown',
            is_logged_in TINYINT(1) DEFAULT 0,
            role VARCHAR(60) NULL,
            referrer VARCHAR(255) NULL,
            utm JSON NULL,
            session_key CHAR(16) NULL,
            ua_hash CHAR(16) NULL,
            ip_hash CHAR(16) NULL,
            PRIMARY KEY (id),
            KEY ts (ts),
            KEY event_name (event_name),
            KEY selector_key (selector_key),
            KEY page_event (page_hash, event_name)
        ) $charset_collate;";
        
        // Daily rollup table (long retention)
        $table_rollup = $wpdb->prefix . 'ct_rollup_daily';
        $sql_rollup = "CREATE TABLE $table_rollup (
            day DATE NOT NULL,
            page_hash CHAR(16) NOT NULL,
            page_url TEXT NOT NULL,
            event_name VARCHAR(120) NOT NULL,
            selector_key CHAR(16) NOT NULL,
            device ENUM('desktop','mobile','tablet','unknown') DEFAULT 'unknown',
            is_logged_in TINYINT(1) DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (day, page_hash, event_name, selector_key, device, is_logged_in),
            KEY day (day),
            KEY event_name (event_name),
            KEY page_hash (page_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_events);
        dbDelta($sql_rollup);
    }
    
    /**
     * Create capabilities
     */
    private static function create_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            // Add old capability for backward compatibility
            $admin_role->add_cap('manage_clicktally');
            // Add new capability with long prefix
            $admin_role->add_cap('manage_clicktally_element_event_tracker');
        }
    }
    
    /**
     * Set default plugin settings
     */
    private static function set_default_settings() {
        $default_settings = array(
            'respect_dnt' => true,
            'track_admins' => false,
            'session_tracking' => false,
            'retention_raw_days' => 30,
            'retention_rollup_months' => 12,
            'auto_track_outbound' => true,
            'auto_track_downloads' => true,
            'auto_track_mailto' => true,
            'auto_track_buttons' => true,
            'ip_hash_salt' => wp_generate_password(32, false),
        );
        
        add_option('ct_settings', $default_settings);
        add_option('ct_rules_version', 1);
    }
    
    /**
     * Create default auto-track rules
     */
    private static function create_auto_track_rules() {
        $auto_rules = array(
            array(
                'post_title' => 'Auto-track: Outbound Links',
                'meta_input' => array(
                    'selector_type' => 'css',
                    'selector_value' => 'a[href^="http"]:not([href*="' . parse_url(home_url(), PHP_URL_HOST) . '"])',
                    'event_name' => 'Outbound Link Click',
                    'event_type' => 'click',
                    'scope_type' => 'global',
                    'label_template' => '{text} → {href}',
                    'auto_rule' => true,
                    'status' => 'active'
                )
            ),
            array(
                'post_title' => 'Auto-track: File Downloads',
                'meta_input' => array(
                    'selector_type' => 'css',
                    'selector_value' => 'a[href$=".pdf"], a[href$=".doc"], a[href$=".docx"], a[href$=".zip"], a[href$=".jpg"], a[href$=".png"]',
                    'event_name' => 'File Download',
                    'event_type' => 'click',
                    'scope_type' => 'global',
                    'label_template' => '{href}',
                    'auto_rule' => true,
                    'status' => 'active'
                )
            ),
            array(
                'post_title' => 'Auto-track: Mailto Links',
                'meta_input' => array(
                    'selector_type' => 'css',
                    'selector_value' => 'a[href^="mailto:"], a[href^="tel:"]',
                    'event_name' => 'Contact Link Click',
                    'event_type' => 'click',
                    'scope_type' => 'global',
                    'label_template' => '{href}',
                    'auto_rule' => true,
                    'status' => 'active'
                )
            ),
            array(
                'post_title' => 'Auto-track: Buttons',
                'meta_input' => array(
                    'selector_type' => 'css',
                    'selector_value' => 'button, .wp-block-button a, .elementor-button, a[role="button"]',
                    'event_name' => 'Button Click',
                    'event_type' => 'click',
                    'scope_type' => 'global',
                    'label_template' => '{text}',
                    'auto_rule' => true,
                    'status' => 'active'
                )
            )
        );
        
        foreach ($auto_rules as $rule) {
            $existing = get_posts(array(
                'post_type' => 'ct_rule',
                'meta_query' => array(
                    array(
                        'key' => 'selector_value',
                        'value' => $rule['meta_input']['selector_value'],
                        'compare' => '='
                    ),
                    array(
                        'key' => 'auto_rule',
                        'value' => true,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));
            
            if (empty($existing)) {
                $rule['post_type'] = 'ct_rule';
                $rule['post_status'] = 'publish';
                wp_insert_post($rule);
            }
        }
        
        // Increment rules version to bust client cache
        update_option('ct_rules_version', get_option('ct_rules_version', 1) + 1);
    }
}