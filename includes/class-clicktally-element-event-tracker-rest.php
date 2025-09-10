<?php
/**
 * ClickTally Element Event Tracker REST API Class
 * Enhanced REST endpoints for the dashboard with proper long prefixes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clicktally_Element_Event_Tracker_REST {
    
    /**
     * Initialize REST API endpoints
     */
    public static function clicktally_element_event_tracker_init() {
        add_action('rest_api_init', array(__CLASS__, 'clicktally_element_event_tracker_register_rest_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function clicktally_element_event_tracker_register_rest_routes() {
        // Register new prefixed namespace
        self::clicktally_element_event_tracker_register_dashboard_routes();
        
        // Register backward compatibility routes (reuse existing endpoints from ClickTally_REST)
        self::clicktally_element_event_tracker_register_backcompat_routes();
    }
    
    /**
     * Register dashboard-specific routes with new namespace
     */
    private static function clicktally_element_event_tracker_register_dashboard_routes() {
        $namespace = 'clicktally-element-event-tracker/v1';
        
        // Summary stats endpoint
        register_rest_route($namespace, '/stats/summary', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'clicktally_element_event_tracker_rest_get_summary'),
            'permission_callback' => array(__CLASS__, 'clicktally_element_event_tracker_check_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array(__CLASS__, 'clicktally_element_event_tracker_validate_range')
                ),
                'device' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array(__CLASS__, 'clicktally_element_event_tracker_validate_device')
                ),
                'user' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array(__CLASS__, 'clicktally_element_event_tracker_validate_user_type')
                )
            )
        ));
        
        // Top elements endpoint
        register_rest_route($namespace, '/stats/top-elements', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'clicktally_element_event_tracker_rest_get_top_elements'),
            'permission_callback' => array(__CLASS__, 'clicktally_element_event_tracker_check_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array(__CLASS__, 'clicktally_element_event_tracker_validate_range')
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                ),
                'device' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'user' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
        
        // Top pages endpoint
        register_rest_route($namespace, '/stats/top-pages', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'clicktally_element_event_tracker_rest_get_top_pages'),
            'permission_callback' => array(__CLASS__, 'clicktally_element_event_tracker_check_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => array(__CLASS__, 'clicktally_element_event_tracker_validate_range')
                ),
                'limit' => array(
                    'default' => 10,
                    'sanitize_callback' => 'absint'
                ),
                'device' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'user' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }
    
    /**
     * Register backward compatibility routes (alias to old namespace)
     */
    private static function clicktally_element_event_tracker_register_backcompat_routes() {
        // Keep existing clicktally/v1 namespace working for backward compatibility
        // These will delegate to the existing ClickTally_REST class methods if available
        
        if (!class_exists('ClickTally_REST')) {
            return;
        }
        
        // Override summary endpoint to include timeseries data
        register_rest_route('clicktally/v1', '/stats/summary', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'clicktally_element_event_tracker_rest_get_summary'),
            'permission_callback' => array(__CLASS__, 'clicktally_element_event_tracker_check_permissions'),
            'args' => array(
                'range' => array(
                    'default' => '7d',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'device' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'user' => array(
                    'default' => 'all',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ), true); // Override existing route
    }
    
    /**
     * Get summary statistics
     */
    public static function clicktally_element_event_tracker_rest_get_summary($request) {
        $range = $request->get_param('range');
        $device = $request->get_param('device');
        $user_type = $request->get_param('user');
        
        $date_bounds = self::clicktally_element_event_tracker_get_date_range_bounds($range);
        $filters = self::clicktally_element_event_tracker_build_filters($device, $user_type);
        
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Build base WHERE clause
        $where_conditions = array("day >= %s AND day <= %s");
        $params = array($date_bounds['start'], $date_bounds['end']);
        
        foreach ($filters as $condition => $value) {
            $where_conditions[] = $condition;
            $params[] = $value;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total clicks
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM {$table} WHERE {$where_clause}",
            $params
        ));
        
        // Get unique elements
        $unique_elements = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT event_name) FROM {$table} WHERE {$where_clause}",
            $params
        ));
        
        // Get top page
        $top_page_result = $wpdb->get_row($wpdb->prepare(
            "SELECT page_url, SUM(clicks) as total_clicks 
             FROM {$table} 
             WHERE {$where_clause}
             GROUP BY page_hash, page_url 
             ORDER BY total_clicks DESC 
             LIMIT 1",
            $params
        ));
        
        // Get events today
        $today = gmdate('Y-m-d');
        $today_params = array_merge(array($today, $today), array_slice($params, 2));
        $events_today = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM {$table} WHERE day = %s AND day = %s" . 
            (count($filters) > 0 ? ' AND ' . implode(' AND ', array_keys($filters)) : ''),
            $today_params
        ));
        
        // Get timeseries data
        $timeseries = self::clicktally_element_event_tracker_get_timeseries_data($date_bounds, $filters);
        
        $top_page = null;
        if ($top_page_result) {
            $top_page = array(
                'page' => $top_page_result->page_url,
                'clicks' => (int) $top_page_result->total_clicks,
                'title' => self::clicktally_element_event_tracker_esc_title($top_page_result->page_url)
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => array(
                'total_clicks' => (int) ($total_clicks ?: 0),
                'unique_elements' => (int) ($unique_elements ?: 0),
                'top_page' => $top_page,
                'events_today' => (int) ($events_today ?: 0),
                'timeseries' => $timeseries
            )
        ));
    }
    
    /**
     * Get top clicked elements
     */
    public static function clicktally_element_event_tracker_rest_get_top_elements($request) {
        $range = $request->get_param('range');
        $limit = min($request->get_param('limit'), 100); // Cap at 100
        $device = $request->get_param('device');
        $user_type = $request->get_param('user');
        
        $date_bounds = self::clicktally_element_event_tracker_get_date_range_bounds($range);
        $filters = self::clicktally_element_event_tracker_build_filters($device, $user_type);
        
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Build WHERE clause
        $where_conditions = array("day >= %s AND day <= %s");
        $params = array($date_bounds['start'], $date_bounds['end']);
        
        foreach ($filters as $condition => $value) {
            $where_conditions[] = $condition;
            $params[] = $value;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total clicks for percentage calculation
        $total_clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(clicks) FROM {$table} WHERE {$where_clause}",
            $params
        ));
        
        // Get top elements with example pages
        $params[] = $limit;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_name,
                selector_key,
                SUM(clicks) as clicks,
                COUNT(DISTINCT page_hash) as page_count,
                (SELECT page_url FROM {$table} t2 
                 WHERE t2.event_name = t1.event_name 
                 AND t2.selector_key = t1.selector_key 
                 AND {$where_clause}
                 ORDER BY t2.clicks DESC 
                 LIMIT 1) as example_page
             FROM {$table} t1
             WHERE {$where_clause}
             GROUP BY event_name, selector_key 
             ORDER BY clicks DESC 
             LIMIT %d",
            array_merge($params, $params)
        ));
        
        // Process results
        $elements = array();
        foreach ($results as $result) {
            $page_share = $total_clicks > 0 ? ($result->clicks / $total_clicks) : 0;
            
            $elements[] = array(
                'event_name' => $result->event_name,
                'selector_key' => $result->selector_key,
                'selector_preview' => self::clicktally_element_event_tracker_get_selector_preview($result->selector_key),
                'example_page' => $result->example_page,
                'clicks' => (int) $result->clicks,
                'page_share' => (float) $page_share
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $elements
        ));
    }
    
    /**
     * Get top pages by clicks
     */
    public static function clicktally_element_event_tracker_rest_get_top_pages($request) {
        $range = $request->get_param('range');
        $limit = min($request->get_param('limit'), 100); // Cap at 100
        $device = $request->get_param('device');
        $user_type = $request->get_param('user');
        
        $date_bounds = self::clicktally_element_event_tracker_get_date_range_bounds($range);
        $filters = self::clicktally_element_event_tracker_build_filters($device, $user_type);
        
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Build WHERE clause
        $where_conditions = array("day >= %s AND day <= %s");
        $params = array($date_bounds['start'], $date_bounds['end']);
        
        foreach ($filters as $condition => $value) {
            $where_conditions[] = $condition;
            $params[] = $value;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $params[] = $limit;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                page_url,
                SUM(clicks) as clicks,
                (SELECT event_name FROM {$table} t2 
                 WHERE t2.page_hash = t1.page_hash 
                 AND {$where_clause}
                 GROUP BY event_name 
                 ORDER BY SUM(clicks) DESC 
                 LIMIT 1) as top_event
             FROM {$table} t1
             WHERE {$where_clause}
             GROUP BY page_hash, page_url 
             ORDER BY clicks DESC 
             LIMIT %d",
            array_merge($params, $params)
        ));
        
        // Process results
        $pages = array();
        foreach ($results as $result) {
            $pages[] = array(
                'page' => $result->page_url,
                'title' => self::clicktally_element_event_tracker_esc_title($result->page_url),
                'clicks' => (int) $result->clicks,
                'top_event' => $result->top_event ?: __('No events', 'clicktally')
            );
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data' => $pages
        ));
    }
    
    /**
     * Get timeseries data for chart
     */
    private static function clicktally_element_event_tracker_get_timeseries_data($date_bounds, $filters) {
        global $wpdb;
        $table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Build WHERE clause
        $where_conditions = array("day >= %s AND day <= %s");
        $params = array($date_bounds['start'], $date_bounds['end']);
        
        foreach ($filters as $condition => $value) {
            $where_conditions[] = $condition;
            $params[] = $value;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT day, SUM(clicks) as clicks 
             FROM {$table} 
             WHERE {$where_clause}
             GROUP BY day 
             ORDER BY day ASC",
            $params
        ));
        
        // Fill in missing days with zero values
        $timeseries = array();
        $current_date = new DateTime($date_bounds['start']);
        $end_date = new DateTime($date_bounds['end']);
        
        // Create lookup array for existing data
        $data_lookup = array();
        foreach ($results as $result) {
            $data_lookup[$result->day] = (int) $result->clicks;
        }
        
        // Generate complete timeseries
        while ($current_date <= $end_date) {
            $day_key = $current_date->format('Y-m-d');
            $timeseries[] = array(
                'day' => $day_key,
                'clicks' => isset($data_lookup[$day_key]) ? $data_lookup[$day_key] : 0
            );
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $timeseries;
    }
    
    /**
     * Get date range bounds from range parameter
     */
    private static function clicktally_element_event_tracker_get_date_range_bounds($range) {
        $end_date = gmdate('Y-m-d');
        
        switch ($range) {
            case '30d':
                $days = 30;
                break;
            case '90d':
                $days = 90;
                break;
            case '7d':
            default:
                $days = 7;
                break;
        }
        
        $start_date = gmdate('Y-m-d', strtotime("-{$days} days"));
        
        return array(
            'start' => $start_date,
            'end' => $end_date
        );
    }
    
    /**
     * Build filters array from device and user type
     */
    private static function clicktally_element_event_tracker_build_filters($device, $user_type) {
        $filters = array();
        
        if ($device && $device !== 'all') {
            $filters['device = %s'] = $device;
        }
        
        if ($user_type && $user_type !== 'all') {
            if ($user_type === 'guests') {
                $filters['is_logged_in = %d'] = 0;
            } elseif ($user_type === 'logged-in') {
                $filters['is_logged_in = %d'] = 1;
            }
        }
        
        return $filters;
    }
    
    /**
     * Get selector preview from selector key
     */
    private static function clicktally_element_event_tracker_get_selector_preview($selector_key) {
        // This would typically look up the actual selector from rules
        // For now, return a placeholder
        return 'selector-' . substr($selector_key, 0, 8);
    }
    
    /**
     * Escape and format page title
     */
    private static function clicktally_element_event_tracker_esc_title($url) {
        if (empty($url)) {
            return __('Unknown Page', 'clicktally');
        }
        
        // Extract page title from URL
        $path = parse_url($url, PHP_URL_PATH);
        if ($path === '/' || empty($path)) {
            return __('Home', 'clicktally');
        }
        
        $segments = array_filter(explode('/', trim($path, '/')));
        if (empty($segments)) {
            return __('Home', 'clicktally');
        }
        
        $title = end($segments);
        $title = ucwords(str_replace(array('-', '_'), ' ', $title));
        
        return esc_html($title);
    }
    
    /**
     * Check permissions for admin endpoints
     */
    public static function clicktally_element_event_tracker_check_permissions() {
        return current_user_can('manage_clicktally_element_event_tracker');
    }
    
    /**
     * Validate range parameter
     */
    public static function clicktally_element_event_tracker_validate_range($value) {
        return in_array($value, array('7d', '30d', '90d'), true);
    }
    
    /**
     * Validate device parameter
     */
    public static function clicktally_element_event_tracker_validate_device($value) {
        return in_array($value, array('all', 'desktop', 'mobile', 'tablet'), true);
    }
    
    /**
     * Validate user type parameter
     */
    public static function clicktally_element_event_tracker_validate_user_type($value) {
        return in_array($value, array('all', 'guests', 'logged-in'), true);
    }
}