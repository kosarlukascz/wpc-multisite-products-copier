<?php
/**
 * Product Mapping Dashboard Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles product mapping dashboard
 */
class WPC_Product_Mapping {
    
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
        
        // Add network admin menu
        add_action('network_admin_menu', array($this, 'add_network_menu'));
        
        // AJAX handlers
        add_action('wp_ajax_wpc_get_product_mapping', array($this, 'ajax_get_product_mapping'));
        add_action('wp_ajax_wpc_sync_product', array($this, 'ajax_sync_product'));
        add_action('wp_ajax_wpc_check_sync_status', array($this, 'ajax_check_sync_status'));
        add_action('wp_ajax_wpc_export_mapping', array($this, 'ajax_export_mapping'));
        
        // Scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add network admin menu
     */
    public function add_network_menu() {
        add_submenu_page(
            'wpc-activity-log',
            __('Product Network Map', 'wpc-multisite-products-copier'),
            __('Product Map', 'wpc-multisite-products-copier'),
            'manage_network',
            'wpc-product-mapping',
            array($this, 'render_mapping_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'product-copy-log_page_wpc-product-mapping') {
            return;
        }
        
        wp_enqueue_script(
            'wpc-product-mapping',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/product-mapping.js',
            array('jquery', 'wp-util'),
            '1.0.0',
            true
        );
        
        wp_localize_script('wpc-product-mapping', 'wpc_mapping', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpc_product_mapping'),
            'source_blog_id' => 5,
            'strings' => array(
                'loading' => __('Loading product map...', 'wpc-multisite-products-copier'),
                'sync' => __('Sync', 'wpc-multisite-products-copier'),
                'create' => __('Create', 'wpc-multisite-products-copier'),
                'update' => __('Update', 'wpc-multisite-products-copier'),
                'view' => __('View', 'wpc-multisite-products-copier'),
                'checking' => __('Checking...', 'wpc-multisite-products-copier'),
                'confirm_sync' => __('Sync this product to the selected site?', 'wpc-multisite-products-copier'),
                'sync_success' => __('Product synced successfully!', 'wpc-multisite-products-copier'),
                'sync_error' => __('Error syncing product', 'wpc-multisite-products-copier'),
                'export_success' => __('Export completed!', 'wpc-multisite-products-copier'),
                'no_products' => __('No products found', 'wpc-multisite-products-copier'),
                'search_placeholder' => __('Search products...', 'wpc-multisite-products-copier'),
                'filter_all' => __('All Products', 'wpc-multisite-products-copier'),
                'filter_synced' => __('Synced', 'wpc-multisite-products-copier'),
                'filter_partial' => __('Partially Synced', 'wpc-multisite-products-copier'),
                'filter_not_synced' => __('Not Synced', 'wpc-multisite-products-copier'),
                'filter_outdated' => __('Outdated', 'wpc-multisite-products-copier')
            )
        ));
        
        wp_enqueue_style(
            'wpc-product-mapping',
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/product-mapping.css',
            array(),
            '1.0.0'
        );
    }
    
