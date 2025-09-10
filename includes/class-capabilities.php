<?php
/**
 * ClickTally Capabilities Class
 * Handles user permissions and capabilities
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_Capabilities {
    
    /**
     * Initialize capabilities
     */
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_type'));
    }
    
    /**
     * Register custom post type for rules
     */
    public static function register_post_type() {
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
                'edit_post' => 'manage_clicktally',
                'read_post' => 'manage_clicktally',
                'delete_post' => 'manage_clicktally',
                'edit_posts' => 'manage_clicktally',
                'edit_others_posts' => 'manage_clicktally',
                'publish_posts' => 'manage_clicktally',
                'read_private_posts' => 'manage_clicktally',
                'read' => 'manage_clicktally',
                'delete_posts' => 'manage_clicktally',
                'delete_private_posts' => 'manage_clicktally',
                'delete_published_posts' => 'manage_clicktally',
                'delete_others_posts' => 'manage_clicktally',
                'edit_private_posts' => 'manage_clicktally',
                'edit_published_posts' => 'manage_clicktally',
                'create_posts' => 'manage_clicktally'
            ),
            'supports' => array('title', 'revisions'),
            'rewrite' => false,
            'query_var' => false,
        ));
    }
    
    /**
     * Check if current user can manage ClickTally
     */
    public static function can_manage() {
        return current_user_can('manage_clicktally');
    }
    
    /**
     * Check if current user can view stats
     */
    public static function can_view_stats() {
        return current_user_can('manage_clicktally');
    }
    
    /**
     * Get roles that can manage ClickTally
     */
    public static function get_management_roles() {
        $roles = array();
        $wp_roles = wp_roles();
        
        foreach ($wp_roles->roles as $role_name => $role_info) {
            if (isset($role_info['capabilities']['manage_clicktally']) && $role_info['capabilities']['manage_clicktally']) {
                $roles[] = $role_name;
            }
        }
        
        return $roles;
    }
    
    /**
     * Add capability to role
     */
    public static function add_capability_to_role($role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('manage_clicktally');
        }
    }
    
    /**
     * Remove capability from role
     */
    public static function remove_capability_from_role($role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('manage_clicktally');
        }
    }
}