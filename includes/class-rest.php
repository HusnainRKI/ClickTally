<?php
/**
 * ClickTally REST API Class
 * Handles REST API endpoints for data ingestion and rules
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_REST {
    
    /**
     * Initialize REST API
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Public routes
        register_rest_route('clicktally/v1', '/rules', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_rules'),
            'permission_callback' => '__return_true',
            'args' => array(
                'ver' => array(
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        register_rest_route('clicktally/v1', '/ingest', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'ingest_events'),
            'permission_callback' => '__return_true'
        ));
        
        // Admin routes (require manage_clicktally capability)
        register_rest_route('clicktally/v1', '/stats/summary', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_stats_summary'),
            'permission_callback' => array(__CLASS__, 'check_admin_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'device' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'user_type' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        register_rest_route('clicktally/v1', '/stats/top-elements', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_top_elements'),
            'permission_callback' => array(__CLASS__, 'check_admin_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        register_rest_route('clicktally/v1', '/stats/top-pages', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_top_pages'),
            'permission_callback' => array(__CLASS__, 'check_admin_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                )
            )
        ));
        
        register_rest_route('clicktally/v1', '/rules/manage', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'manage_rule'),
            'permission_callback' => array(__CLASS__, 'check_admin_permissions')
        ));
        
        register_rest_route('clicktally/v1', '/test/preview', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'preview_rule'),
            'permission_callback' => array(__CLASS__, 'check_admin_permissions')
        ));
    }
    
    /**
     * Get rules for client consumption
     */
    public static function get_rules($request) {
        $version = $request->get_param('ver');
        $current_version = get_option('ct_rules_version', 1);
        
        // Return 304 if client has current version
        if ($version >= $current_version) {
            status_header(304);
            exit;
        }
        
        $rules = ClickTally_Rules::get_client_rules();
        
        return rest_ensure_response(array(
            'version' => $current_version,
            'rules' => $rules
        ));
    }
    
    /**
     * Ingest tracking events
     */
    public static function ingest_events($request) {
        $events = $request->get_json_params();
        
        if (!is_array($events)) {
            return new WP_Error('invalid_data', 'Events must be an array', array('status' => 400));
        }
        
        // Validate origin and rate limiting
        if (!self::validate_ingest_request($request)) {
            return new WP_Error('invalid_request', 'Request validation failed', array('status' => 403));
        }
        
        $processed = 0;
        foreach ($events as $event) {
            if (ClickTally_Ingest::process_event($event)) {
                $processed++;
            }
        }
        
        return rest_ensure_response(array(
            'processed' => $processed,
            'total' => count($events)
        ));
    }
    
    /**
     * Get stats summary
     */
    public static function get_stats_summary($request) {
        $range = $request->get_param('range');
        $device = $request->get_param('device');
        $user_type = $request->get_param('user_type');
        
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Calculate date range
        $days = $range === '30d' ? 30 : 7;
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        // Build WHERE clause
        $where = array("day >= %s");
        $params = array($start_date);
        
        if ($device !== 'all') {
            $where[] = "device = %s";
            $params[] = $device;
        }
        
        if ($user_type === 'guest') {
            $where[] = "is_logged_in = 0";
        } elseif ($user_type === 'logged_in') {
            $where[] = "is_logged_in = 1";
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get total clicks
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM {$table} WHERE {$where_clause}",
            $params
        ));
        
        // Get unique elements count
        $unique_elements = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT event_name) FROM {$table} WHERE {$where_clause}",
            $params
        ));
        
        // Get top page
        $top_page = $wpdb->get_row($wpdb->prepare(
            "SELECT page_url, SUM(clicks) as total_clicks 
             FROM {$table} 
             WHERE {$where_clause}
             GROUP BY page_hash 
             ORDER BY total_clicks DESC 
             LIMIT 1",
            $params
        ));
        
        return rest_ensure_response(array(
            'total_clicks' => (int) $total_clicks,
            'unique_elements' => (int) $unique_elements,
            'top_page' => $top_page ? array(
                'url' => $top_page->page_url,
                'clicks' => (int) $top_page->total_clicks
            ) : null,
            'range' => $range,
            'filters' => array(
                'device' => $device,
                'user_type' => $user_type
            )
        ));
    }
    
    /**
     * Get top clicked elements
     */
    public static function get_top_elements($request) {
        $range = $request->get_param('range');
        $limit = $request->get_param('limit');
        
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        $days = $range === '30d' ? 30 : 7;
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT event_name, SUM(clicks) as total_clicks,
                    COUNT(DISTINCT page_hash) as page_count
             FROM {$table} 
             WHERE day >= %s
             GROUP BY event_name 
             ORDER BY total_clicks DESC 
             LIMIT %d",
            $start_date,
            $limit
        ));
        
        // Calculate percentages
        $total_clicks = array_sum(array_column($results, 'total_clicks'));
        
        foreach ($results as &$result) {
            $result->percentage = $total_clicks > 0 ? round(($result->total_clicks / $total_clicks) * 100, 2) : 0;
        }
        
        return rest_ensure_response($results);
    }
    
    /**
     * Get top pages by clicks
     */
    public static function get_top_pages($request) {
        $range = $request->get_param('range');
        $limit = $request->get_param('limit');
        
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        $days = $range === '30d' ? 30 : 7;
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT page_url, SUM(clicks) as total_clicks,
                    (SELECT event_name FROM {$table} t2 
                     WHERE t2.page_hash = t1.page_hash AND t2.day >= %s
                     GROUP BY event_name 
                     ORDER BY SUM(clicks) DESC 
                     LIMIT 1) as top_event
             FROM {$table} t1
             WHERE day >= %s
             GROUP BY page_hash, page_url 
             ORDER BY total_clicks DESC 
             LIMIT %d",
            $start_date,
            $start_date,
            $limit
        ));
        
        return rest_ensure_response($results);
    }
    
    /**
     * Manage rules (create, update, delete)
     */
    public static function manage_rule($request) {
        $action = $request->get_param('action');
        $rule_data = $request->get_json_params();
        
        switch ($action) {
            case 'create':
                $result = ClickTally_Rules::create_rule($rule_data);
                if ($result) {
                    return rest_ensure_response(array('success' => true, 'rule_id' => $result));
                }
                break;
                
            case 'update':
                $rule_id = $request->get_param('rule_id');
                $result = ClickTally_Rules::update_rule($rule_id, $rule_data);
                if ($result) {
                    return rest_ensure_response(array('success' => true));
                }
                break;
                
            case 'delete':
                $rule_id = $request->get_param('rule_id');
                $result = ClickTally_Rules::delete_rule($rule_id);
                if ($result) {
                    return rest_ensure_response(array('success' => true));
                }
                break;
        }
        
        return new WP_Error('operation_failed', 'Rule operation failed', array('status' => 500));
    }
    
    /**
     * Preview rule functionality
     */
    public static function preview_rule($request) {
        $url = $request->get_param('url');
        $selector_type = $request->get_param('selector_type');
        $selector_value = $request->get_param('selector_value');
        
        // Basic validation
        if (!ClickTally_Rules::validate_selector($selector_type, $selector_value)) {
            return new WP_Error('invalid_selector', 'Invalid selector', array('status' => 400));
        }
        
        // Return preview data (actual DOM parsing would require more complex implementation)
        return rest_ensure_response(array(
            'valid' => true,
            'selector_type' => $selector_type,
            'selector_value' => $selector_value,
            'preview_url' => $url
        ));
    }
    
    /**
     * Check admin permissions
     */
    public static function check_admin_permissions() {
        return current_user_can('manage_clicktally');
    }
    
    /**
     * Validate ingest request
     */
    private static function validate_ingest_request($request) {
        // Check nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'clicktally_track')) {
            return false;
        }
        
        // Check referer domain
        $referer = $request->get_header('Referer');
        if ($referer) {
            $referer_host = parse_url($referer, PHP_URL_HOST);
            $site_host = parse_url(home_url(), PHP_URL_HOST);
            
            if ($referer_host !== $site_host) {
                return false;
            }
        }
        
        // Basic rate limiting (could be enhanced)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $rate_limit_key = 'ct_rate_limit_' . md5($ip);
        $current_count = get_transient($rate_limit_key);
        
        if ($current_count === false) {
            set_transient($rate_limit_key, 1, MINUTE_IN_SECONDS);
        } elseif ($current_count >= 100) { // 100 requests per minute
            return false;
        } else {
            set_transient($rate_limit_key, $current_count + 1, MINUTE_IN_SECONDS);
        }
        
        return true;
    }
}