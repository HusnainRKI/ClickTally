<?php
/**
 * ClickTally Element Event Tracker Capabilities Class
 * Handles user permissions and capabilities with proper long prefixes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Clicktally_Element_Event_Tracker_Capabilities {
    
    /**
     * Initialize capabilities
     */
    public static function clicktally_element_event_tracker_init() {
        add_action('init', array(__CLASS__, 'clicktally_element_event_tracker_register_post_type'));
    }
    
    /**
     * Add the new capability to administrators on activation
     */
    public static function clicktally_element_event_tracker_create_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('manage_clicktally_element_event_tracker');
        }
        
        // Keep backward compatibility - also add the old capability
        if ($admin_role) {
            $admin_role->add_cap('manage_clicktally');
        }
    }
    
    /**
     * Remove capabilities on deactivation
     */
    public static function clicktally_element_event_tracker_remove_capabilities() {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->remove_cap('manage_clicktally_element_event_tracker');
            // Note: Keep old capability for backward compatibility in case other code depends on it
        }
    }
    
    /**
     * Register custom post type for rules with new capability
     */
    public static function clicktally_element_event_tracker_register_post_type() {
        register_post_type('ct_rule', array(
            'labels' => array(
                'name' => __('Tracking Rules', 'clicktally'),
                'singular_name' => __('Tracking Rule', 'clicktally'),
                'add_new' => __('Add New Rule', 'clicktally'),
                'add_new_item' => __('Add New Tracking Rule', 'clicktally'),
                'edit_item' => __('Edit Tracking Rule', 'clicktally'),
                'new_item' => __('New Tracking Rule', 'clicktally'),
                'view_item' => __('View Tracking Rule', 'clicktally'),
                'search_items' => __('Search Tracking Rules', 'clicktally'),
                'not_found' => __('No tracking rules found', 'clicktally'),
                'not_found_in_trash' => __('No tracking rules found in trash', 'clicktally'),
            ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'capability_type' => 'post',
            'capabilities' => array(
                'edit_post' => 'manage_clicktally_element_event_tracker',
                'read_post' => 'manage_clicktally_element_event_tracker',
                'delete_post' => 'manage_clicktally_element_event_tracker',
                'edit_posts' => 'manage_clicktally_element_event_tracker',
                'edit_others_posts' => 'manage_clicktally_element_event_tracker',
                'publish_posts' => 'manage_clicktally_element_event_tracker',
                'read_private_posts' => 'manage_clicktally_element_event_tracker',
                'read' => 'manage_clicktally_element_event_tracker',
                'delete_posts' => 'manage_clicktally_element_event_tracker',
                'delete_private_posts' => 'manage_clicktally_element_event_tracker',
                'delete_published_posts' => 'manage_clicktally_element_event_tracker',
                'delete_others_posts' => 'manage_clicktally_element_event_tracker',
                'edit_private_posts' => 'manage_clicktally_element_event_tracker',
                'edit_published_posts' => 'manage_clicktally_element_event_tracker',
                'create_posts' => 'manage_clicktally_element_event_tracker'
            ),
            'supports' => array('title', 'revisions'),
            'rewrite' => false,
            'query_var' => false,
        ));
    }
    
    /**
     * Check if current user can manage ClickTally Element Event Tracker
     */
    public static function clicktally_element_event_tracker_can_manage() {
        return current_user_can('manage_clicktally_element_event_tracker');
    }
    
    /**
     * Check if current user can view stats
     */
    public static function clicktally_element_event_tracker_can_view_stats() {
        return current_user_can('manage_clicktally_element_event_tracker');
    }
    
    /**
     * Get roles that can manage ClickTally Element Event Tracker
     */
    public static function clicktally_element_event_tracker_get_management_roles() {
        $roles = array();
        $wp_roles = wp_roles();
        
        foreach ($wp_roles->roles as $role_name => $role_info) {
            if (isset($role_info['capabilities']['manage_clicktally_element_event_tracker']) && 
                $role_info['capabilities']['manage_clicktally_element_event_tracker']) {
                $roles[] = $role_name;
            }
        }
        
        return $roles;
    }
    
    /**
     * Add capability to role
     */
    public static function clicktally_element_event_tracker_add_capability_to_role($role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('manage_clicktally_element_event_tracker');
        }
    }
    
    /**
     * Remove capability from role
     */
    public static function clicktally_element_event_tracker_remove_capability_from_role($role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_clicktally_element_event_tracker');
        }
    }
    
    /**
     * Check if user has backward compatibility capability
     */
    public static function clicktally_element_event_tracker_has_legacy_capability() {
        return current_user_can('manage_clicktally');
    }
    
    /**
     * Grant new capability to users who have the old one (migration helper)
     */
    public static function clicktally_element_event_tracker_migrate_capabilities() {
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
    }
}