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

// Delete plugin options
delete_option('ct_settings');
delete_option('ct_rules_version');
delete_option('ct_last_rollup_date');

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
        if ($role && $role->has_cap('manage_clicktally')) {
            $role->remove_cap('manage_clicktally');
        }
    }
}

// Clear any scheduled events
wp_clear_scheduled_hook('clicktally_rollup_events');
wp_clear_scheduled_hook('clicktally_cleanup_old_data');

// Remove transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ct_%' OR option_name LIKE '_transient_timeout_ct_%'");

// Flush rewrite rules
flush_rewrite_rules();