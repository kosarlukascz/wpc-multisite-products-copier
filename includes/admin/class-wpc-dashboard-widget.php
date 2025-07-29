<?php
/**
 * Dashboard Widget Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds a dashboard widget showing recent product copy activity
 */
class WPC_Dashboard_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_network_dashboard_setup', array($this, 'add_dashboard_widget'));
    }
    
    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'wpc_activity_dashboard',
            __('Recent Product Copy Activity', 'wpc-multisite-products-copier'),
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        // Get recent activities from site option
        $activities = get_site_option('wpc_activity_log', array());
        
        // Get last 10 activities
        $recent_activities = array_slice(array_reverse($activities), 0, 10);
        
        if (empty($recent_activities)) {
            echo '<p>' . esc_html__('No product copy activities yet.', 'wpc-multisite-products-copier') . '</p>';
            return;
        }
        ?>
        <div class="wpc-activity-widget">
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Action', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('By', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Product', 'wpc-multisite-products-copier'); ?></th>
                        <th><?php esc_html_e('Sites', 'wpc-multisite-products-copier'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activities as $activity) : ?>
                        <tr>
                            <td>
                                <?php 
                                $time_diff = human_time_diff(strtotime($activity['date_time']), current_time('timestamp'));
                                echo sprintf(esc_html__('%s ago', 'wpc-multisite-products-copier'), $time_diff);
                                ?>
                            </td>
                            <td>
                                <span class="dashicons dashicons-<?php echo $activity['action'] === 'create' ? 'plus' : 'update'; ?>"></span>
                                <?php echo esc_html(ucfirst($activity['action'])); ?>
                            </td>
                            <td>
                                <?php 
                                // Try to use cached user info first
                                if (!empty($activity['user_display_name'])) {
                                    echo '<span title="' . esc_attr($activity['user_email']) . '">';
                                    echo esc_html($activity['user_display_name']);
                                    echo '</span>';
                                } else {
                                    // Fallback to looking up user
                                    $user = get_user_by('id', $activity['user_id']);
                                    if ($user) {
                                        echo '<span title="' . esc_attr($user->user_email) . '">';
                                        echo esc_html($user->display_name);
                                        echo '</span>';
                                    } else {
                                        echo '<span style="color: #999;">' . esc_html__('Unknown', 'wpc-multisite-products-copier') . '</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Get product name from source blog
                                switch_to_blog($activity['source_blog_id']);
                                $product = wc_get_product($activity['source_product_id']);
                                $product_name = $product ? $product->get_name() : __('Unknown Product', 'wpc-multisite-products-copier');
                                restore_current_blog();
                                
                                echo '<span title="' . esc_attr($product_name) . '">' . esc_html(wp_trim_words($product_name, 5)) . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                $source_site = get_site($activity['source_blog_id']);
                                $target_site = get_site($activity['target_blog_id']);
                                
                                echo '<span title="' . esc_attr__('From', 'wpc-multisite-products-copier') . ' ' . esc_attr($source_site->blogname) . '">';
                                echo esc_html($activity['source_blog_id']);
                                echo '</span> → ';
                                echo '<span title="' . esc_attr__('To', 'wpc-multisite-products-copier') . ' ' . esc_attr($target_site->blogname) . '">';
                                echo esc_html($activity['target_blog_id']);
                                echo '</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p class="wpc-view-all">
                <a href="<?php echo esc_url(network_admin_url('admin.php?page=wpc-activity-log')); ?>" class="button">
                    <?php esc_html_e('View All Activity', 'wpc-multisite-products-copier'); ?>
                </a>
            </p>
        </div>
        
        <style>
            .wpc-activity-widget table {
                margin-top: 0;
            }
            .wpc-activity-widget .dashicons {
                width: 16px;
                height: 16px;
                font-size: 16px;
            }
            .wpc-activity-widget td {
                padding: 8px 10px;
            }
            .wpc-activity-widget .wpc-view-all {
                margin-top: 12px;
                margin-bottom: 0;
            }
        </style>
        <?php
    }
}