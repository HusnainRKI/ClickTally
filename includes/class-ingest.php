<?php
/**
 * ClickTally Ingest Class
 * Handles event data ingestion and processing
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_Ingest {
    
    /**
     * Initialize data ingestion
     */
    public static function init() {
        // No specific initialization needed for now
    }
    
    /**
     * Process a single tracking event
     */
    public static function process_event($event_data) {
        // Validate event data
        if (!self::validate_event($event_data)) {
            return false;
        }
        
        // Check if tracking should be skipped
        if (!self::should_track($event_data)) {
            return false;
        }
        
        // Process and insert event
        return self::insert_event($event_data);
    }
    
    /**
     * Validate event data
     */
    private static function validate_event($event) {
        $required_fields = array('ts', 'page_url', 'event_name', 'selector_key', 'event_type');
        
        foreach ($required_fields as $field) {
            if (!isset($event[$field]) || empty($event[$field])) {
                return false;
            }
        }
        
        // Validate event type
        $valid_types = array('click', 'submit', 'change', 'view');
        if (!in_array($event['event_type'], $valid_types)) {
            return false;
        }
        
        // Validate timestamp
        if (!is_numeric($event['ts']) || $event['ts'] < 0) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if tracking should proceed
     */
    private static function should_track($event) {
        // Check DNT header
        $settings = get_option('ct_settings', array());
        if (!empty($settings['respect_dnt'])) {
            $dnt = $_SERVER['HTTP_DNT'] ?? '';
            if ($dnt === '1') {
                return false;
            }
        }
        
        // Apply filter for custom tracking logic
        return apply_filters('clicktally_should_track', true, $event);
    }
    
    /**
     * Insert event into database
     */
    private static function insert_event($event) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ct_events_raw';
        
        // Prepare event data
        $data = array(
            'ts' => gmdate('Y-m-d H:i:s', $event['ts'] / 1000), // Convert from milliseconds
            'page_url' => esc_url_raw($event['page_url']),
            'page_hash' => self::generate_page_hash($event['page_url']),
            'event_name' => sanitize_text_field($event['event_name']),
            'selector_key' => sanitize_text_field($event['selector_key']),
            'event_type' => sanitize_text_field($event['event_type']),
            'label' => isset($event['label']) ? sanitize_text_field($event['label']) : null,
            'device' => self::detect_device($event),
            'is_logged_in' => !empty($event['is_logged_in']) ? 1 : 0,
            'role' => isset($event['role']) ? sanitize_text_field($event['role']) : null,
            'referrer' => isset($event['referrer']) ? esc_url_raw($event['referrer']) : null,
            'utm' => isset($event['utm']) ? wp_json_encode($event['utm']) : null,
            'session_key' => isset($event['session_key']) ? sanitize_text_field($event['session_key']) : null,
            'ua_hash' => self::hash_user_agent(),
            'ip_hash' => self::hash_ip_address()
        );
        
        $formats = array(
            '%s', // ts
            '%s', // page_url
            '%s', // page_hash
            '%s', // event_name
            '%s', // selector_key
            '%s', // event_type
            '%s', // label
            '%s', // device
            '%d', // is_logged_in
            '%s', // role
            '%s', // referrer
            '%s', // utm
            '%s', // session_key
            '%s', // ua_hash
            '%s'  // ip_hash
        );
        
        $result = $wpdb->insert($table, $data, $formats);
        
        if ($result === false) {
            error_log('ClickTally: Failed to insert event - ' . $wpdb->last_error);
            return false;
        }
        
        // Schedule rollup if not already scheduled
        if (!wp_next_scheduled('clicktally_rollup_events')) {
            wp_schedule_event(time() + 300, 'hourly', 'clicktally_rollup_events'); // Start in 5 minutes
        }
        
        return true;
    }
    
    /**
     * Generate page hash for efficient querying
     */
    private static function generate_page_hash($url) {
        // Normalize URL (remove query params for basic grouping)
        $parsed = parse_url($url);
        $normalized = $parsed['scheme'] . '://' . $parsed['host'] . ($parsed['path'] ?? '/');
        
        return substr(md5($normalized), 0, 16);
    }
    
    /**
     * Detect device type
     */
    private static function detect_device($event) {
        // Use client-provided device info if available
        if (isset($event['device']) && in_array($event['device'], array('desktop', 'mobile', 'tablet'))) {
            return $event['device'];
        }
        
        // Fallback to user agent detection
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
            if (preg_match('/iPad/', $user_agent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }
    
    /**
     * Hash user agent for privacy
     */
    private static function hash_user_agent() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($user_agent)) {
            return null;
        }
        
        $settings = get_option('ct_settings', array());
        $salt = $settings['ip_hash_salt'] ?? 'clicktally_default_salt';
        
        return substr(md5($salt . $user_agent), 0, 16);
    }
    
    /**
     * Hash IP address for privacy
     */
    private static function hash_ip_address() {
        $ip = self::get_real_ip();
        if (empty($ip)) {
            return null;
        }
        
        $settings = get_option('ct_settings', array());
        $salt = $settings['ip_hash_salt'] ?? 'clicktally_default_salt';
        
        return substr(md5($salt . $ip), 0, 16);
    }
    
    /**
     * Get real IP address
     */
    private static function get_real_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Clean up old raw events based on retention setting
     */
    public static function cleanup_old_events() {
        global $wpdb;
        
        $settings = get_option('ct_settings', array());
        $retention_days = $settings['retention_raw_days'] ?? 30;
        
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        $table = $wpdb->prefix . 'ct_events_raw';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE ts < %s",
            $cutoff_date
        ));
        
        if ($deleted !== false) {
            error_log("ClickTally: Cleaned up {$deleted} old events");
        }
        
        return $deleted;
    }
    
    /**
     * Get event statistics for debugging
     */
    public static function get_event_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ct_events_raw';
        
        $stats = array(
            'total_events' => $wpdb->get_var("SELECT COUNT(*) FROM {$table}"),
            'events_last_24h' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE ts >= %s",
                gmdate('Y-m-d H:i:s', strtotime('-24 hours'))
            )),
            'unique_pages' => $wpdb->get_var("SELECT COUNT(DISTINCT page_hash) FROM {$table}"),
            'unique_events' => $wpdb->get_var("SELECT COUNT(DISTINCT event_name) FROM {$table}")
        );
        
        return $stats;
    }
}