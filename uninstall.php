<?php
/**
 * ClickTally Uninstall Script
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall plugin
if (!current_user_can('activate_plugins')) {
    exit;
}

// Confirm that we're uninstalling the correct plugin
if (__FILE__ != WP_UNINSTALL_PLUGIN) {
    exit;
}

// Check if user opted to delete data during deactivation
$delete_data = get_option('clicktally_delete_on_uninstall', false);

if ($delete_data) {
    // User chose to delete all data
    
    // Delete plugin options
    delete_option('ct_settings');
    delete_option('ct_rules_version');
    delete_option('ct_last_rollup_date');
    delete_option('clicktally_delete_on_uninstall');
    
    // Delete custom tables
    global $wpdb;
    
    $tables = array(
        $wpdb->prefix . 'ct_events_raw',
        $wpdb->prefix . 'ct_rollup_daily'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
    
    // Delete all tracking rules (custom post type)
    $rules = get_posts(array(
        'post_type' => 'ct_rule',
        'numberposts' => -1,
        'post_status' => 'any'
    ));
    
    foreach ($rules as $rule) {
        wp_delete_post($rule->ID, true);
    }
    
    // Remove capabilities from all roles
    $roles = wp_roles();
    if ($roles) {
        foreach ($roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                if ($role->has_cap('manage_clicktally')) {
                    $role->remove_cap('manage_clicktally');
                }
                if ($role->has_cap('manage_clicktally_element_event_tracker')) {
                    $role->remove_cap('manage_clicktally_element_event_tracker');
                }
            }
        }
    }
    
    // Clear any scheduled events
    wp_clear_scheduled_hook('clicktally_rollup_events');
    wp_clear_scheduled_hook('clicktally_cleanup_old_data');
    
    // Remove transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ct_%' OR option_name LIKE '_transient_timeout_ct_%'");
    
} else {
    // User chose to keep data - only clean up the deletion preference
    delete_option('clicktally_delete_on_uninstall');
    
    // Note: We could also remove capabilities here if desired, but leaving them
    // allows for easy plugin reactivation with existing permissions
}

// Flush rewrite rules
flush_rewrite_rules();