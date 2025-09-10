<?php
/**
 * ClickTally Admin Class
 * Handles admin interface and dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ClickTally_Admin {
    
    /**
     * Initialize admin interface
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
        add_action('wp_ajax_clicktally_get_stats', array(__CLASS__, 'ajax_get_stats'));
        add_action('wp_ajax_clicktally_manage_rule', array(__CLASS__, 'ajax_manage_rule'));
        add_action('wp_ajax_clicktally_export_data', array(__CLASS__, 'ajax_export_data'));
    }
    
    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        $capability = 'manage_clicktally_element_event_tracker';
        
        // Main menu
        add_menu_page(
            __('ClickTally', 'clicktally'),
            __('ClickTally', 'clicktally'),
            $capability,
            'clicktally',
            array(__CLASS__, 'render_overview_page'),
            'dashicons-chart-bar',
            30
        );
        
        // Subpages
        add_submenu_page(
            'clicktally',
            __('Overview', 'clicktally'),
            __('Overview', 'clicktally'),
            $capability,
            'clicktally',
            array(__CLASS__, 'render_overview_page')
        );
        
        add_submenu_page(
            'clicktally',
            __('Event Tracking Rules', 'clicktally'),
            __('Event Tracking Rules', 'clicktally'),
            $capability,
            'clicktally-rules',
            array(__CLASS__, 'render_rules_page')
        );
        
        add_submenu_page(
            'clicktally',
            __('Test Mode', 'clicktally'),
            __('Test Mode', 'clicktally'),
            $capability,
            'clicktally-test',
            array(__CLASS__, 'render_test_page')
        );
        
        add_submenu_page(
            'clicktally',
            __('Settings', 'clicktally'),
            __('Settings', 'clicktally'),
            $capability,
            'clicktally-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'clicktally') === false) {
            return;
        }
        
        wp_enqueue_style(
            'clicktally-element-event-tracker-admin-legacy-style',
            CLICKTALLY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CLICKTALLY_VERSION
        );
        
        // Load appropriate JS based on the page
        if (strpos($hook, 'clicktally-rules') !== false) {
            // Rules page
            wp_enqueue_script(
                'clicktally-element-event-tracker-admin-rules-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/admin-rules.js',
                array('jquery'),
                CLICKTALLY_VERSION,
                true
            );
            wp_localize_script('clicktally-element-event-tracker-admin-rules-script', 'clickTallyAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('clicktally_element_event_tracker_admin'),
                'apiUrl' => rest_url('clicktally/v1/'),
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this event?', 'clicktally'),
                    'errorGeneral' => __('An error occurred. Please try again.', 'clicktally'),
                    'eventAdded' => __('Event added successfully.', 'clicktally'),
                    'eventUpdated' => __('Event updated successfully.', 'clicktally'),
                    'eventDeleted' => __('Event deleted successfully.', 'clicktally'),
                )
            ));
        } elseif (strpos($hook, 'clicktally-test') !== false) {
            // Test page
            wp_enqueue_script(
                'clicktally-element-event-tracker-admin-test-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/admin-test.js',
                array(),
                CLICKTALLY_VERSION,
                true
            );
        } else {
            // Overview/main page
            wp_enqueue_script(
                'clicktally-element-event-tracker-admin-legacy-script',
                CLICKTALLY_PLUGIN_URL . 'assets/js/admin-legacy.js',
                array('jquery'),
                CLICKTALLY_VERSION,
                true
            );
            wp_localize_script('clicktally-element-event-tracker-admin-legacy-script', 'clickTallyAdmin', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('clicktally_element_event_tracker_admin'),
                'apiUrl' => rest_url('clicktally/v1/'),
                'strings' => array(
                    'confirmDelete' => __('Are you sure you want to delete this rule?', 'clicktally'),
                    'errorGeneral' => __('An error occurred. Please try again.', 'clicktally'),
                )
            ));
        }
    }
    
    /**
     * Render overview page
     */
    public static function render_overview_page() {
        $stats = self::get_dashboard_stats();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="clicktally-dashboard">
                <!-- Summary Cards -->
                <div class="clicktally-summary-cards">
                    <div class="clicktally-card">
                        <h3><?php _e('Total Clicks (7 days)', 'clicktally'); ?></h3>
                        <div class="clicktally-stat-number"><?php echo number_format($stats['total_clicks_7d']); ?></div>
                    </div>
                    <div class="clicktally-card">
                        <h3><?php _e('Unique Elements', 'clicktally'); ?></h3>
                        <div class="clicktally-stat-number"><?php echo number_format($stats['unique_elements']); ?></div>
                    </div>
                    <div class="clicktally-card">
                        <h3><?php _e('Top Page', 'clicktally'); ?></h3>
                        <div class="clicktally-stat-text">
                            <?php if ($stats['top_page']): ?>
                                <a href="<?php echo esc_url($stats['top_page']['url']); ?>" target="_blank">
                                    <?php echo esc_html(wp_trim_words($stats['top_page']['url'], 5)); ?>
                                </a>
                                <small>(<?php echo number_format($stats['top_page']['clicks']); ?> clicks)</small>
                            <?php else: ?>
                                <?php _e('No data yet', 'clicktally'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="clicktally-filters">
                    <select id="ct-date-range">
                        <option value="7d"><?php _e('Last 7 days', 'clicktally'); ?></option>
                        <option value="30d"><?php _e('Last 30 days', 'clicktally'); ?></option>
                    </select>
                    <select id="ct-device">
                        <option value="all"><?php _e('All devices', 'clicktally'); ?></option>
                        <option value="desktop"><?php _e('Desktop', 'clicktally'); ?></option>
                        <option value="mobile"><?php _e('Mobile', 'clicktally'); ?></option>
                        <option value="tablet"><?php _e('Tablet', 'clicktally'); ?></option>
                    </select>
                    <select id="ct-user-type">
                        <option value="all"><?php _e('All users', 'clicktally'); ?></option>
                        <option value="guest"><?php _e('Guests', 'clicktally'); ?></option>
                        <option value="logged_in"><?php _e('Logged in', 'clicktally'); ?></option>
                    </select>
                    <button type="button" class="button" onclick="clickTallyExportData()"><?php _e('Export CSV', 'clicktally'); ?></button>
                </div>
                
                <!-- Top Elements -->
                <div class="clicktally-section">
                    <h2><?php _e('Top Clicked Elements', 'clicktally'); ?></h2>
                    <div id="ct-top-elements-table">
                        <?php self::render_top_elements_table($stats['top_elements']); ?>
                    </div>
                </div>
                
                <!-- Top Pages -->
                <div class="clicktally-section">
                    <h2><?php _e('Top Pages by Clicks', 'clicktally'); ?></h2>
                    <div id="ct-top-pages-table">
                        <?php self::render_top_pages_table($stats['top_pages']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Privacy Notice (if tracking disabled) -->
            <?php if (!self::clicktally_element_event_tracker_is_tracking_enabled()): ?>
            <div class="notice notice-info">
                <p><?php echo esc_html__('Tracking is currently disabled in settings. Enable tracking to see data.', 'clicktally'); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render rules page
     */
    public static function render_rules_page() {
        $rules = ClickTally_Rules::get_active_rules();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="clicktally-rules-page">
                <div class="clicktally-rules-header">
                    <button type="button" class="button button-primary" data-action="add-event"><?php _e('Add Event', 'clicktally'); ?></button>
                </div>
                
                <div class="clicktally-rules-list">
                    <?php if (empty($rules)): ?>
                        <p><?php _e('No events configured yet. Click "Add Event" to get started.', 'clicktally'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Event Name', 'clicktally'); ?></th>
                                    <th><?php _e('Selector', 'clicktally'); ?></th>
                                    <th><?php _e('Type', 'clicktally'); ?></th>
                                    <th><?php _e('Status', 'clicktally'); ?></th>
                                    <th><?php _e('Actions', 'clicktally'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rules as $rule): ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($rule['event_name']); ?></strong></td>
                                        <td>
                                            <code><?php echo esc_html($rule['selector_type'] . ': ' . $rule['selector_value']); ?></code>
                                            <?php if ($rule['auto_rule']): ?>
                                                <span class="dashicons dashicons-admin-tools" title="<?php _e('Auto-generated rule', 'clicktally'); ?>"></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html(ucfirst($rule['event_type'])); ?></td>
                                        <td>
                                            <span class="status-<?php echo esc_attr($rule['status']); ?>">
                                                <?php echo esc_html(ucfirst($rule['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small" data-rule-id="<?php echo esc_attr($rule['id']); ?>" data-action="edit"><?php _e('Edit', 'clicktally'); ?></button>
                                            <?php if (!$rule['auto_rule']): ?>
                                                <button type="button" class="button button-small button-link-delete" data-rule-id="<?php echo esc_attr($rule['id']); ?>" data-action="delete"><?php _e('Delete', 'clicktally'); ?></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Event Modal -->
            <div id="event-modal" class="clicktally-modal" style="display: none;">
                <div class="clicktally-modal-content">
                    <div class="clicktally-modal-header">
                        <h2><?php _e('Add/Edit Tracking Event', 'clicktally'); ?></h2>
                        <button type="button" class="clicktally-modal-close" data-action="close-modal">&times;</button>
                    </div>
                    <div class="clicktally-modal-body">
                        <form id="event-form">
                            <input type="hidden" id="event-id" name="event_id" value="">
                            
                            <div class="clicktally-form-row">
                                <label for="selector-type"><?php _e('Selector Type', 'clicktally'); ?></label>
                                <select id="selector-type" name="selector_type" required>
                                    <option value="id"><?php _e('ID', 'clicktally'); ?></option>
                                    <option value="class"><?php _e('Class', 'clicktally'); ?></option>
                                    <option value="css"><?php _e('CSS Selector', 'clicktally'); ?></option>
                                    <option value="xpath"><?php _e('XPath', 'clicktally'); ?></option>
                                    <option value="data"><?php _e('Data Attribute', 'clicktally'); ?></option>
                                </select>
                            </div>
                            
                            <div class="clicktally-form-row">
                                <label for="selector-value"><?php _e('Selector Value', 'clicktally'); ?></label>
                                <div class="clicktally-input-group">
                                    <input type="text" id="selector-value" name="selector_value" required 
                                           placeholder="<?php _e('e.g., #signup-cta or .btn-primary', 'clicktally'); ?>">
                                    <button type="button" class="button" data-action="dom-picker"><?php _e('Add from Selected', 'clicktally'); ?></button>
                                </div>
                            </div>
                            
                            <div class="clicktally-form-row">
                                <label for="event-name"><?php _e('Event Name', 'clicktally'); ?></label>
                                <input type="text" id="event-name" name="event_name" required 
                                       placeholder="<?php _e('e.g., Hero CTA Click', 'clicktally'); ?>">
                            </div>
                            
                            <details class="clicktally-advanced-options">
                                <summary><?php _e('Advanced Options', 'clicktally'); ?></summary>
                                
                                <div class="clicktally-form-row">
                                    <label for="event-type"><?php _e('Event Type', 'clicktally'); ?></label>
                                    <select id="event-type" name="event_type">
                                        <option value="click"><?php _e('Click', 'clicktally'); ?></option>
                                        <option value="submit"><?php _e('Submit', 'clicktally'); ?></option>
                                        <option value="change"><?php _e('Change', 'clicktally'); ?></option>
                                        <option value="view"><?php _e('View (Intersection)', 'clicktally'); ?></option>
                                    </select>
                                </div>
                                
                                <div class="clicktally-form-row">
                                    <label for="label-template"><?php _e('Label Template', 'clicktally'); ?></label>
                                    <input type="text" id="label-template" name="label_template" 
                                           placeholder="<?php _e('{text} → {href}', 'clicktally'); ?>">
                                    <small><?php _e('Available tokens: {text}, {href}, {id}, {class}, {data-*}', 'clicktally'); ?></small>
                                </div>
                                
                                <div class="clicktally-form-row">
                                    <label for="throttle-ms"><?php _e('Throttle (ms)', 'clicktally'); ?></label>
                                    <input type="number" id="throttle-ms" name="throttle_ms" min="0" value="0">
                                </div>
                                
                                <div class="clicktally-form-row">
                                    <label>
                                        <input type="checkbox" id="once-per-view" name="once_per_view">
                                        <?php _e('Count only once per pageview', 'clicktally'); ?>
                                    </label>
                                </div>
                            </details>
                            
                            <div class="clicktally-modal-footer">
                                <button type="button" class="button" data-action="close-modal"><?php _e('Cancel', 'clicktally'); ?></button>
                                <button type="submit" class="button button-primary"><?php _e('Save Event', 'clicktally'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render test page
     */
    public static function render_test_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="clicktally-test-page">
                <div class="clicktally-test-controls">
                    <div class="clicktally-form-row">
                        <label for="test-url"><?php _e('Test URL', 'clicktally'); ?></label>
                        <input type="url" id="test-url" placeholder="<?php echo esc_attr(home_url()); ?>" style="width: 400px;">
                        <button type="button" class="button" data-action="load-test-page"><?php _e('Load Page', 'clicktally'); ?></button>
                    </div>
                    <div class="clicktally-form-row">
                        <label>
                            <input type="checkbox" id="enable-picker"> <?php _e('Enable Element Picker', 'clicktally'); ?>
                        </label>
                    </div>
                </div>
                
                <div class="clicktally-test-iframe-container">
                    <iframe id="test-iframe" style="width: 100%; height: 600px; border: 1px solid #ccc;"></iframe>
                </div>
                
                <div class="clicktally-test-results">
                    <h3><?php _e('Test Results', 'clicktally'); ?></h3>
                    <div id="test-results-content">
                        <p><?php _e('Load a page and interact with elements to see tracking results.', 'clicktally'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        if (isset($_POST['submit'])) {
            self::save_settings();
        }
        
        $settings = get_option('ct_settings', array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('clicktally_settings', 'clicktally_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Privacy Settings', 'clicktally'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="respect_dnt" value="1" <?php checked($settings['respect_dnt'] ?? true); ?>>
                                    <?php _e('Respect Do Not Track header', 'clicktally'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="session_tracking" value="1" <?php checked($settings['session_tracking'] ?? false); ?>>
                                    <?php _e('Enable session tracking (uses localStorage)', 'clicktally'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="track_admins" value="1" <?php checked($settings['track_admins'] ?? false); ?>>
                                    <?php _e('Track admin users', 'clicktally'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Data Retention', 'clicktally'); ?></th>
                        <td>
                            <label>
                                <?php _e('Raw events (days):', 'clicktally'); ?>
                                <input type="number" name="retention_raw_days" value="<?php echo esc_attr($settings['retention_raw_days'] ?? 30); ?>" min="1" max="90">
                            </label><br>
                            <label>
                                <?php _e('Rollup data (months):', 'clicktally'); ?>
                                <input type="number" name="retention_rollup_months" value="<?php echo esc_attr($settings['retention_rollup_months'] ?? 12); ?>" min="1" max="60">
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto-Track Settings', 'clicktally'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="auto_track_outbound" value="1" <?php checked($settings['auto_track_outbound'] ?? true); ?>>
                                    <?php _e('Track outbound links', 'clicktally'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="auto_track_downloads" value="1" <?php checked($settings['auto_track_downloads'] ?? true); ?>>
                                    <?php _e('Track file downloads', 'clicktally'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="auto_track_mailto" value="1" <?php checked($settings['auto_track_mailto'] ?? true); ?>>
                                    <?php _e('Track mailto/tel links', 'clicktally'); ?>
                                </label><br>
                                <label>
                                    <input type="checkbox" name="auto_track_buttons" value="1" <?php checked($settings['auto_track_buttons'] ?? true); ?>>
                                    <?php _e('Track button clicks', 'clicktally'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private static function save_settings() {
        // Verify nonce
        $nonce = sanitize_text_field(wp_unslash($_POST['clicktally_settings_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'clicktally_settings')) {
            wp_die(__('Security check failed', 'clicktally'));
        }
        
        // Check capabilities - standardized capability
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('Insufficient permissions', 'clicktally'));
        }
        
        $settings = array(
            'respect_dnt' => !empty($_POST['respect_dnt']),
            'session_tracking' => !empty($_POST['session_tracking']),
            'track_admins' => !empty($_POST['track_admins']),
            'retention_raw_days' => absint($_POST['retention_raw_days'] ?? 30),
            'retention_rollup_months' => absint($_POST['retention_rollup_months'] ?? 12),
            'auto_track_outbound' => !empty($_POST['auto_track_outbound']),
            'auto_track_downloads' => !empty($_POST['auto_track_downloads']),
            'auto_track_mailto' => !empty($_POST['auto_track_mailto']),
            'auto_track_buttons' => !empty($_POST['auto_track_buttons'])
        );
        
        // Preserve existing salt
        $existing_settings = get_option('ct_settings', array());
        if (isset($existing_settings['ip_hash_salt'])) {
            $settings['ip_hash_salt'] = $existing_settings['ip_hash_salt'];
        } else {
            $settings['ip_hash_salt'] = wp_generate_password(32, false);
        }
        
        update_option('ct_settings', $settings);
        
        add_settings_error('clicktally_settings', 'settings_updated', __('Settings saved.', 'clicktally'), 'updated');
    }
    
    /**
     * Get dashboard statistics
     */
    private static function get_dashboard_stats($range = '7d', $device = 'all', $user_type = 'all') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ct_rollup_daily';
        $days = $range === '30d' ? 30 : 7;
        $start_date = gmdate('Y-m-d', strtotime("-{$days} days"));
        
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
        )) ?: 0;
        
        // Get unique elements
        $unique_elements = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT event_name) FROM {$table} WHERE {$where_clause}",
            $params
        )) ?: 0;
        
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
        
        // Get top elements
        $top_elements = $wpdb->get_results($wpdb->prepare(
            "SELECT event_name, SUM(clicks) as total_clicks,
                    COUNT(DISTINCT page_hash) as page_count
             FROM {$table} 
             WHERE {$where_clause}
             GROUP BY event_name 
             ORDER BY total_clicks DESC 
             LIMIT 10",
            $params
        ));
        
        // Get top pages
        $top_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT page_url, SUM(clicks) as total_clicks
             FROM {$table} 
             WHERE {$where_clause}
             GROUP BY page_hash, page_url 
             ORDER BY total_clicks DESC 
             LIMIT 10",
            $params
        ));
        
        return array(
            'total_clicks_7d' => $total_clicks,
            'unique_elements' => $unique_elements,
            'top_page' => $top_page ? array(
                'url' => $top_page->page_url,
                'clicks' => (int) $top_page->total_clicks
            ) : null,
            'top_elements' => $top_elements,
            'top_pages' => $top_pages
        );
    }
    
    /**
     * Render top elements table
     */
    private static function render_top_elements_table($elements) {
        if (empty($elements)) {
            echo '<p>' . __('No data available.', 'clicktally') . '</p>';
            return;
        }
        
        $total_clicks = array_sum(array_column($elements, 'total_clicks'));
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Event Name', 'clicktally'); ?></th>
                    <th><?php _e('Clicks', 'clicktally'); ?></th>
                    <th><?php _e('% of Total', 'clicktally'); ?></th>
                    <th><?php _e('Pages', 'clicktally'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elements as $element): ?>
                    <tr>
                        <td><strong><?php echo esc_html($element->event_name); ?></strong></td>
                        <td><?php echo number_format($element->total_clicks); ?></td>
                        <td><?php echo $total_clicks > 0 ? round(($element->total_clicks / $total_clicks) * 100, 1) : 0; ?>%</td>
                        <td><?php echo number_format($element->page_count); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render top pages table
     */
    private static function render_top_pages_table($pages) {
        if (empty($pages)) {
            echo '<p>' . __('No data available.', 'clicktally') . '</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Page', 'clicktally'); ?></th>
                    <th><?php _e('Clicks', 'clicktally'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($page->page_url); ?>" target="_blank">
                                <?php echo esc_html(wp_trim_words($page->page_url, 8)); ?>
                            </a>
                        </td>
                        <td><?php echo number_format($page->total_clicks); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX handler for getting stats
     */
    public static function ajax_get_stats() {
        // Verify nonce - standardized action
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'clicktally_element_event_tracker_admin')) {
            wp_die(__('Security check failed', 'clicktally'), 403);
        }
        
        // Check capabilities - standardized capability
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('Insufficient permissions', 'clicktally'), 403);
        }
        
        $range = sanitize_text_field(wp_unslash($_POST['range'] ?? '7d'));
        $device = sanitize_text_field(wp_unslash($_POST['device'] ?? 'all'));
        $user_type = sanitize_text_field(wp_unslash($_POST['user_type'] ?? 'all'));
        
        $stats = self::get_dashboard_stats($range, $device, $user_type);
        
        // Generate HTML for tables
        ob_start();
        self::render_top_elements_table($stats['top_elements']);
        $top_elements_html = ob_get_clean();
        
        ob_start();
        self::render_top_pages_table($stats['top_pages']);
        $top_pages_html = ob_get_clean();
        
        wp_send_json_success(array(
            'total_clicks' => $stats['total_clicks_7d'],
            'unique_elements' => $stats['unique_elements'],
            'top_page' => $stats['top_page'],
            'top_elements_html' => $top_elements_html,
            'top_pages_html' => $top_pages_html
        ));
    }
    
    /**
     * AJAX handler for managing events
     */
    public static function ajax_manage_rule() {
        // Verify nonce - standardized action
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'clicktally_element_event_tracker_admin')) {
            wp_die(__('Security check failed', 'clicktally'), 403);
        }
        
        // Check capabilities - standardized capability
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('Insufficient permissions', 'clicktally'), 403);
        }
        
        // TODO: Implement event management
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for exporting data
     */
    public static function ajax_export_data() {
        // Verify nonce - standardized action
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'clicktally_element_event_tracker_admin')) {
            wp_die(__('Security check failed', 'clicktally'), 403);
        }
        
        // Check capabilities - standardized capability
        if (!current_user_can('manage_clicktally_element_event_tracker')) {
            wp_die(__('Insufficient permissions', 'clicktally'), 403);
        }
        
        // TODO: Implement data export
        wp_send_json_success();
    }
    
    /**
     * Check if tracking is enabled
     */
    private static function clicktally_element_event_tracker_is_tracking_enabled() {
        $settings = get_option('ct_settings', array());
        return !isset($settings['disable_tracking']) || !$settings['disable_tracking'];
    }
}