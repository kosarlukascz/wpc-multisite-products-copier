<?php
/**
 * Bulk Operations Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles bulk product operations
 */
class WPC_Bulk_Operations {
    
    /**
     * Instance of main plugin class
     *
     * @var WPC_Multisite_Products_Copier
     */
    private $copier;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->copier = WPC_Multisite_Products_Copier::get_instance();
        
        // Add bulk actions to products list
        add_filter('bulk_actions-edit-product', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Add custom column for sync status
        add_filter('manage_product_posts_columns', array($this, 'add_sync_column'));
        add_action('manage_product_posts_custom_column', array($this, 'render_sync_column'), 10, 2);
        
        // AJAX handlers for bulk operations
        add_action('wp_ajax_wpc_bulk_copy_products', array($this, 'ajax_bulk_copy_products'));
        add_action('wp_ajax_wpc_bulk_update_products', array($this, 'ajax_bulk_update_products'));
        add_action('wp_ajax_wpc_get_bulk_operation_status', array($this, 'ajax_get_operation_status'));
        
        // Admin scripts for bulk operations
        add_action('admin_enqueue_scripts', array($this, 'enqueue_bulk_scripts'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'show_bulk_operation_notices'));
    }
    
    /**
     * Add bulk actions to dropdown
     */
    public function add_bulk_actions($actions) {
        // Only show on source blog
        if (get_current_blog_id() !== 5) {
            return $actions;
        }
        
        $actions['wpc_bulk_copy'] = __('Copy to Sites', 'wpc-multisite-products-copier');
        $actions['wpc_bulk_update'] = __('Update on Sites', 'wpc-multisite-products-copier');
        
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action !== 'wpc_bulk_copy' && $action !== 'wpc_bulk_update') {
            return $redirect_to;
        }
        
        // Store operation details in transient
        $operation_id = 'wpc_bulk_' . uniqid();
        set_transient($operation_id, array(
            'action' => $action,
            'product_ids' => $post_ids,
            'status' => 'pending',
            'processed' => 0,
            'total' => count($post_ids),
            'errors' => array()
        ), HOUR_IN_SECONDS);
        