    /**
     * Render the mapping page
     */
    public function render_mapping_page() {
        // Get all sites
        $sites = get_sites();
        $sites_data = array();
        
        foreach ($sites as $site) {
            $sites_data[] = array(
                'id' => $site->blog_id,
                'name' => $site->blogname,
                'url' => $site->siteurl
            );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Product Network Map', 'wpc-multisite-products-copier'); ?></h1>
            
            <div class="wpc-mapping-header">
                <div class="wpc-mapping-filters">
                    <input type="text" id="wpc-product-search" placeholder="<?php esc_attr_e('Search products...', 'wpc-multisite-products-copier'); ?>">
                    
                    <select id="wpc-sync-filter">
                        <option value=""><?php esc_html_e('All Products', 'wpc-multisite-products-copier'); ?></option>
                        <option value="synced"><?php esc_html_e('Fully Synced', 'wpc-multisite-products-copier'); ?></option>
                        <option value="partial"><?php esc_html_e('Partially Synced', 'wpc-multisite-products-copier'); ?></option>
                        <option value="not_synced"><?php esc_html_e('Not Synced', 'wpc-multisite-products-copier'); ?></option>
                        <option value="outdated"><?php esc_html_e('Has Outdated Copies', 'wpc-multisite-products-copier'); ?></option>
                    </select>
                    
                    <select id="wpc-category-filter">
                        <option value=""><?php esc_html_e('All Categories', 'wpc-multisite-products-copier'); ?></option>
                        <?php
                        // Switch to source blog to get categories
                        switch_to_blog(5);
                        $categories = get_terms(array(
                            'taxonomy' => 'product_cat',
                            'hide_empty' => true
                        ));
                        foreach ($categories as $category) {
                            echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
                        }
                        restore_current_blog();
                        ?>
                    </select>
                    
                    <button type="button" class="button" id="wpc-refresh-map">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Refresh', 'wpc-multisite-products-copier'); ?>
                    </button>
                    
                    <button type="button" class="button" id="wpc-export-map">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export CSV', 'wpc-multisite-products-copier'); ?>
                    </button>
                </div>
                
                <div class="wpc-mapping-legend">
                    <span class="wpc-legend-item">
                        <span class="wpc-status-dot wpc-status-synced"></span>
                        <?php esc_html_e('Synced', 'wpc-multisite-products-copier'); ?>
                    </span>
                    <span class="wpc-legend-item">
                        <span class="wpc-status-dot wpc-status-outdated"></span>
                        <?php esc_html_e('Outdated', 'wpc-multisite-products-copier'); ?>
                    </span>
                    <span class="wpc-legend-item">
                        <span class="wpc-status-dot wpc-status-not-exists"></span>
                        <?php esc_html_e('Not Exists', 'wpc-multisite-products-copier'); ?>
                    </span>
                </div>
            </div>
            
            <div id="wpc-mapping-container">
                <div class="wpc-loading">
                    <span class="spinner is-active"></span>
                    <p><?php esc_html_e('Loading product map...', 'wpc-multisite-products-copier'); ?></p>
                </div>
            </div>
            
            <!-- Product Details Modal -->
            <div id="wpc-product-details-modal" class="wpc-modal" style="display: none;">
                <div class="wpc-modal-content">
                    <span class="wpc-modal-close">&times;</span>
                    <h2 id="wpc-modal-title"></h2>
                    <div id="wpc-modal-body"></div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            var wpc_sites = <?php echo json_encode($sites_data); ?>;
        </script>
        <?php
    }
    
    /**
     * AJAX handler to get product mapping data
     */
    public function ajax_get_product_mapping() {
        check_ajax_referer('wpc_product_mapping', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_die('Unauthorized');
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sync_filter = isset($_POST['sync_filter']) ? sanitize_text_field($_POST['sync_filter']) : '';
        $category_filter = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 50;
        
        // Get all sites
        $sites = get_sites();
        $sites_data = array();
        
        foreach ($sites as $site) {
            $sites_data[$site->blog_id] = array(
                'id' => $site->blog_id,
                'name' => $site->blogname,
                'url' => $site->siteurl
            );
        }
        
        // Switch to source blog to get products
        switch_to_blog(5);
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_product_type',
                    'value' => 'variable',
                    'compare' => '='
                )
            )
        );
        
        // Add search
        if (!empty($search)) {
            $args['s'] = $search;
        }
        
