<?php
/**
 * ClickTally Rules Class
 * Handles tracking rule management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_Rules {
    
    /**
     * Initialize rules management
     */
    public static function init() {
        add_action('save_post_ct_rule', array(__CLASS__, 'on_rule_save'), 10, 2);
        add_action('delete_post', array(__CLASS__, 'on_rule_delete'));
    }
    
    /**
     * Get all active rules
     */
    public static function get_active_rules() {
        $rules = get_posts(array(
            'post_type' => 'ct_rule',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'status',
                    'value' => 'active',
                    'compare' => '='
                )
            )
        ));
        
        $formatted_rules = array();
        foreach ($rules as $rule) {
            $formatted_rules[] = self::format_rule($rule);
        }
        
        return $formatted_rules;
    }
    
    /**
     * Get rules for client consumption (minimal data)
     */
    public static function get_client_rules() {
        $rules = self::get_active_rules();
        $client_rules = array();
        
        foreach ($rules as $rule) {
            // Only include necessary data for client-side tracking
            $client_rules[] = array(
                'selector_type' => $rule['selector_type'],
                'selector_value' => $rule['selector_value'],
                'event_name' => $rule['event_name'],
                'event_type' => $rule['event_type'],
                'selector_key' => $rule['selector_key'],
                'scope_type' => $rule['scope_type'],
                'scope_value' => $rule['scope_value'],
                'label_template' => $rule['label_template'],
                'throttle_ms' => $rule['throttle_ms'],
                'once_per_view' => $rule['once_per_view'],
                'roles' => $rule['roles']
            );
        }
        
        return $client_rules;
    }
    
    /**
     * Create a new rule
     */
    public static function create_rule($data) {
        $rule_data = array(
            'post_type' => 'ct_rule',
            'post_title' => sanitize_text_field($data['event_name']),
            'post_status' => 'publish',
            'meta_input' => array(
                'selector_type' => sanitize_text_field($data['selector_type']),
                'selector_value' => sanitize_text_field($data['selector_value']),
                'event_name' => sanitize_text_field($data['event_name']),
                'event_type' => sanitize_text_field($data['event_type'] ?? 'click'),
                'scope_type' => sanitize_text_field($data['scope_type'] ?? 'global'),
                'scope_value' => sanitize_text_field($data['scope_value'] ?? ''),
                'label_template' => sanitize_text_field($data['label_template'] ?? '{text}'),
                'throttle_ms' => absint($data['throttle_ms'] ?? 0),
                'once_per_view' => (bool) ($data['once_per_view'] ?? false),
                'roles' => is_array($data['roles'] ?? array()) ? $data['roles'] : array(),
                'status' => 'active',
                'auto_rule' => false
            )
        );
        
        $post_id = wp_insert_post($rule_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Generate selector key
            $selector_key = self::generate_selector_key($data['selector_type'], $data['selector_value']);
            update_post_meta($post_id, 'selector_key', $selector_key);
            
            // Increment rules version
            self::increment_rules_version();
            
            return $post_id;
        }
        
        return false;
    }
    
    /**
     * Update a rule
     */
    public static function update_rule($rule_id, $data) {
        $rule_data = array(
            'ID' => $rule_id,
            'post_title' => sanitize_text_field($data['event_name']),
            'meta_input' => array(
                'selector_type' => sanitize_text_field($data['selector_type']),
                'selector_value' => sanitize_text_field($data['selector_value']),
                'event_name' => sanitize_text_field($data['event_name']),
                'event_type' => sanitize_text_field($data['event_type'] ?? 'click'),
                'scope_type' => sanitize_text_field($data['scope_type'] ?? 'global'),
                'scope_value' => sanitize_text_field($data['scope_value'] ?? ''),
                'label_template' => sanitize_text_field($data['label_template'] ?? '{text}'),
                'throttle_ms' => absint($data['throttle_ms'] ?? 0),
                'once_per_view' => (bool) ($data['once_per_view'] ?? false),
                'roles' => is_array($data['roles'] ?? array()) ? $data['roles'] : array(),
                'status' => sanitize_text_field($data['status'] ?? 'active')
            )
        );
        
        $result = wp_update_post($rule_data);
        
        if ($result && !is_wp_error($result)) {
            // Update selector key
            $selector_key = self::generate_selector_key($data['selector_type'], $data['selector_value']);
            update_post_meta($rule_id, 'selector_key', $selector_key);
            
            // Increment rules version
            self::increment_rules_version();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete a rule
     */
    public static function delete_rule($rule_id) {
        $result = wp_delete_post($rule_id, true);
        
        if ($result) {
            self::increment_rules_version();
            return true;
        }
        
        return false;
    }
    
    /**
     * Format rule data
     */
    private static function format_rule($post) {
        $meta = get_post_meta($post->ID);
        
        return array(
            'id' => $post->ID,
            'selector_type' => $meta['selector_type'][0] ?? '',
            'selector_value' => $meta['selector_value'][0] ?? '',
            'event_name' => $meta['event_name'][0] ?? '',
            'event_type' => $meta['event_type'][0] ?? 'click',
            'selector_key' => $meta['selector_key'][0] ?? self::generate_selector_key($meta['selector_type'][0] ?? '', $meta['selector_value'][0] ?? ''),
            'scope_type' => $meta['scope_type'][0] ?? 'global',
            'scope_value' => $meta['scope_value'][0] ?? '',
            'label_template' => $meta['label_template'][0] ?? '{text}',
            'throttle_ms' => absint($meta['throttle_ms'][0] ?? 0),
            'once_per_view' => (bool) ($meta['once_per_view'][0] ?? false),
            'roles' => maybe_unserialize($meta['roles'][0] ?? array()),
            'status' => $meta['status'][0] ?? 'active',
            'auto_rule' => (bool) ($meta['auto_rule'][0] ?? false),
            'created' => $post->post_date,
            'modified' => $post->post_modified
        );
    }
    
    /**
     * Generate a unique selector key
     */
    public static function generate_selector_key($selector_type, $selector_value) {
        return substr(md5($selector_type . '|' . $selector_value), 0, 16);
    }
    
    /**
     * Increment rules version to bust client cache
     */
    private static function increment_rules_version() {
        $version = get_option('ct_rules_version', 1);
        update_option('ct_rules_version', $version + 1);
    }
    
    /**
     * Handle rule save
     */
    public static function on_rule_save($post_id, $post) {
        if ($post->post_type === 'ct_rule') {
            self::increment_rules_version();
        }
    }
    
    /**
     * Handle rule deletion
     */
    public static function on_rule_delete($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'ct_rule') {
            self::increment_rules_version();
        }
    }
    
    /**
     * Validate selector
     */
    public static function validate_selector($selector_type, $selector_value) {
        if (empty($selector_value)) {
            return false;
        }
        
        switch ($selector_type) {
            case 'id':
                return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $selector_value);
            case 'class':
                return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $selector_value);
            case 'css':
                // Basic CSS selector validation - could be improved
                return !empty($selector_value);
            case 'xpath':
                // Basic XPath validation - could be improved
                return !empty($selector_value);
            case 'data':
                return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $selector_value);
            default:
                return false;
        }
    }
}