        // Redirect with operation ID
        $redirect_to = add_query_arg(array(
            'wpc_bulk_operation' => $operation_id,
            'wpc_bulk_action' => $action
        ), $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Add sync status column
     */
    public function add_sync_column($columns) {
        // Only show on source blog
        if (get_current_blog_id() !== 5) {
            return $columns;
        }
        
        // Add after title column
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['wpc_sync_status'] = __('Network Sync', 'wpc-multisite-products-copier');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render sync status column
     */
    public function render_sync_column($column, $post_id) {
        if ($column !== 'wpc_sync_status') {
            return;
        }
        
        // Get synced products
        $synced_products = get_post_meta($post_id, '_wpc_synced_product_ids', true);
        if (!is_array($synced_products) || empty($synced_products)) {
            echo '<span class="wpc-sync-status wpc-not-synced" title="' . esc_attr__('Not synced to any site', 'wpc-multisite-products-copier') . '">—</span>';
            return;
        }
        
        $count = count($synced_products);
        $sites_list = array();
        
        foreach ($synced_products as $blog_id => $product_id) {
            $site = get_site($blog_id);
            if ($site) {
                $sites_list[] = $site->blogname;
            }
        }
        
        $tooltip = sprintf(
            _n('Synced to: %s', 'Synced to: %s', $count, 'wpc-multisite-products-copier'),
            implode(', ', $sites_list)
        );
        
        echo '<span class="wpc-sync-status wpc-synced" title="' . esc_attr($tooltip) . '">';
        echo '<span class="dashicons dashicons-admin-multisite"></span> ';
        echo sprintf(_n('%d site', '%d sites', $count, 'wpc-multisite-products-copier'), $count);
        echo '</span>';
    }
    
    /**
     * Enqueue bulk operation scripts
     */
    public function enqueue_bulk_scripts($hook) {
        if ($hook !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
            return;
        }
        
        // Only on source blog
        if (get_current_blog_id() !== 5) {
            return;
        }
        
        wp_enqueue_script(
            'wpc-bulk-operations',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/bulk-operations.js',
            array('jquery', 'wp-util'),
            '1.0.0',
            true
        );
        
        wp_localize_script('wpc-bulk-operations', 'wpc_bulk', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpc_bulk_operations'),
            'sites' => $this->get_available_sites(),
            'strings' => array(
                'select_sites' => __('Select Target Sites', 'wpc-multisite-products-copier'),
                'copying' => __('Copying products...', 'wpc-multisite-products-copier'),
                'updating' => __('Updating products...', 'wpc-multisite-products-copier'),
                'complete' => __('Operation complete!', 'wpc-multisite-products-copier'),
                'error' => __('An error occurred', 'wpc-multisite-products-copier'),
                'cancel' => __('Cancel', 'wpc-multisite-products-copier'),
                'close' => __('Close', 'wpc-multisite-products-copier'),
                'processing' => __('Processing %1$d of %2$d products...', 'wpc-multisite-products-copier'),
                'confirm_copy' => __('Copy %d products to selected sites?', 'wpc-multisite-products-copier'),
                'confirm_update' => __('Update %d products on their synced sites?', 'wpc-multisite-products-copier')
            )
        ));
        
        wp_enqueue_style(
            'wpc-bulk-operations',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/bulk-operations.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * Get available sites for bulk operations
     */
    private function get_available_sites() {
        $sites = get_sites();
        $current_blog_id = get_current_blog_id();
        $available_sites = array();
        
        foreach ($sites as $site) {
            if ($site->blog_id != $current_blog_id) {
                $available_sites[] = array(
                    'id' => $site->blog_id,
                    'name' => $site->blogname,
                    'url' => $site->siteurl
                );
            }
        }
        
        return $available_sites;
    }
    
    /**
     * Show admin notices for bulk operations
     */
    public function show_bulk_operation_notices() {
        if (!isset($_GET['wpc_bulk_operation'])) {
            return;
        }
        
        $operation_id = sanitize_text_field($_GET['wpc_bulk_operation']);
        $operation_data = get_transient($operation_id);
        
        if (!$operation_data) {
            return;
        }
        
        ?>
        <div class="notice notice-info is-dismissible" id="wpc-bulk-operation-notice" data-operation-id="<?php echo esc_attr($operation_id); ?>">
            <p>
                <strong><?php esc_html_e('Bulk Operation in Progress', 'wpc-multisite-products-copier'); ?></strong>
            </p>
            <div id="wpc-bulk-progress">
                <div class="wpc-progress-bar">
                    <div class="wpc-progress-fill" style="width: 0%;"></div>
                </div>
                <p class="wpc-progress-text">
                    <?php esc_html_e('Initializing...', 'wpc-multisite-products-copier'); ?>
                </p>
            </div>
        </div>
        
        <!-- Site Selection Modal -->
        <div id="wpc-site-selection-modal" class="wpc-modal" style="display: none;">
            <div class="wpc-modal-content">
                <h2><?php esc_html_e('Select Target Sites', 'wpc-multisite-products-copier'); ?></h2>
                <div class="wpc-site-list">
                    <?php foreach ($this->get_available_sites() as $site) : ?>
                        <label class="wpc-site-option">
                            <input type="checkbox" name="target_sites[]" value="<?php echo esc_attr($site['id']); ?>">
                            <?php echo esc_html($site['name']); ?> 
                            <small>(ID: <?php echo esc_html($site['id']); ?>)</small>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="wpc-modal-actions">
                    <button type="button" class="button button-primary" id="wpc-start-bulk-operation">
                        <?php esc_html_e('Start Operation', 'wpc-multisite-products-copier'); ?>
                    </button>
                    <button type="button" class="button" id="wpc-cancel-operation">
                        <?php esc_html_e('Cancel', 'wpc-multisite-products-copier'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for bulk copy products
     */
    public function ajax_bulk_copy_products() {
        check_ajax_referer('wpc_bulk_operations', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_die('Unauthorized');
        }
        
        $operation_id = isset($_POST['operation_id']) ? sanitize_text_field($_POST['operation_id']) : '';
        $target_sites = isset($_POST['target_sites']) ? array_map('intval', $_POST['target_sites']) : array();
        $batch_size = 5; // Process 5 products at a time
        
        if (empty($operation_id) || empty($target_sites)) {
            wp_send_json_error('Invalid parameters');
        }
        
        $operation_data = get_transient($operation_id);
        if (!$operation_data) {
            wp_send_json_error('Operation not found');
        }
        
        // Get products to process in this batch
        $start = $operation_data['processed'];
        $product_ids = array_slice($operation_data['product_ids'], $start, $batch_size);
        
        $results = array();
        $errors = array();
        
        foreach ($product_ids as $product_id) {
            foreach ($target_sites as $target_site) {
                try {
                    // Check if product is variable
                    $product = wc_get_product($product_id);
                    if (!$product || !$product->is_type('variable')) {
                        $errors[] = sprintf(
                            __('Product %d is not a variable product', 'wpc-multisite-products-copier'),
                            $product_id
                        );
                        continue;
                    }
                    
                    // Use the main plugin's copy method
                    $new_product_id = $this->copier->create_product_on_blog($product_id, $target_site);
                    
                    if ($new_product_id) {
                        $results[] = array(
                            'source_product_id' => $product_id,
                            'target_product_id' => $new_product_id,
                            'target_blog_id' => $target_site
                        );
                        
                        // Log activity
                        do_action('wpc_after_product_copy', $product_id, $new_product_id, $target_site);
                    } else {
                        $errors[] = sprintf(
                            __('Failed to copy product %d to site %d', 'wpc-multisite-products-copier'),
                            $product_id,
                            $target_site
                        );
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
            
            $operation_data['processed']++;
        }
        
        // Update operation data
        $operation_data['errors'] = array_merge($operation_data['errors'], $errors);
        $operation_data['results'] = isset($operation_data['results']) ? 
            array_merge($operation_data['results'], $results) : $results;
        
        if ($operation_data['processed'] >= $operation_data['total']) {
            $operation_data['status'] = 'complete';
        }
        
        set_transient($operation_id, $operation_data, HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'processed' => $operation_data['processed'],
            'total' => $operation_data['total'],
            'complete' => $operation_data['status'] === 'complete',
            'errors' => $errors,
            'results' => $results
        ));
    }
    
    /**
     * AJAX handler for bulk update products
     */
    public function ajax_bulk_update_products() {
        check_ajax_referer('wpc_bulk_operations', 'nonce');
        
        if (!current_user_can('edit_products')) {
            wp_die('Unauthorized');
        }
        
        $operation_id = isset($_POST['operation_id']) ? sanitize_text_field($_POST['operation_id']) : '';
        $batch_size = 5; // Process 5 products at a time
        
        if (empty($operation_id)) {
            wp_send_json_error('Invalid parameters');
        }
        
        $operation_data = get_transient($operation_id);
        if (!$operation_data) {
            wp_send_json_error('Operation not found');
        }
        
        // Get products to process in this batch
        $start = $operation_data['processed'];
        $product_ids = array_slice($operation_data['product_ids'], $start, $batch_size);
        
        $results = array();
        $errors = array();
        
        foreach ($product_ids as $product_id) {
            // Get synced products
            $synced_products = get_post_meta($product_id, '_wpc_synced_product_ids', true);
            
            if (!is_array($synced_products) || empty($synced_products)) {
                continue; // Skip products that aren't synced
            }
            
            foreach ($synced_products as $target_blog_id => $target_product_id) {
                try {
                    // Use the main plugin's update method
                    $updated = $this->copier->update_product_on_blog($product_id, $target_blog_id, $target_product_id);
                    
                    if ($updated) {
                        $results[] = array(
                            'source_product_id' => $product_id,
                            'target_product_id' => $target_product_id,
                            'target_blog_id' => $target_blog_id
                        );
                        
                        // Log activity
                        do_action('wpc_after_product_update', $product_id, $target_product_id, $target_blog_id);
                    } else {
                        $errors[] = sprintf(
                            __('Failed to update product %d on site %d', 'wpc-multisite-products-copier'),
                            $product_id,
                            $target_blog_id
                        );
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
            
            $operation_data['processed']++;
        }
        
        // Update operation data
        $operation_data['errors'] = array_merge($operation_data['errors'], $errors);
        $operation_data['results'] = isset($operation_data['results']) ? 
            array_merge($operation_data['results'], $results) : $results;
        
        if ($operation_data['processed'] >= $operation_data['total']) {
            $operation_data['status'] = 'complete';
        }
        
        set_transient($operation_id, $operation_data, HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'processed' => $operation_data['processed'],
            'total' => $operation_data['total'],
            'complete' => $operation_data['status'] === 'complete',
            'errors' => $errors,
            'results' => $results
        ));
    }
    
    /**
     * AJAX handler to get operation status
     */
    public function ajax_get_operation_status() {
        check_ajax_referer('wpc_bulk_operations', 'nonce');
        
        $operation_id = isset($_POST['operation_id']) ? sanitize_text_field($_POST['operation_id']) : '';
        
        if (empty($operation_id)) {
            wp_send_json_error('Invalid operation ID');
        }
        
        $operation_data = get_transient($operation_id);
        if (!$operation_data) {
            wp_send_json_error('Operation not found');
        }
        
        wp_send_json_success($operation_data);
    }
}