        // Add category filter
        if ($category_filter > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_filter
                )
            );
        }
        
        $query = new WP_Query($args);
        $products_data = array();
        
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            
            // Get synced products data
            $synced_products = get_post_meta($post->ID, '_wpc_synced_product_ids', true);
            if (!is_array($synced_products)) {
                $synced_products = array();
            }
            
            // Get last modified time
            $last_modified = get_post_modified_time('U', false, $post->ID);
            
            // Build site status array
            $site_status = array();
            foreach ($sites_data as $blog_id => $site_info) {
                if ($blog_id == 5) {
                    // Source site
                    $site_status[$blog_id] = array(
                        'exists' => true,
                        'product_id' => $post->ID,
                        'status' => 'source',
                        'last_sync' => null,
                        'is_outdated' => false,
                        'edit_url' => get_edit_post_link($post->ID)
                    );
                } else if (isset($synced_products[$blog_id])) {
                    // Check if product exists and get sync status
                    $sync_data = $this->check_product_sync_status($post->ID, $synced_products[$blog_id], $blog_id, $last_modified);
                    $site_status[$blog_id] = $sync_data;
                } else {
                    // Not synced
                    $site_status[$blog_id] = array(
                        'exists' => false,
                        'product_id' => null,
                        'status' => 'not_exists',
                        'last_sync' => null,
                        'is_outdated' => false,
                        'edit_url' => null
                    );
                }
            }
            
            // Apply sync filter
            if (!empty($sync_filter)) {
                $sync_count = 0;
                $outdated_count = 0;
                $total_sites = count($sites_data) - 1; // Exclude source
                
                foreach ($site_status as $blog_id => $status) {
                    if ($blog_id == 5) continue;
                    if ($status['exists']) $sync_count++;
                    if ($status['is_outdated']) $outdated_count++;
                }
                
                switch ($sync_filter) {
                    case 'synced':
                        if ($sync_count !== $total_sites) continue 2;
                        break;
                    case 'partial':
                        if ($sync_count === 0 || $sync_count === $total_sites) continue 2;
                        break;
                    case 'not_synced':
                        if ($sync_count !== 0) continue 2;
                        break;
                    case 'outdated':
                        if ($outdated_count === 0) continue 2;
                        break;
                }
            }
            
            $products_data[] = array(
                'id' => $post->ID,
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_price(),
                'stock_status' => $product->get_stock_status(),
                'categories' => wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names')),
                'last_modified' => $last_modified,
                'site_status' => $site_status
            );
        }
        
        restore_current_blog();
        
        wp_send_json_success(array(
            'products' => $products_data,
            'sites' => array_values($sites_data),
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $page
        ));
    }
    
    /**
     * Check product sync status
     */
    private function check_product_sync_status($source_product_id, $target_product_id, $target_blog_id, $source_last_modified) {
        switch_to_blog($target_blog_id);
        
        $target_product = wc_get_product($target_product_id);
        
        if (!$target_product) {
            restore_current_blog();
            return array(
                'exists' => false,
                'product_id' => $target_product_id,
                'status' => 'deleted',
                'last_sync' => null,
                'is_outdated' => false,
                'edit_url' => null
            );
        }
        
        // Get last sync time from meta
        $last_sync = get_post_meta($target_product_id, '_wpc_last_sync', true);
        $is_outdated = false;
        
        if ($last_sync && $source_last_modified > $last_sync) {
            $is_outdated = true;
        }
        
        $result = array(
            'exists' => true,
            'product_id' => $target_product_id,
            'status' => $is_outdated ? 'outdated' : 'synced',
            'last_sync' => $last_sync,
            'is_outdated' => $is_outdated,
            'edit_url' => get_edit_post_link($target_product_id)
        );
        
        restore_current_blog();
        
        return $result;
    }
    
    /**
     * AJAX handler to sync a single product
     */
    public function ajax_sync_product() {
        check_ajax_referer('wpc_product_mapping', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_die('Unauthorized');
        }
        
        $source_product_id = isset($_POST['source_product_id']) ? intval($_POST['source_product_id']) : 0;
        $target_blog_id = isset($_POST['target_blog_id']) ? intval($_POST['target_blog_id']) : 0;
        $action = isset($_POST['sync_action']) ? sanitize_text_field($_POST['sync_action']) : '';
        
        if (!$source_product_id || !$target_blog_id || !in_array($action, array('create', 'update'))) {
            wp_send_json_error('Invalid parameters');
        }
        
        try {
            if ($action === 'create') {
                // Create new product
                $new_product_id = $this->copier->create_product_on_blog($source_product_id, $target_blog_id);
                
                if ($new_product_id) {
                    // Log activity
                    do_action('wpc_after_product_copy', $source_product_id, $new_product_id, $target_blog_id);
                    
                    wp_send_json_success(array(
                        'message' => __('Product created successfully', 'wpc-multisite-products-copier'),
                        'product_id' => $new_product_id,
                        'edit_url' => get_admin_url($target_blog_id, 'post.php?post=' . $new_product_id . '&action=edit')
                    ));
                } else {
                    wp_send_json_error(__('Failed to create product', 'wpc-multisite-products-copier'));
                }
            } else {
                // Update existing product
                $synced_products = get_post_meta($source_product_id, '_wpc_synced_product_ids', true);
                
                if (!is_array($synced_products) || !isset($synced_products[$target_blog_id])) {
                    wp_send_json_error(__('Product not synced to this site', 'wpc-multisite-products-copier'));
                }
                
                $target_product_id = $synced_products[$target_blog_id];
                $updated = $this->copier->update_product_on_blog($source_product_id, $target_blog_id, $target_product_id);
                
                if ($updated) {
                    // Update last sync time
                    switch_to_blog($target_blog_id);
                    update_post_meta($target_product_id, '_wpc_last_sync', time());
                    restore_current_blog();
                    
                    // Log activity
                    do_action('wpc_after_product_update', $source_product_id, $target_product_id, $target_blog_id);
                    
                    wp_send_json_success(array(
                        'message' => __('Product updated successfully', 'wpc-multisite-products-copier')
                    ));
                } else {
                    wp_send_json_error(__('Failed to update product', 'wpc-multisite-products-copier'));
                }
            }
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler to check sync status
     */
    public function ajax_check_sync_status() {
        check_ajax_referer('wpc_product_mapping', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_die('Unauthorized');
        }
        
        $source_product_id = isset($_POST['source_product_id']) ? intval($_POST['source_product_id']) : 0;
        $target_blog_id = isset($_POST['target_blog_id']) ? intval($_POST['target_blog_id']) : 0;
        
        if (!$source_product_id || !$target_blog_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Switch to source blog
        switch_to_blog(5);
        $source_product = wc_get_product($source_product_id);
        $source_last_modified = get_post_modified_time('U', false, $source_product_id);
        restore_current_blog();
        
        if (!$source_product) {
            wp_send_json_error('Source product not found');
        }
        
        // Get synced product
        $synced_products = get_post_meta($source_product_id, '_wpc_synced_product_ids', true);
        
        if (!is_array($synced_products) || !isset($synced_products[$target_blog_id])) {
            wp_send_json_success(array(
                'status' => 'not_synced',
                'can_create' => true
            ));
        }
        
        $target_product_id = $synced_products[$target_blog_id];
        $sync_data = $this->check_product_sync_status($source_product_id, $target_product_id, $target_blog_id, $source_last_modified);
        
        wp_send_json_success($sync_data);
    }
    
    /**
     * AJAX handler to export mapping data
     */
    public function ajax_export_mapping() {
        check_ajax_referer('wpc_product_mapping', 'nonce');
        
        if (!current_user_can('manage_network')) {
            wp_die('Unauthorized');
        }
        
        // Get all sites
        $sites = get_sites();
        
        // Prepare CSV headers
        $headers = array('Product ID', 'Product Name', 'SKU');
        foreach ($sites as $site) {
            $headers[] = $site->blogname . ' (ID: ' . $site->blog_id . ')';
        }
        
        // Switch to source blog
        switch_to_blog(5);
        
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_product_type',
                    'value' => 'variable',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($args);
        $csv_data = array();
        
        foreach ($query->posts as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            
            $row = array(
                $post->ID,
                $product->get_name(),
                $product->get_sku()
            );
            
            // Get sync status for each site
            $synced_products = get_post_meta($post->ID, '_wpc_synced_product_ids', true);
            if (!is_array($synced_products)) {
                $synced_products = array();
            }
            
            foreach ($sites as $site) {
                if ($site->blog_id == 5) {
                    $row[] = 'SOURCE';
                } else if (isset($synced_products[$site->blog_id])) {
                    $row[] = 'Product ID: ' . $synced_products[$site->blog_id];
                } else {
                    $row[] = 'Not Synced';
                }
            }
            
            $csv_data[] = $row;
        }
        
        restore_current_blog();
        
        // Generate CSV filename
        $filename = 'product-network-map-' . date('Y-m-d-H-i-s') . '.csv';
        
        // Create CSV content
        $csv_content = '';
        
        // Add headers
        $csv_content .= implode(',', array_map(function($header) {
            return '"' . str_replace('"', '""', $header) . '"';
        }, $headers)) . "\n";
        
        // Add data rows
        foreach ($csv_data as $row) {
            $csv_content .= implode(',', array_map(function($cell) {
                return '"' . str_replace('"', '""', $cell) . '"';
            }, $row)) . "\n";
        }
        
        wp_send_json_success(array(
            'filename' => $filename,
            'content' => base64_encode($csv_content),
            'mime_type' => 'text/csv'
        ));
    }
}