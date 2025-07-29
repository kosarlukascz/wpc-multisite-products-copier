<?php
/**
 * Activity Log Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles activity logging and display
 */
class WPC_Activity_Log {
    
    /**
     * Table name for activity logs
     *
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->base_prefix . 'wpc_activity_log';
        
        // Hook into network admin menu
        add_action('network_admin_menu', array($this, 'add_network_admin_menu'));
        
        // Hook into product operations to log activities
        add_action('wpc_after_product_copy', array($this, 'log_product_creation'), 10, 3);
        add_action('wpc_after_product_update', array($this, 'log_product_update'), 10, 3);
    }
    
    /**
     * Add network admin menu
     */
    public function add_network_admin_menu() {
        add_menu_page(
            __('Product Copy Activity', 'wpc-multisite-products-copier'),
            __('Product Copy Log', 'wpc-multisite-products-copier'),
            'manage_network',
            'wpc-activity-log',
            array($this, 'render_activity_page'),
            'dashicons-clipboard',
            30
        );
    }
    
    /**
     * Render the activity log page
     */
    public function render_activity_page() {
        // Get filter parameters
        $filter_action = isset($_GET['filter_action']) ? sanitize_text_field($_GET['filter_action']) : '';
        $filter_user = isset($_GET['filter_user']) ? intval($_GET['filter_user']) : 0;
        $filter_source_blog = isset($_GET['filter_source_blog']) ? intval($_GET['filter_source_blog']) : 0;
        $filter_target_blog = isset($_GET['filter_target_blog']) ? intval($_GET['filter_target_blog']) : 0;
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        
        // Pagination
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($paged - 1) * $per_page;
        
        // Get activities
        $activities = $this->get_activities($filter_action, $filter_user, $filter_source_blog, $filter_target_blog, $filter_date_from, $filter_date_to, $per_page, $offset);
        $total_items = $this->count_activities($filter_action, $filter_user, $filter_source_blog, $filter_target_blog, $filter_date_from, $filter_date_to);
        $total_pages = ceil($total_items / $per_page);
        
        // Get users for filter dropdown
        $users = $this->get_activity_users();
        
        // Get blogs for filter dropdowns
        $blogs = get_sites();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Product Copy Activity Log', 'wpc-multisite-products-copier'); ?></h1>
            
            <!-- Filters -->
            <div class="tablenav top">
                <form method="get" action="">
                    <input type="hidden" name="page" value="wpc-activity-log">
                    
                    <div class="alignleft actions">
                        <!-- Action Filter -->
                        <select name="filter_action">
                            <option value=""><?php esc_html_e('All Actions', 'wpc-multisite-products-copier'); ?></option>
                            <option value="create" <?php selected($filter_action, 'create'); ?>><?php esc_html_e('Created', 'wpc-multisite-products-copier'); ?></option>
                            <option value="update" <?php selected($filter_action, 'update'); ?>><?php esc_html_e('Updated', 'wpc-multisite-products-copier'); ?></option>
                        </select>
                        
                        <!-- User Filter -->
                        <select name="filter_user">
                            <option value="0"><?php esc_html_e('All Users', 'wpc-multisite-products-copier'); ?></option>
                            <?php foreach ($users as $user_id => $user_name) : ?>
                                <option value="<?php echo esc_attr($user_id); ?>" <?php selected($filter_user, $user_id); ?>>
                                    <?php echo esc_html($user_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- Source Blog Filter -->
                        <select name="filter_source_blog">
                            <option value="0"><?php esc_html_e('All Source Sites', 'wpc-multisite-products-copier'); ?></option>
                            <?php foreach ($blogs as $blog) : ?>
                                <option value="<?php echo esc_attr($blog->blog_id); ?>" <?php selected($filter_source_blog, $blog->blog_id); ?>>
                                    <?php echo esc_html($blog->blogname); ?> (ID: <?php echo esc_html($blog->blog_id); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <!-- Target Blog Filter -->
                        <select name="filter_target_blog">
                            <option value="0"><?php esc_html_e('All Target Sites', 'wpc-multisite-products-copier'); ?></option>
                            <?php foreach ($blogs as $blog) : ?>
                                <option value="<?php echo esc_attr($blog->blog_id); ?>" <?php selected($filter_target_blog, $blog->blog_id); ?>>
                                    <?php echo esc_html($blog->blogname); ?> (ID: <?php echo esc_html($blog->blog_id); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alignleft actions">
                        <!-- Date Range -->
                        <input type="date" name="filter_date_from" value="<?php echo esc_attr($filter_date_from); ?>" placeholder="<?php esc_attr_e('From Date', 'wpc-multisite-products-copier'); ?>">
                        <input type="date" name="filter_date_to" value="<?php echo esc_attr($filter_date_to); ?>" placeholder="<?php esc_attr_e('To Date', 'wpc-multisite-products-copier'); ?>">
                        
                        <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'wpc-multisite-products-copier'); ?>">
                        <a href="<?php echo esc_url(network_admin_url('admin.php?page=wpc-activity-log')); ?>" class="button"><?php esc_html_e('Clear Filters', 'wpc-multisite-products-copier'); ?></a>
                    </div>
                    
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo sprintf(esc_html__('%d items', 'wpc-multisite-products-copier'), $total_items); ?></span>
                        <?php if ($total_pages > 1) : ?>
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'total' => $total_pages,
                                'current' => $paged,
                                'show_all' => false,
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                            );
                            echo paginate_links($pagination_args);
                            ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Activity Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date/Time', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Action', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Initiated By', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Source Product', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Source Site', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Target Product', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Target Site', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Status', 'wpc-multisite-products-copier'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activities)) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No activities found.', 'wpc-multisite-products-copier'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($activities as $activity) : ?>
                            <tr>
                                <td><?php echo esc_html($this->format_date($activity->date_time)); ?></td>
                                <td>
                                    <span class="dashicons dashicons-<?php echo $activity->action === 'create' ? 'plus' : 'update'; ?>"></span>
                                    <?php echo esc_html(ucfirst($activity->action)); ?>
                                </td>
                                <td>
                                    <?php 
                                    $user = get_user_by('id', $activity->user_id);
                                    if ($user) {
                                        echo '<strong>' . esc_html($user->display_name) . '</strong>';
                                        echo '<br><small>' . esc_html($user->user_email) . '</small>';
                                    } else {
                                        echo '<span style="color: #999;">' . esc_html__('Unknown User', 'wpc-multisite-products-copier') . '</span>';
                                        echo '<br><small>' . sprintf(esc_html__('User ID: %d', 'wpc-multisite-products-copier'), $activity->user_id) . '</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    switch_to_blog($activity->source_blog_id);
                                    $source_product = wc_get_product($activity->source_product_id);
                                    if ($source_product) {
                                        $edit_link = get_edit_post_link($activity->source_product_id);
                                        echo '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($source_product->get_name()) . '</a>';
                                        echo ' <small>(ID: ' . esc_html($activity->source_product_id) . ')</small>';
                                    } else {
                                        echo esc_html__('Product not found', 'wpc-multisite-products-copier');
                                    }
                                    restore_current_blog();
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $source_site = get_site($activity->source_blog_id);
                                    echo $source_site ? esc_html($source_site->blogname) : esc_html__('Unknown', 'wpc-multisite-products-copier');
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    switch_to_blog($activity->target_blog_id);
                                    $target_product = wc_get_product($activity->target_product_id);
                                    if ($target_product) {
                                        $edit_link = get_edit_post_link($activity->target_product_id);
                                        echo '<a href="' . esc_url($edit_link) . '" target="_blank">' . esc_html($target_product->get_name()) . '</a>';
                                        echo ' <small>(ID: ' . esc_html($activity->target_product_id) . ')</small>';
                                    } else {
                                        echo esc_html__('Product not found', 'wpc-multisite-products-copier');
                                    }
                                    restore_current_blog();
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $target_site = get_site($activity->target_blog_id);
                                    echo $target_site ? esc_html($target_site->blogname) : esc_html__('Unknown', 'wpc-multisite-products-copier');
                                    ?>
                                </td>
                                <td>
                                    <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                    <?php esc_html_e('Success', 'wpc-multisite-products-copier'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Bottom Pagination -->
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php if ($total_pages > 1) : ?>
                        <?php echo paginate_links($pagination_args); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
            .wp-list-table .dashicons {
                width: 20px;
                height: 20px;
                font-size: 20px;
                vertical-align: middle;
            }
            .wp-list-table td {
                vertical-align: middle;
            }
            .tablenav .actions {
                overflow: visible;
            }
            .tablenav .actions select,
            .tablenav .actions input[type="date"] {
                margin-right: 6px;
            }
            .wp-list-table td small {
                color: #666;
                font-style: italic;
            }
            .wp-list-table td strong {
                color: #23282d;
                font-weight: 600;
            }
        </style>
        <?php
    }
    
    /**
     * Get activities from database
     */
    private function get_activities($action = '', $user_id = 0, $source_blog = 0, $target_blog = 0, $date_from = '', $date_to = '', $limit = 50, $offset = 0) {
        // Get activities from site option
        $all_activities = get_site_option('wpc_activity_log', array());
        
        // Convert array data to objects and reverse to show newest first
        $all_activities = array_reverse($all_activities);
        $activities = array();
        
        foreach ($all_activities as $index => $activity) {
            // Convert array to object
            $activity_obj = (object) array(
                'id' => $index + 1,
                'action' => $activity['action'],
                'user_id' => $activity['user_id'],
                'user_login' => isset($activity['user_login']) ? $activity['user_login'] : '',
                'user_email' => isset($activity['user_email']) ? $activity['user_email'] : '',
                'user_display_name' => isset($activity['user_display_name']) ? $activity['user_display_name'] : '',
                'source_product_id' => $activity['source_product_id'],
                'source_blog_id' => $activity['source_blog_id'],
                'target_product_id' => $activity['target_product_id'],
                'target_blog_id' => $activity['target_blog_id'],
                'date_time' => $activity['date_time'],
                'status' => $activity['status'],
                'ip_address' => isset($activity['ip_address']) ? $activity['ip_address'] : '',
                'user_agent' => isset($activity['user_agent']) ? $activity['user_agent'] : ''
            );
            
            // Apply filters
            if ($action && $activity_obj->action !== $action) continue;
            if ($user_id && $activity_obj->user_id != $user_id) continue;
            if ($source_blog && $activity_obj->source_blog_id != $source_blog) continue;
            if ($target_blog && $activity_obj->target_blog_id != $target_blog) continue;
            
            // Date range filtering
            if ($date_from && strtotime($activity_obj->date_time) < strtotime($date_from . ' 00:00:00')) continue;
            if ($date_to && strtotime($activity_obj->date_time) > strtotime($date_to . ' 23:59:59')) continue;
            
            $activities[] = $activity_obj;
        }
        
        // Apply pagination
        $activities = array_slice($activities, $offset, $limit);
        
        return $activities;
    }
    
    /**
     * Count total activities
     */
    private function count_activities($action = '', $user_id = 0, $source_blog = 0, $target_blog = 0, $date_from = '', $date_to = '') {
        // Get activities from site option
        $all_activities = get_site_option('wpc_activity_log', array());
        $count = 0;
        
        foreach ($all_activities as $activity) {
            // Apply filters
            if ($action && $activity['action'] !== $action) continue;
            if ($user_id && $activity['user_id'] != $user_id) continue;
            if ($source_blog && $activity['source_blog_id'] != $source_blog) continue;
            if ($target_blog && $activity['target_blog_id'] != $target_blog) continue;
            
            // Date range filtering
            if ($date_from && strtotime($activity['date_time']) < strtotime($date_from . ' 00:00:00')) continue;
            if ($date_to && strtotime($activity['date_time']) > strtotime($date_to . ' 23:59:59')) continue;
            
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Get unique users who have performed activities
     */
    private function get_activity_users() {
        $users = array();
        $all_activities = get_site_option('wpc_activity_log', array());
        
        // Collect unique user IDs
        $user_ids = array();
        foreach ($all_activities as $activity) {
            if (!empty($activity['user_id'])) {
                $user_ids[$activity['user_id']] = true;
            }
        }
        
        // Get user display names
        foreach (array_keys($user_ids) as $user_id) {
            $user = get_user_by('id', $user_id);
            if ($user) {
                $users[$user_id] = $user->display_name;
            }
        }
        
        return $users;
    }
    
    /**
     * Format date for display
     */
    private function format_date($date) {
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date));
    }
    
    /**
     * Log product creation
     */
    public function log_product_creation($source_product_id, $new_product_id, $target_blog_id) {
        $this->log_activity('create', $source_product_id, $new_product_id, get_current_blog_id(), $target_blog_id);
    }
    
    /**
     * Log product update
     */
    public function log_product_update($source_product_id, $target_product_id, $target_blog_id) {
        $this->log_activity('update', $source_product_id, $target_product_id, get_current_blog_id(), $target_blog_id);
    }
    
    /**
     * Log activity to database
     */
    private function log_activity($action, $source_product_id, $target_product_id, $source_blog_id, $target_blog_id) {
        global $wpdb;
        
        // Get current user info
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        
        // If no user ID (shouldn't happen in admin), try to get from context
        if (!$user_id) {
            // This shouldn't happen in normal operation, but just in case
            error_log('WPC Activity Log: No user ID found for action ' . $action);
            return;
        }
        
        // In real implementation, insert into database
        // For now, we'll just store in options as a demo
        $activities = get_site_option('wpc_activity_log', array());
        
        $activities[] = array(
            'action' => $action,
            'user_id' => $user_id,
            'user_login' => $current_user->user_login,
            'user_email' => $current_user->user_email,
            'user_display_name' => $current_user->display_name,
            'source_product_id' => $source_product_id,
            'source_blog_id' => $source_blog_id,
            'target_product_id' => $target_product_id,
            'target_blog_id' => $target_blog_id,
            'date_time' => current_time('mysql'),
            'status' => 'success',
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        );
        
        // Keep only last 1000 activities
        if (count($activities) > 1000) {
            $activities = array_slice($activities, -1000);
        }
        
        update_site_option('wpc_activity_log', $activities);
    }
}