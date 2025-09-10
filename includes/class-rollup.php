<?php
/**
 * ClickTally Rollup Class
 * Handles data aggregation from raw events to daily rollups
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_Rollup {
    
    /**
     * Initialize rollup system
     */
    public static function init() {
        add_action('clicktally_rollup_events', array(__CLASS__, 'process_rollup'));
        add_action('clicktally_cleanup_old_data', array(__CLASS__, 'cleanup_old_rollups'));
        
        // Schedule daily cleanup if not already scheduled
        if (!wp_next_scheduled('clicktally_cleanup_old_data')) {
            wp_schedule_event(time(), 'daily', 'clicktally_cleanup_old_data');
        }
    }
    
    /**
     * Process events rollup
     */
    public static function process_rollup() {
        $processed = self::rollup_daily_events();
        
        if ($processed > 0) {
            error_log("ClickTally: Rolled up {$processed} events");
        }
        
        return $processed;
    }
    
    /**
     * Roll up raw events into daily aggregates
     */
    private static function rollup_daily_events() {
        global $wpdb;
        
        $raw_table = $wpdb->prefix . 'ct_events_raw';
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Get the last rollup date
        $last_rollup = get_option('ct_last_rollup_date', gmdate('Y-m-d', strtotime('-1 day')));
        $today = gmdate('Y-m-d');
        
        // Process each day since last rollup
        $current_date = $last_rollup;
        $total_processed = 0;
        
        while ($current_date <= $today) {
            $processed = self::rollup_single_day($current_date);
            $total_processed += $processed;
            
            // Move to next day
            $current_date = gmdate('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        // Update last rollup date
        update_option('ct_last_rollup_date', $today);
        
        return $total_processed;
    }
    
    /**
     * Roll up events for a single day
     */
    private static function rollup_single_day($date) {
        global $wpdb;
        
        $raw_table = $wpdb->prefix . 'ct_events_raw';
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        
        // Get aggregated data for the day
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(ts) as day,
                page_hash,
                page_url,
                event_name,
                selector_key,
                device,
                is_logged_in,
                COUNT(*) as clicks
             FROM {$raw_table}
             WHERE DATE(ts) = %s
             GROUP BY page_hash, event_name, selector_key, device, is_logged_in",
            $date
        ));
        
        if (empty($results)) {
            return 0;
        }
        
        $processed = 0;
        
        foreach ($results as $row) {
            // Check if rollup entry already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT clicks FROM {$rollup_table} 
                 WHERE day = %s 
                 AND page_hash = %s 
                 AND event_name = %s 
                 AND selector_key = %s 
                 AND device = %s 
                 AND is_logged_in = %d",
                $row->day,
                $row->page_hash,
                $row->event_name,
                $row->selector_key,
                $row->device,
                $row->is_logged_in
            ));
            
            if ($existing !== null) {
                // Update existing rollup
                $wpdb->update(
                    $rollup_table,
                    array('clicks' => $existing + $row->clicks),
                    array(
                        'day' => $row->day,
                        'page_hash' => $row->page_hash,
                        'event_name' => $row->event_name,
                        'selector_key' => $row->selector_key,
                        'device' => $row->device,
                        'is_logged_in' => $row->is_logged_in
                    ),
                    array('%d'),
                    array('%s', '%s', '%s', '%s', '%s', '%d')
                );
            } else {
                // Insert new rollup
                $wpdb->insert(
                    $rollup_table,
                    array(
                        'day' => $row->day,
                        'page_hash' => $row->page_hash,
                        'page_url' => $row->page_url,
                        'event_name' => $row->event_name,
                        'selector_key' => $row->selector_key,
                        'device' => $row->device,
                        'is_logged_in' => $row->is_logged_in,
                        'clicks' => $row->clicks
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
                );
            }
            
            $processed += $row->clicks;
        }
        
        return $processed;
    }
    
    /**
     * Clean up old rollup data based on retention settings
     */
    public static function cleanup_old_rollups() {
        global $wpdb;
        
        $settings = get_option('ct_settings', array());
        $retention_months = $settings['retention_rollup_months'] ?? 12;
        
        $cutoff_date = gmdate('Y-m-d', strtotime("-{$retention_months} months"));
        
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$rollup_table} WHERE day < %s",
            $cutoff_date
        ));
        
        if ($deleted !== false) {
            error_log("ClickTally: Cleaned up {$deleted} old rollup records");
        }
        
        // Also clean up old raw events
        ClickTally_Ingest::cleanup_old_events();
        
        return $deleted;
    }
    
    /**
     * Get rollup statistics
     */
    public static function get_rollup_stats() {
        global $wpdb;
        
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        
        $stats = array(
            'total_rollup_records' => $wpdb->get_var("SELECT COUNT(*) FROM {$rollup_table}"),
            'date_range' => $wpdb->get_row(
                "SELECT MIN(day) as earliest, MAX(day) as latest FROM {$rollup_table}"
            ),
            'total_clicks' => $wpdb->get_var("SELECT SUM(clicks) FROM {$rollup_table}"),
            'unique_pages' => $wpdb->get_var("SELECT COUNT(DISTINCT page_hash) FROM {$rollup_table}"),
            'unique_events' => $wpdb->get_var("SELECT COUNT(DISTINCT event_name) FROM {$rollup_table}")
        );
        
        return $stats;
    }
    
    /**
     * Force rollup for specific date range (admin utility)
     */
    public static function force_rollup($start_date, $end_date = null) {
        if ($end_date === null) {
            $end_date = $start_date;
        }
        
        $current_date = $start_date;
        $total_processed = 0;
        
        while ($current_date <= $end_date) {
            $processed = self::rollup_single_day($current_date);
            $total_processed += $processed;
            
            $current_date = gmdate('Y-m-d', strtotime($current_date . ' +1 day'));
        }
        
        return $total_processed;
    }
    
    /**
     * Get top events for a date range
     */
    public static function get_top_events($start_date, $end_date, $limit = 10) {
        global $wpdb;
        
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                event_name,
                SUM(clicks) as total_clicks,
                COUNT(DISTINCT page_hash) as page_count
             FROM {$rollup_table}
             WHERE day BETWEEN %s AND %s
             GROUP BY event_name
             ORDER BY total_clicks DESC
             LIMIT %d",
            $start_date,
            $end_date,
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Get top pages for a date range
     */
    public static function get_top_pages($start_date, $end_date, $limit = 10) {
        global $wpdb;
        
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                page_url,
                page_hash,
                SUM(clicks) as total_clicks,
                COUNT(DISTINCT event_name) as event_count
             FROM {$rollup_table}
             WHERE day BETWEEN %s AND %s
             GROUP BY page_hash, page_url
             ORDER BY total_clicks DESC
             LIMIT %d",
            $start_date,
            $end_date,
            $limit
        ));
        
        return $results;
    }
    
    /**
     * Get clicks over time for charting
     */
    public static function get_clicks_over_time($start_date, $end_date, $group_by = 'day') {
        global $wpdb;
        
        $rollup_table = $wpdb->prefix . 'ct_rollup_daily';
        
        $date_format = $group_by === 'week' ? '%Y-%u' : '%Y-%m-%d';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(day, %s) as period,
                SUM(clicks) as total_clicks
             FROM {$rollup_table}
             WHERE day BETWEEN %s AND %s
             GROUP BY period
             ORDER BY period",
            $date_format,
            $start_date,
            $end_date
        ));
        
        return $results;
    }
}