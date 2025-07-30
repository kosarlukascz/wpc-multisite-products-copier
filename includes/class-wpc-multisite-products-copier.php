<?php
/**
 * Main plugin class for WPC Multisite Products Copier
 *
 * @package WPC_Multisite_Products_Copier
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class WPC_Multisite_Products_Copier {

    /**
     * The single instance of the class
     *
     * @var WPC_Multisite_Products_Copier
     */
    private static $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version = '1.1.8'; // Added variation stock management on update

    /**
     * Source blog ID (always 5)
     *
     * @var int
     */
    private $source_blog_id = 5;

    /**
     * Enable debug logging
     *
     * @var bool
     */
    private $debug_enabled = true;

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file = '';

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_logging();
    }

    /**
     * Get the singleton instance
     *
     * @return WPC_Multisite_Products_Copier
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {
        // Cloning instances of the class is forbidden
        _doing_it_wrong(__FUNCTION__, esc_html__('Cloning is forbidden.', 'wpc-multisite-products-copier'), '1.0.0');
    }

    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        // Unserializing instances of the class is forbidden
        _doing_it_wrong(__FUNCTION__, esc_html__('Unserializing is forbidden.', 'wpc-multisite-products-copier'), '1.0.0');
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Defer initialization to 'init' hook to ensure WordPress is fully loaded
        add_action('init', array($this, 'late_init'), 0);
        
        // Load plugin textdomain
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Late initialization after WordPress is loaded
     */
    public function late_init() {
        // Initialize activity log for network admin
        if (is_network_admin() || (defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN)) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-activity-log.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-dashboard-widget.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-product-mapping.php';
            
            new WPC_Activity_Log();
            new WPC_Dashboard_Widget();
            new WPC_Product_Mapping();
        }
        
        // Initialize bulk operations on admin
        if (is_admin() && get_current_blog_id() === $this->source_blog_id) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-bulk-operations.php';
            new WPC_Bulk_Operations();
        }
        
        // Initialize for AJAX requests
        if (wp_doing_ajax()) {
            if (isset($_REQUEST['action'])) {
                $action = $_REQUEST['action'];
                
                // Activity log actions
                if (in_array($action, array('wpc_mpc_create_product', 'wpc_mpc_update_product'))) {
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-activity-log.php';
                    new WPC_Activity_Log();
                }
                
                // Product mapping actions
                if (in_array($action, array('wpc_get_product_mapping', 'wpc_sync_product', 'wpc_check_sync_status', 'wpc_export_mapping'))) {
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-product-mapping.php';
                    new WPC_Product_Mapping();
                }
                
                // Bulk operations actions
                if (in_array($action, array('wpc_bulk_copy_products', 'wpc_bulk_update_products', 'wpc_get_bulk_operation_status'))) {
                    require_once plugin_dir_path(dirname(__FILE__)) . 'includes/admin/class-wpc-bulk-operations.php';
                    new WPC_Bulk_Operations();
                }
            }
        }
        
        // Check if we're on the source blog
        if (get_current_blog_id() !== $this->source_blog_id) {
            return;
        }

        // WooCommerce product metabox
        add_action('add_meta_boxes', array($this, 'add_product_metabox'));
        
        // Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_wpc_mpc_create_product', array($this, 'ajax_create_product'));
        add_action('wp_ajax_wpc_mpc_update_product', array($this, 'ajax_update_product'));
    }

    /**
     * Add product metabox
     */
    public function add_product_metabox() {
        // Only add to product post type
        add_meta_box(
            'wpc_multisite_products_copier',
            __('Multisite Product Sync', 'wpc-multisite-products-copier'),
            array($this, 'render_product_metabox'),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render product metabox
     */
    public function render_product_metabox($post) {
        // Security nonce
        wp_nonce_field('wpc_mpc_metabox', 'wpc_mpc_metabox_nonce');
        
        // Get synced products meta
        $synced_products = get_post_meta($post->ID, '_wpc_synced_product_ids', true);
        if (!is_array($synced_products)) {
            $synced_products = array();
        }
        
        // Get all sites except current
        $sites = get_sites();
        $current_blog_id = get_current_blog_id();
        ?>
        <div class="wpc-mpc-metabox">
            <div class="wpc-site-selection">
                <label><?php esc_html_e('Select Target Sites:', 'wpc-multisite-products-copier'); ?></label>
                <div class="wpc-sites-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; margin: 8px 0;">
                    <?php foreach ($sites as $site) : ?>
                        <?php if ($site->blog_id != $current_blog_id) : ?>
                            <?php 
                            $is_synced = isset($synced_products[$site->blog_id]);
                            $synced_product_id = $is_synced ? $synced_products[$site->blog_id] : '';
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" 
                                       class="wpc-target-site-checkbox" 
                                       name="wpc_target_sites[]" 
                                       value="<?php echo esc_attr($site->blog_id); ?>"
                                       data-synced="<?php echo $is_synced ? '1' : '0'; ?>"
                                       data-product-id="<?php echo esc_attr($synced_product_id); ?>">
                                <?php echo esc_html($site->blogname); ?> (ID: <?php echo esc_html($site->blog_id); ?>)
                                <?php if ($is_synced) : ?>
                                    <span style="color: #46b450; font-size: 11px;">✓ <?php esc_html_e('Synced', 'wpc-multisite-products-copier'); ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div style="margin-bottom: 10px;">
                    <a href="#" id="wpc-select-all" style="font-size: 12px;"><?php esc_html_e('Select All', 'wpc-multisite-products-copier'); ?></a> | 
                    <a href="#" id="wpc-select-none" style="font-size: 12px;"><?php esc_html_e('Select None', 'wpc-multisite-products-copier'); ?></a> | 
                    <a href="#" id="wpc-select-not-synced" style="font-size: 12px;"><?php esc_html_e('Select Not Synced', 'wpc-multisite-products-copier'); ?></a>
                </div>
            </div>
            
            <div class="wpc-mpc-actions" style="margin-top: 10px;">
                <button type="button" id="wpc_mpc_create_multiple" class="button button-primary" disabled>
                    <?php esc_html_e('Create on Selected Sites', 'wpc-multisite-products-copier'); ?>
                </button>
                <button type="button" id="wpc_mpc_update_multiple" class="button" disabled>
                    <?php esc_html_e('Update on Selected Sites', 'wpc-multisite-products-copier'); ?>
                </button>
            </div>
            
            <div class="wpc-mpc-status" style="margin-top: 10px; display: none;">
                <span class="spinner" style="float: none; vertical-align: middle;"></span>
                <span class="status-message"></span>
            </div>
            
            <?php if (!empty($synced_products)) : ?>
                <div class="wpc-mpc-synced-info" style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #ddd;">
                    <strong><?php esc_html_e('Synced to:', 'wpc-multisite-products-copier'); ?></strong>
                    <ul style="margin: 5px 0 0 20px;">
                        <?php foreach ($synced_products as $blog_id => $product_id) : ?>
                            <?php
                            $site = get_site($blog_id);
                            if ($site) :
                                // Build the product edit URL for the target site
                                $edit_url = get_admin_url($blog_id, 'post.php?post=' . $product_id . '&action=edit');
                            ?>
                                <li>
                                    <a href="<?php echo esc_url($edit_url); ?>" target="_blank" style="text-decoration: none;">
                                        <?php echo esc_html($site->blogname); ?> 
                                        <small>(<?php echo esc_html__('Product ID:', 'wpc-multisite-products-copier') . ' ' . esc_html($product_id); ?>)</small>
                                        <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        global $post;
        
        // Only load on product edit page
        if (($hook !== 'post.php' && $hook !== 'post-new.php') || !isset($post) || $post->post_type !== 'product') {
            return;
        }
        
        // Enqueue script
        wp_enqueue_script(
            'wpc-mpc-admin',
            WPC_MPC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Localize script
        wp_localize_script('wpc-mpc-admin', 'wpc_mpc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpc_mpc_ajax'),
            'product_id' => $post->ID,
            'messages' => array(
                'select_site' => __('Please select a target site.', 'wpc-multisite-products-copier'),
                'creating' => __('Creating product...', 'wpc-multisite-products-copier'),
                'updating' => __('Updating product...', 'wpc-multisite-products-copier'),
                'success_create' => __('Product created successfully!', 'wpc-multisite-products-copier'),
                'success_update' => __('Product updated successfully!', 'wpc-multisite-products-copier'),
                'error' => __('An error occurred. Please try again.', 'wpc-multisite-products-copier'),
            )
        ));
    }

    /**
     * AJAX handler for creating product
     */
    public function ajax_create_product() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpc_mpc_ajax')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wpc-multisite-products-copier')));
        }
        
        // Check capabilities
        if (!current_user_can('edit_products')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wpc-multisite-products-copier')));
        }
        
        // Validate input
        $source_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $target_blog_id = isset($_POST['target_blog_id']) ? intval($_POST['target_blog_id']) : 0;
        
        if (!$source_product_id || !$target_blog_id) {
            wp_send_json_error(array('message' => __('Invalid product or target site.', 'wpc-multisite-products-copier')));
        }
        
        // Perform the product creation
        $result = $this->create_product_on_blog($source_product_id, $target_blog_id);
        
        if (is_wp_error($result)) {
            // Log error
            error_log('WPC MPC Create Error: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Product created successfully!', 'wpc-multisite-products-copier'),
            'target_product_id' => $result
        ));
    }

    /**
     * AJAX handler for updating product
     */
    public function ajax_update_product() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpc_mpc_ajax')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wpc-multisite-products-copier')));
        }
        
        // Check capabilities
        if (!current_user_can('edit_products')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wpc-multisite-products-copier')));
        }
        
        // Validate input
        $source_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $target_blog_id = isset($_POST['target_blog_id']) ? intval($_POST['target_blog_id']) : 0;
        
        if (!$source_product_id || !$target_blog_id) {
            wp_send_json_error(array('message' => __('Invalid product or target site.', 'wpc-multisite-products-copier')));
        }
        
        // Get target product ID from meta
        $synced_products = get_post_meta($source_product_id, '_wpc_synced_product_ids', true);
        if (!is_array($synced_products) || !isset($synced_products[$target_blog_id])) {
            wp_send_json_error(array('message' => __('Product not found on target site.', 'wpc-multisite-products-copier')));
        }
        
        $target_product_id = $synced_products[$target_blog_id];
        
        // Perform the product update
        $result = $this->update_product_on_blog($source_product_id, $target_blog_id, $target_product_id);
        
        if (is_wp_error($result)) {
            // Log error
            error_log('WPC MPC Update Error: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => __('Product updated successfully!', 'wpc-multisite-products-copier')
        ));
    }

    /**
     * Create product on target blog
     *
     * @param int $source_product_id Source product ID
     * @param int $target_blog_id Target blog ID
     * @return int|WP_Error New product ID or error
     */
    public function create_product_on_blog($source_product_id, $target_blog_id) {
        try {
            // Get source product
            $source_product = wc_get_product($source_product_id);
            if (!$source_product || !$source_product->is_type('variable')) {
                return new WP_Error('invalid_product', __('Source product must be a variable product.', 'wpc-multisite-products-copier'));
            }

            // Store source blog ID
            $source_blog_id = get_current_blog_id();
            
            // Ensure we're on the source blog
            if (get_current_blog_id() !== $source_blog_id) {
                switch_to_blog($source_blog_id);
                // Re-get the product to ensure we have the correct data
                $source_product = wc_get_product($source_product_id);
            }

            // Get source product data
            $product_data = array(
                'post_title' => $source_product->get_name(),
                'post_name' => $source_product->get_slug(),
                'post_content' => $source_product->get_description(),
                'post_status' => 'draft',
                'post_type' => 'product'
            );

            // Get source product meta
            $source_sku = $source_product->get_sku();
            $source_weight = $source_product->get_weight();
            
            // Get custom fields
            $custom_fields = array(
                'feed_name' => get_post_meta($source_product_id, 'feed_name', true),
                '_color' => get_post_meta($source_product_id, '_color', true),
                '_gender' => get_post_meta($source_product_id, '_gender', true),
                'custom_order_wcp' => get_post_meta($source_product_id, 'custom_order_wcp', true)
            );

            // Get ACF fields with attachment IDs
            $acf_fields = array();
            if (function_exists('get_field')) {
                // Get raw attachment IDs (not formatted URLs)
                $feed_image = get_field('feed_image', $source_product_id, false);
                $swatch_image = get_field('swatch_image', $source_product_id, false);
                
                // If ACF returns URL instead of ID, try getting from post meta directly
                if ($feed_image && !is_numeric($feed_image)) {
                    $feed_image = get_post_meta($source_product_id, 'feed_image', true);
                    // If still URL, try to get attachment ID from URL
                    if ($feed_image && !is_numeric($feed_image)) {
                        $feed_image = attachment_url_to_postid($feed_image);
                    }
                }
                if ($swatch_image && !is_numeric($swatch_image)) {
                    $swatch_image = get_post_meta($source_product_id, 'swatch_image', true);
                    // If still URL, try to get attachment ID from URL
                    if ($swatch_image && !is_numeric($swatch_image)) {
                        $swatch_image = attachment_url_to_postid($swatch_image);
                    }
                }
                
                $this->log("ACF field values", array(
                    'feed_image' => $feed_image,
                    'swatch_image' => $swatch_image,
                    'feed_image_is_numeric' => is_numeric($feed_image),
                    'swatch_image_is_numeric' => is_numeric($swatch_image)
                ));
                
                // Store ACF field data - only if we have numeric attachment IDs
                if ($feed_image && is_numeric($feed_image)) {
                    $acf_fields['feed_image'] = array(
                        'value' => intval($feed_image),
                        'field_key' => $this->get_acf_field_key('feed_image')
                    );
                }
                if ($swatch_image && is_numeric($swatch_image)) {
                    $acf_fields['swatch_image'] = array(
                        'value' => intval($swatch_image),
                        'field_key' => $this->get_acf_field_key('swatch_image')
                    );
                }
            }

            // Get featured image ID - try multiple methods
            $thumbnail_id = get_post_thumbnail_id($source_product_id);
            if (!$thumbnail_id) {
                // Try getting from meta directly
                $thumbnail_id = get_post_meta($source_product_id, '_thumbnail_id', true);
            }
            
            // Debug: Check all image-related meta
            $all_meta = get_post_meta($source_product_id);
            $image_meta = array();
            foreach ($all_meta as $key => $value) {
                if (strpos($key, 'image') !== false || strpos($key, 'thumbnail') !== false || strpos($key, 'gallery') !== false) {
                    $image_meta[$key] = $value;
                }
            }
            
            $this->log("Featured image retrieval", array(
                'thumbnail_id' => $thumbnail_id,
                'source_product_id' => $source_product_id,
                'get_post_thumbnail_id' => get_post_thumbnail_id($source_product_id),
                '_thumbnail_id_meta' => get_post_meta($source_product_id, '_thumbnail_id', true),
                'current_blog_id' => get_current_blog_id(),
                'image_related_meta' => $image_meta
            ));

            // Get gallery image IDs - try multiple methods
            $gallery_ids = $source_product->get_gallery_image_ids();
            if (empty($gallery_ids)) {
                // Try getting from meta directly
                $gallery_ids_meta = get_post_meta($source_product_id, '_product_image_gallery', true);
                if ($gallery_ids_meta) {
                    $gallery_ids = array_filter(explode(',', $gallery_ids_meta));
                }
            }
            
            $this->log("Gallery image retrieval", array(
                'gallery_ids' => $gallery_ids,
                'get_gallery_image_ids' => $source_product->get_gallery_image_ids(),
                '_product_image_gallery_meta' => get_post_meta($source_product_id, '_product_image_gallery', true),
                'current_blog_id' => get_current_blog_id()
            ));

            // Get attributes
            $attributes = $source_product->get_attributes();
            
            // Get variations
            $variations = $source_product->get_children();

            // Switch to target blog
            switch_to_blog($target_blog_id);

            // Create the product post
            $new_product_id = wp_insert_post($product_data);
            if (is_wp_error($new_product_id)) {
                restore_current_blog();
                return $new_product_id;
            }

            // Set product type
            wp_set_object_terms($new_product_id, 'variable', 'product_type');

            // Create WC product object
            $new_product = new WC_Product_Variable($new_product_id);

            // Set basic product data
            if ($source_sku) {
                $new_product->set_sku($source_sku);
            }
            if ($source_weight) {
                $new_product->set_weight($source_weight);
            }

            // Copy custom fields
            foreach ($custom_fields as $key => $value) {
                if ($value !== false && $value !== '') {
                    update_post_meta($new_product_id, $key, $value);
                }
            }

            // Copy attributes - Need to find matching terms on target blog
            $new_attributes = array();
            $term_slug_mapping = array(); // Map source term slugs to target term slugs
            
            // Process attributes
            
            foreach ($attributes as $attribute) {
                if ($attribute->is_taxonomy()) {
                    // Handle taxonomy attributes
                    $taxonomy = $attribute->get_taxonomy();
                    
                    // Process taxonomy attribute
                    
                    // Get source terms
                    switch_to_blog($source_blog_id);
                    $source_terms = wp_get_post_terms($source_product_id, $taxonomy, array('fields' => 'all'));
                    restore_current_blog();
                    
                    // Process source terms
                    
                    // Find matching terms on target blog by slug
                    $target_term_ids = array();
                    foreach ($source_terms as $source_term) {
                        $target_term = get_term_by('slug', $source_term->slug, $taxonomy);
                        if ($target_term) {
                            $target_term_ids[] = $target_term->term_id;
                            // Store slug mapping for variations
                            $term_slug_mapping[$taxonomy][$source_term->slug] = $target_term->slug;
                            
                            // Found existing term
                        } else {
                            // Create term if it doesn't exist
                            // Create new term
                            
                            $new_term = wp_insert_term(
                                $source_term->name,
                                $taxonomy,
                                array(
                                    'slug' => $source_term->slug,
                                    'description' => $source_term->description
                                )
                            );
                            
                            if (!is_wp_error($new_term)) {
                                $target_term_ids[] = $new_term['term_id'];
                                // Get the created term to store slug mapping
                                $created_term = get_term($new_term['term_id'], $taxonomy);
                                if ($created_term) {
                                    $term_slug_mapping[$taxonomy][$source_term->slug] = $created_term->slug;
                                }
                                
                                // Term created successfully
                            } else {
                                // Failed to create term
                            }
                        }
                    }
                    
                    // Set terms on new product
                    if (!empty($target_term_ids)) {
                        $set_terms_result = wp_set_object_terms($new_product_id, $target_term_ids, $taxonomy);
                        // Set object terms
                        
                        // Create attribute object
                        $new_attr = new WC_Product_Attribute();
                        
                        // Get the attribute ID from the taxonomy
                        $attribute_id = wc_attribute_taxonomy_id_by_name($taxonomy);
                        if ($attribute_id) {
                            $new_attr->set_id($attribute_id);
                        }
                        
                        $new_attr->set_name($taxonomy);
                        $new_attr->set_options($target_term_ids);
                        $new_attr->set_position($attribute->get_position());
                        $new_attr->set_visible($attribute->get_visible());
                        $new_attr->set_variation($attribute->get_variation());
                        
                        $new_attributes[] = $new_attr;
                        // Created attribute object
                    }
                } else {
                    // Handle custom attributes
                    // Process custom attribute
                    
                    $new_attr = new WC_Product_Attribute();
                    $new_attr->set_name($attribute->get_name());
                    $new_attr->set_options($attribute->get_options());
                    $new_attr->set_position($attribute->get_position());
                    $new_attr->set_visible($attribute->get_visible());
                    $new_attr->set_variation($attribute->get_variation());
                    
                    $new_attributes[] = $new_attr;
                }
            }
            
            // Set attributes on product
            $new_product->set_attributes($new_attributes);
            
            // Handle product categories
            $target_category_ids = $this->handle_product_categories($source_product_id, $target_blog_id, $source_blog_id);
            if (!empty($target_category_ids)) {
                $new_product->set_category_ids($target_category_ids);
                $this->log("Set categories on new product", array(
                    'product_id' => $new_product_id,
                    'category_ids' => $target_category_ids
                ));
            }

            // Save product to generate variations
            $new_product->save();
            
            // Store the attributes in WooCommerce format
            $this->save_product_attributes_properly($new_product_id, $new_attributes);

            // Copy featured image
            $this->log("Checking for featured image", array(
                'thumbnail_id' => $thumbnail_id,
                'has_thumbnail' => !empty($thumbnail_id),
                'source_blog_id' => $source_blog_id,
                'target_blog_id' => $target_blog_id,
                'current_blog_before_copy' => get_current_blog_id()
            ));
            
            if ($thumbnail_id) {
                // Ensure we're on the source blog when copying
                $current_blog_before = get_current_blog_id();
                if ($current_blog_before !== $source_blog_id) {
                    switch_to_blog($source_blog_id);
                }
                
                $attachment_exists = get_post($thumbnail_id);
                $this->log("Thumbnail attachment check", array(
                    'attachment_id' => $thumbnail_id,
                    'exists' => !empty($attachment_exists),
                    'post_type' => $attachment_exists ? $attachment_exists->post_type : null,
                    'post_status' => $attachment_exists ? $attachment_exists->post_status : null,
                    'current_blog_during_check' => get_current_blog_id()
                ));
                
                if ($current_blog_before !== $source_blog_id) {
                    switch_to_blog($target_blog_id);
                }
                
                $new_thumbnail_id = $this->copy_image_to_blog($thumbnail_id, $target_blog_id, $source_blog_id);
                if ($new_thumbnail_id && !is_wp_error($new_thumbnail_id)) {
                    // Use both methods to ensure thumbnail is set
                    set_post_thumbnail($new_product_id, $new_thumbnail_id);
                    update_post_meta($new_product_id, '_thumbnail_id', $new_thumbnail_id);
                    
                    // Also set it on the product object
                    $new_product->set_image_id($new_thumbnail_id);
                    $new_product->save();
                    
                    $this->log("Set featured image", array(
                        'product_id' => $new_product_id,
                        'attachment_id' => $new_thumbnail_id,
                        'method' => 'both set_post_thumbnail and update_post_meta'
                    ));
                } else {
                    $this->log("Failed to copy featured image", array(
                        'error' => is_wp_error($new_thumbnail_id) ? $new_thumbnail_id->get_error_message() : 'Unknown error',
                        'thumbnail_id' => $thumbnail_id
                    ), 'error');
                }
            } else {
                $this->log("No featured image to copy");
            }

            // Copy gallery images
            $this->log("Checking for gallery images", array(
                'gallery_ids' => $gallery_ids,
                'count' => count($gallery_ids),
                'current_blog' => get_current_blog_id()
            ));
            
            if (!empty($gallery_ids)) {
                $new_gallery_ids = array();
                foreach ($gallery_ids as $gallery_id) {
                    $this->log("Processing gallery image", array(
                        'gallery_id' => $gallery_id,
                        'index' => array_search($gallery_id, $gallery_ids)
                    ));
                    
                    $new_gallery_id = $this->copy_image_to_blog($gallery_id, $target_blog_id, $source_blog_id);
                    if ($new_gallery_id && !is_wp_error($new_gallery_id)) {
                        $new_gallery_ids[] = $new_gallery_id;
                        $this->log("Successfully copied gallery image", array(
                            'source_id' => $gallery_id,
                            'target_id' => $new_gallery_id
                        ));
                    } else {
                        $this->log("Failed to copy gallery image", array(
                            'gallery_id' => $gallery_id,
                            'error' => is_wp_error($new_gallery_id) ? $new_gallery_id->get_error_message() : 'Unknown error'
                        ), 'error');
                    }
                }
                if (!empty($new_gallery_ids)) {
                    $new_product->set_gallery_image_ids($new_gallery_ids);
                    $this->log("Set gallery images", array(
                        'product_id' => $new_product_id,
                        'gallery_ids' => $new_gallery_ids,
                        'count' => count($new_gallery_ids)
                    ));
                }
            } else {
                $this->log("No gallery images to copy");
            }
            
            // Save product after setting images
            $new_product->save();

            // Copy ACF image fields
            $this->log("Checking for ACF fields", array(
                'acf_fields' => $acf_fields,
                'has_acf' => !empty($acf_fields),
                'function_exists' => function_exists('update_field')
            ));
            
            if (!empty($acf_fields) && function_exists('update_field')) {
                foreach ($acf_fields as $field_name => $field_data) {
                    if (!empty($field_data['value'])) {
                        $this->log("Copying ACF image field", array(
                            'field_name' => $field_name,
                            'attachment_id' => $field_data['value'],
                            'field_key' => $field_data['field_key']
                        ));
                        
                        $new_attachment_id = $this->copy_image_to_blog($field_data['value'], $target_blog_id, $source_blog_id);
                        if ($new_attachment_id && !is_wp_error($new_attachment_id)) {
                            // Get the field key for the target blog
                            $target_field_key = $this->get_acf_field_key($field_name);
                            
                            // Set ACF field using multiple methods to ensure it works
                            
                            // Method 1: Use update_field with field key if available on target blog
                            if (!empty($target_field_key) && function_exists('update_field')) {
                                update_field($target_field_key, $new_attachment_id, $new_product_id);
                            }
                            
                            // Method 2: Use update_field with field name as primary method
                            if (function_exists('update_field')) {
                                update_field($field_name, $new_attachment_id, $new_product_id);
                            }
                            
                            // Method 3: Set meta directly as fallback (ACF stores both field_name and _field_name)
                            update_post_meta($new_product_id, $field_name, $new_attachment_id);
                            if (!empty($target_field_key)) {
                                update_post_meta($new_product_id, '_' . $field_name, $target_field_key);
                            }
                            
                            $this->log("Set ACF field", array(
                                'field_name' => $field_name,
                                'source_field_key' => $field_data['field_key'],
                                'target_field_key' => $target_field_key,
                                'attachment_id' => $new_attachment_id,
                                'methods_used' => 'update_field + direct meta'
                            ));
                            
                            // Verify what was actually saved
                            $saved_value = get_field($field_name, $new_product_id);
                            $saved_meta = get_post_meta($new_product_id, $field_name, true);
                            $saved_underscore_meta = get_post_meta($new_product_id, '_' . $field_name, true);
                            
                            $this->log("Verify ACF field save", array(
                                'field_name' => $field_name,
                                'get_field_result' => $saved_value,
                                'direct_meta' => $saved_meta,
                                'underscore_meta' => $saved_underscore_meta,
                                'expected_attachment_id' => $new_attachment_id
                            ));
                        } else {
                            $this->log("Failed to copy ACF image", array(
                                'field_name' => $field_name,
                                'error' => is_wp_error($new_attachment_id) ? $new_attachment_id->get_error_message() : 'Unknown error'
                            ), 'error');
                        }
                    }
                }
            }

            // Create variations
            foreach ($variations as $variation_id) {
                switch_to_blog($source_blog_id);
                $source_variation = wc_get_product($variation_id);
                if (!$source_variation) {
                    continue;
                }

                // Get variation data
                $variation_sku = $source_variation->get_sku();
                $regular_price = $source_variation->get_regular_price();
                $sale_price = $source_variation->get_sale_price();
                $stock_status = $source_variation->get_stock_status();
                $manage_stock = $source_variation->get_manage_stock();
                $stock_quantity = $source_variation->get_stock_quantity();
                $backorders = $source_variation->get_backorders();
                $low_stock_amount = get_post_meta($variation_id, '_low_stock_amount', true);
                $sale_date_from = $source_variation->get_date_on_sale_from();
                $sale_date_to = $source_variation->get_date_on_sale_to();
                $variation_attributes = $source_variation->get_attributes();
                
                // Get GTIN code
                $gtin_code = get_post_meta($variation_id, '_wpm_gtin_code', true);
                
                // Process variation

                switch_to_blog($target_blog_id);

                // Create variation
                $variation_post = array(
                    'post_title' => $source_variation->get_name(),
                    'post_name' => 'product-' . $new_product_id . '-variation',
                    'post_status' => 'publish',
                    'post_parent' => $new_product_id,
                    'post_type' => 'product_variation',
                    'guid' => home_url() . '/?product_variation=product-' . $new_product_id . '-variation'
                );

                $new_variation_id = wp_insert_post($variation_post);
                if (!is_wp_error($new_variation_id)) {
                    $new_variation = new WC_Product_Variation($new_variation_id);
                    
                    // Set variation data
                    if ($variation_sku) {
                        $new_variation->set_sku($variation_sku);
                    }
                    if ($regular_price !== '') {
                        $new_variation->set_regular_price($regular_price);
                    }
                    if ($sale_price !== '') {
                        $new_variation->set_sale_price($sale_price);
                    }
                    if ($sale_date_from) {
                        $new_variation->set_date_on_sale_from($sale_date_from);
                    }
                    if ($sale_date_to) {
                        $new_variation->set_date_on_sale_to($sale_date_to);
                    }
                    
                    $new_variation->set_stock_status($stock_status);
                    $new_variation->set_manage_stock($manage_stock);
                    if ($manage_stock) {
                        if ($stock_quantity !== null) {
                            $new_variation->set_stock_quantity($stock_quantity);
                        }
                        $new_variation->set_backorders($backorders);
                        
                        // Set low stock amount if available
                        if ($low_stock_amount !== '') {
                            update_post_meta($new_variation_id, '_low_stock_amount', $low_stock_amount);
                        }
                    }
                    
                    // Map variation attributes to target blog slugs
                    $mapped_attributes = array();
                    foreach ($variation_attributes as $taxonomy => $value) {
                        // If we have a mapping for this taxonomy and value, use it
                        if (isset($term_slug_mapping[$taxonomy]) && isset($term_slug_mapping[$taxonomy][$value])) {
                            $mapped_attributes[$taxonomy] = $term_slug_mapping[$taxonomy][$value];
                            // Mapped variation attribute
                        } else {
                            // Use original value if no mapping exists (for custom attributes)
                            $mapped_attributes[$taxonomy] = $value;
                        }
                    }
                    
                    $new_variation->set_attributes($mapped_attributes);
                    
                    $new_variation->save();
                    
                    // Copy GTIN code if present
                    if ($gtin_code) {
                        update_post_meta($new_variation_id, '_wpm_gtin_code', $gtin_code);
                        // Copied GTIN code
                    }
                    
                    // Created variation
                }
            }

            // Final save
            $new_product->save();
            
            // Handle Woodmart video gallery meta if gallery images were created
            if (!empty($new_gallery_ids)) {
                $this->handle_woodmart_video_gallery(
                    $source_product_id, 
                    $new_product_id, 
                    $new_gallery_ids, 
                    $source_blog_id, 
                    $target_blog_id
                );
            }
            
            // Sync variation attributes properly
            $this->sync_variable_product_attributes($new_product_id);
            
            // IMPORTANT: Update attribute lookup table for variations
            $this->update_product_attribute_lookup_table($new_product_id);

            // Switch back to source blog
            restore_current_blog();

            // Save synced product IDs on source product
            $synced_products = get_post_meta($source_product_id, '_wpc_synced_product_ids', true);
            if (!is_array($synced_products)) {
                $synced_products = array();
            }
            $synced_products[$target_blog_id] = $new_product_id;
            update_post_meta($source_product_id, '_wpc_synced_product_ids', $synced_products);

            // Save reference on target product
            switch_to_blog($target_blog_id);
            update_post_meta($new_product_id, '_wpc_source_product', array(
                'blog_id' => $source_blog_id,
                'product_id' => $source_product_id
            ));
            update_post_meta($new_product_id, '_wpc_last_sync', time());
            restore_current_blog();

            // Trigger action for logging
            do_action('wpc_after_product_copy', $source_product_id, $new_product_id, $target_blog_id);
            
            return $new_product_id;

        } catch (Exception $e) {
            if (ms_is_switched()) {
                restore_current_blog();
            }
            error_log('WPC MPC Error: ' . $e->getMessage());
            return new WP_Error('creation_failed', $e->getMessage());
        }
    }

    /**
     * Get ACF field key by field name
     *
     * @param string $field_name The field name
     * @return string|null The field key or null
     */
    private function get_acf_field_key($field_name) {
        global $wpdb;
        
        $this->log("Looking for ACF field key", array('field_name' => $field_name));
        
        // First, try to get the field key from a product that has this field
        $posts_with_field = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = %s 
             LIMIT 10",
            $field_name
        ));
        
        if (!empty($posts_with_field)) {
            foreach ($posts_with_field as $post_id) {
                // Look for the corresponding underscore prefixed meta key
                $field_key = get_post_meta($post_id, '_' . $field_name, true);
                if ($field_key && strpos($field_key, 'field_') === 0) {
                    $this->log("Found ACF field key from post meta", array(
                        'field_name' => $field_name,
                        'field_key' => $field_key,
                        'post_id' => $post_id
                    ));
                    return $field_key;
                }
            }
        }
        
        // Try to find the field key from ACF field groups
        $field_key = $wpdb->get_var($wpdb->prepare(
            "SELECT post_name FROM {$wpdb->posts} p
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'acf-field'
             AND pm.meta_key = 'name'
             AND pm.meta_value = %s
             LIMIT 1",
            $field_name
        ));
        
        if ($field_key) {
            $this->log("Found ACF field key from field groups", array(
                'field_name' => $field_name,
                'field_key' => $field_key
            ));
            return $field_key;
        }
        
        // Common field keys as fallback
        $known_fields = array(
            'feed_image' => 'field_65d4f8a5e8e8e',
            'swatch_image' => 'field_65d4f8c5e8e8f'
        );
        
        if (isset($known_fields[$field_name])) {
            $field_key = $known_fields[$field_name];
            $this->log("Using fallback ACF field key", array(
                'field_name' => $field_name,
                'field_key' => $field_key
            ));
            return $field_key;
        }
        
        $this->log("Could not find ACF field key", array('field_name' => $field_name), 'warning');
        return null;
    }

    /**
     * Copy image attachment to target blog
     *
     * @param int $attachment_id Source attachment ID
     * @param int $target_blog_id Target blog ID
     * @param int $source_blog_id Source blog ID
     * @return int|WP_Error New attachment ID or error
     */
    private function copy_image_to_blog($attachment_id, $target_blog_id, $source_blog_id, $product_id = null, $skip_if_thumbnail = false) {
        $this->log("Starting image copy - VERSION " . $this->version, array(
            'attachment_id' => $attachment_id,
            'source_blog_id' => $source_blog_id,
            'target_blog_id' => $target_blog_id,
            'current_blog_id' => get_current_blog_id(),
            'plugin_version' => $this->version,
            'product_id' => $product_id,
            'skip_if_thumbnail' => $skip_if_thumbnail
        ));
        
        // Switch to source blog to get attachment data
        switch_to_blog($source_blog_id);
        
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            restore_current_blog();
            $this->log("Invalid attachment", array(
                'attachment_id' => $attachment_id,
                'attachment' => $attachment,
                'current_blog_id' => get_current_blog_id()
            ), 'error');
            return new WP_Error('invalid_attachment', __('Invalid attachment ID.', 'wpc-multisite-products-copier'));
        }

        // Get attachment metadata
        $file_path = get_attached_file($attachment_id);
        $metadata = wp_get_attachment_metadata($attachment_id);
        $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        $attachment_url = wp_get_attachment_url($attachment_id);
        
        $this->log("Source attachment data", array(
            'file_path' => $file_path,
            'attached_file' => $attached_file,
            'attachment_url' => $attachment_url,
            'has_metadata' => !empty($metadata),
            'current_blog_id' => get_current_blog_id()
        ));
        
        // Check if file exists
        if (!file_exists($file_path)) {
            restore_current_blog();
            $this->log("Source file does not exist", array('file_path' => $file_path), 'error');
            return new WP_Error('file_not_found', __('Source file does not exist.', 'wpc-multisite-products-copier'));
        }
        
        // Switch to target blog
        switch_to_blog($target_blog_id);
        
        // Check if the existing attachment is currently set as thumbnail
        $current_thumbnail_id = null;
        if ($product_id && $skip_if_thumbnail) {
            $current_thumbnail_id = get_post_thumbnail_id($product_id);
        }
        
        // FOR UPDATE: Always delete existing attachment and create new one
        // This ensures images are always updated during update operation
        $existing = get_posts(array(
            'post_type' => 'attachment',
            'meta_key' => '_wp_attached_file',
            'meta_value' => $attached_file,
            'posts_per_page' => 1
        ));
        
        if (!empty($existing)) {
            $existing_id = $existing[0]->ID;
            
            // If this is the current thumbnail and we're processing gallery images, reuse it
            if ($skip_if_thumbnail && $current_thumbnail_id && $existing_id == $current_thumbnail_id) {
                $this->log("Existing attachment is current thumbnail, reusing it", array(
                    'existing_id' => $existing_id,
                    'filename' => $attached_file,
                    'is_thumbnail' => true
                ));
                restore_current_blog();
                return $existing_id;
            }
            
            $this->log("Found existing attachment, deleting it for update", array(
                'existing_id' => $existing_id,
                'filename' => $attached_file,
                'is_thumbnail' => ($existing_id == $current_thumbnail_id)
            ));
            
            // Delete the old attachment completely
            wp_delete_attachment($existing_id, true);
            $this->log("Deleted existing attachment", array(
                'attachment_id' => $existing_id
            ));
        }
        
        // Get target upload directory
        $target_upload_dir = wp_upload_dir();
        
        // Build target file path maintaining the same year/month structure
        $target_file_path = $target_upload_dir['basedir'] . '/' . $attached_file;
        
        $this->log("Target file path", array(
            'target_file_path' => $target_file_path,
            'target_basedir' => $target_upload_dir['basedir']
        ));
        
        // Create directory if needed
        $target_dir = dirname($target_file_path);
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Copy file
        if (!copy($file_path, $target_file_path)) {
            restore_current_blog();
            $this->log("Failed to copy file", array(
                'source' => $file_path,
                'target' => $target_file_path
            ), 'error');
            return new WP_Error('copy_failed', __('Failed to copy image file.', 'wpc-multisite-products-copier'));
        }
        
        // Get the file type
        $filetype = wp_check_filetype(basename($target_file_path), null);
        
        // Create attachment post
        $attachment_data = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => $attachment->post_title,
            'post_content' => $attachment->post_content,
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'guid' => $target_upload_dir['url'] . '/' . $attached_file
        );
        
        $new_attachment_id = wp_insert_attachment($attachment_data, $target_file_path);
        
        if (!is_wp_error($new_attachment_id)) {
            // Generate attachment metadata (this creates thumbnails)
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($new_attachment_id, $target_file_path);
            wp_update_attachment_metadata($new_attachment_id, $attach_data);
            
            // Update the _wp_attached_file meta
            update_post_meta($new_attachment_id, '_wp_attached_file', $attached_file);
            
            // Copy alt text
            switch_to_blog($source_blog_id);
            $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
            switch_to_blog($target_blog_id);
            
            if ($alt_text) {
                update_post_meta($new_attachment_id, '_wp_attachment_image_alt', $alt_text);
            }
            
            $this->log("Successfully created attachment", array(
                'new_attachment_id' => $new_attachment_id,
                'filename' => $attached_file
            ));
        } else {
            $this->log("Failed to create attachment", array(
                'error' => $new_attachment_id->get_error_message()
            ), 'error');
        }
        
        restore_current_blog();
        
        return $new_attachment_id;
    }

    /**
     * Handle product categories from source to target
     *
     * @param int $source_product_id Source product ID
     * @param int $target_blog_id Target blog ID
     * @param int $source_blog_id Source blog ID
     * @return array Array of target category IDs
     */
    private function handle_product_categories($source_product_id, $target_blog_id, $source_blog_id) {
        $this->log("Starting category handling", array(
            'source_product_id' => $source_product_id,
            'source_blog_id' => $source_blog_id,
            'target_blog_id' => $target_blog_id
        ));
        
        // Get source categories
        switch_to_blog($source_blog_id);
        $source_categories = wp_get_post_terms($source_product_id, 'product_cat', array('fields' => 'all'));
        restore_current_blog();
        
        if (empty($source_categories) || is_wp_error($source_categories)) {
            $this->log("No categories found on source product", array(
                'source_product_id' => $source_product_id,
                'error' => is_wp_error($source_categories) ? $source_categories->get_error_message() : 'Empty categories'
            ));
            return array();
        }
        
        $this->log("Found source categories", array(
            'count' => count($source_categories),
            'categories' => array_map(function($cat) { 
                return array('id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug); 
            }, $source_categories)
        ));
        
        // Switch to target blog to find/create matching categories
        switch_to_blog($target_blog_id);
        
        $target_category_ids = array();
        
        foreach ($source_categories as $source_cat) {
            // Try to find existing category by slug
            $target_cat = get_term_by('slug', $source_cat->slug, 'product_cat');
            
            if ($target_cat) {
                // Category exists, use it
                $target_category_ids[] = (int) $target_cat->term_id;
                $this->log("Found existing category on target", array(
                    'source_slug' => $source_cat->slug,
                    'target_id' => $target_cat->term_id,
                    'target_name' => $target_cat->name
                ));
            } else {
                // Category doesn't exist, create it
                $new_cat_args = array(
                    'slug' => $source_cat->slug,
                    'description' => $source_cat->description
                );
                
                // If source category has parent, try to find parent on target
                if ($source_cat->parent) {
                    // Get parent slug from source
                    switch_to_blog($source_blog_id);
                    $source_parent = get_term($source_cat->parent, 'product_cat');
                    switch_to_blog($target_blog_id);
                    
                    if ($source_parent && !is_wp_error($source_parent)) {
                        $target_parent = get_term_by('slug', $source_parent->slug, 'product_cat');
                        if ($target_parent) {
                            $new_cat_args['parent'] = $target_parent->term_id;
                        }
                    }
                }
                
                $new_cat = wp_insert_term($source_cat->name, 'product_cat', $new_cat_args);
                
                if (!is_wp_error($new_cat)) {
                    $target_category_ids[] = (int) $new_cat['term_id'];
                    $this->log("Created new category on target", array(
                        'name' => $source_cat->name,
                        'slug' => $source_cat->slug,
                        'new_id' => $new_cat['term_id']
                    ));
                } else {
                    $this->log("Failed to create category", array(
                        'name' => $source_cat->name,
                        'error' => $new_cat->get_error_message()
                    ), 'error');
                }
            }
        }
        
        restore_current_blog();
        
        $this->log("Category handling completed", array(
            'target_category_ids' => $target_category_ids,
            'count' => count($target_category_ids)
        ));
        
        return $target_category_ids;
    }

    /**
     * Update variations for a product including stock management
     *
     * @param int $source_product_id Source product ID
     * @param int $target_product_id Target product ID
     * @param int $source_blog_id Source blog ID
     * @param int $target_blog_id Target blog ID
     * @return bool Success status
     */
    private function update_product_variations($source_product_id, $target_product_id, $source_blog_id, $target_blog_id) {
        $this->log("Starting variation update", array(
            'source_product_id' => $source_product_id,
            'target_product_id' => $target_product_id,
            'source_blog_id' => $source_blog_id,
            'target_blog_id' => $target_blog_id
        ));
        
        // Get source variations
        switch_to_blog($source_blog_id);
        $source_product = wc_get_product($source_product_id);
        if (!$source_product || !$source_product->is_type('variable')) {
            restore_current_blog();
            $this->log("Invalid source product for variation update", array(
                'product_id' => $source_product_id
            ), 'error');
            return false;
        }
        
        $source_variations = $source_product->get_children();
        restore_current_blog();
        
        // Get target variations
        switch_to_blog($target_blog_id);
        $target_product = wc_get_product($target_product_id);
        if (!$target_product || !$target_product->is_type('variable')) {
            restore_current_blog();
            $this->log("Invalid target product for variation update", array(
                'product_id' => $target_product_id
            ), 'error');
            return false;
        }
        
        $target_variations = $target_product->get_children();
        restore_current_blog();
        
        $this->log("Found variations", array(
            'source_count' => count($source_variations),
            'target_count' => count($target_variations)
        ));
        
        // Build a map of target variations by their attributes
        $target_variation_map = array();
        foreach ($target_variations as $target_var_id) {
            switch_to_blog($target_blog_id);
            $target_var = wc_get_product($target_var_id);
            if ($target_var) {
                $attributes = $target_var->get_attributes();
                $attr_key = $this->generate_variation_key($attributes);
                $target_variation_map[$attr_key] = $target_var_id;
            }
            restore_current_blog();
        }
        
        // Update each source variation
        foreach ($source_variations as $source_var_id) {
            switch_to_blog($source_blog_id);
            $source_var = wc_get_product($source_var_id);
            if (!$source_var) {
                restore_current_blog();
                continue;
            }
            
            // Get source variation data including stock
            $source_attributes = $source_var->get_attributes();
            $attr_key = $this->generate_variation_key($source_attributes);
            
            // Stock related data
            $manage_stock = $source_var->get_manage_stock();
            $stock_status = $source_var->get_stock_status();
            $stock_quantity = $source_var->get_stock_quantity();
            $backorders = $source_var->get_backorders();
            $low_stock_amount = get_post_meta($source_var_id, '_low_stock_amount', true);
            
            // Also get prices while we're here
            $regular_price = $source_var->get_regular_price();
            $sale_price = $source_var->get_sale_price();
            
            $this->log("Source variation stock data", array(
                'variation_id' => $source_var_id,
                'attributes' => $source_attributes,
                'manage_stock' => $manage_stock,
                'stock_status' => $stock_status,
                'stock_quantity' => $stock_quantity,
                'backorders' => $backorders,
                'low_stock_amount' => $low_stock_amount
            ));
            
            restore_current_blog();
            
            // Find matching target variation
            if (isset($target_variation_map[$attr_key])) {
                $target_var_id = $target_variation_map[$attr_key];
                
                switch_to_blog($target_blog_id);
                $target_var = wc_get_product($target_var_id);
                
                if ($target_var) {
                    // Update stock management settings
                    $target_var->set_manage_stock($manage_stock);
                    $target_var->set_stock_status($stock_status);
                    
                    if ($manage_stock) {
                        $target_var->set_stock_quantity($stock_quantity);
                        $target_var->set_backorders($backorders);
                        
                        if ($low_stock_amount !== '') {
                            update_post_meta($target_var_id, '_low_stock_amount', $low_stock_amount);
                        }
                    }
                    
                    // Also update prices
                    if ($regular_price !== '') {
                        $target_var->set_regular_price($regular_price);
                    }
                    if ($sale_price !== '') {
                        $target_var->set_sale_price($sale_price);
                    }
                    
                    // Save the variation
                    $target_var->save();
                    
                    $this->log("Updated target variation", array(
                        'target_variation_id' => $target_var_id,
                        'manage_stock' => $manage_stock,
                        'stock_status' => $stock_status,
                        'stock_quantity' => $stock_quantity
                    ));
                }
                
                restore_current_blog();
            } else {
                $this->log("No matching target variation found for attributes", array(
                    'attributes' => $source_attributes
                ), 'warning');
            }
        }
        
        return true;
    }
    
    /**
     * Generate a unique key for variation attributes
     *
     * @param array $attributes Variation attributes
     * @return string Unique key
     */
    private function generate_variation_key($attributes) {
        ksort($attributes);
        $key_parts = array();
        foreach ($attributes as $name => $value) {
            $key_parts[] = $name . ':' . $value;
        }
        return implode('|', $key_parts);
    }

    /**
     * Save product attributes in proper WooCommerce format
     *
     * @param int $product_id Product ID
     * @param array $attributes Array of WC_Product_Attribute objects
     */
    private function save_product_attributes_properly($product_id, $attributes) {
        // Save product attributes
        
        $product_attributes = array();
        
        foreach ($attributes as $attribute) {
            $attribute_name = $attribute->get_name();
            
            // Check if this is a taxonomy attribute by checking the name format
            $is_taxonomy = (taxonomy_exists($attribute_name) && substr($attribute_name, 0, 3) === 'pa_');
            
            if ($is_taxonomy) {
                // For taxonomy attributes, we need to ensure proper format
                $attribute_data = array(
                    'name' => $attribute_name,
                    'value' => '',  // Taxonomy attributes should have empty value
                    'position' => $attribute->get_position(),
                    'is_visible' => $attribute->get_visible() ? 1 : 0,
                    'is_variation' => $attribute->get_variation() ? 1 : 0,
                    'is_taxonomy' => 1
                );
                
                $product_attributes[$attribute_name] = $attribute_data;
                
                // Saved taxonomy attribute
            } else {
                // For custom attributes
                $values = $attribute->get_options();
                $attribute_data = array(
                    'name' => $attribute->get_name(),
                    'value' => is_array($values) ? implode(' | ', $values) : $values,
                    'position' => $attribute->get_position(),
                    'is_visible' => $attribute->get_visible() ? 1 : 0,
                    'is_variation' => $attribute->get_variation() ? 1 : 0,
                    'is_taxonomy' => 0
                );
                
                $sanitized_name = sanitize_title($attribute->get_name());
                $product_attributes[$sanitized_name] = $attribute_data;
                
                // Saved custom attribute
            }
        }
        
        // Update the product attributes meta
        update_post_meta($product_id, '_product_attributes', $product_attributes);
        
        // Product attributes saved
    }
    
    /**
     * Sync variable product attributes after variations are created
     *
     * @param int $product_id Product ID
     */
    private function sync_variable_product_attributes($product_id) {
        // Sync variable product attributes
        
        // Get the product
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            return;
        }
        
        // Get all variations
        $variations = $product->get_children();
        
        // Build array of used attributes from variations
        $used_attributes = array();
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            
            $variation_attributes = $variation->get_attributes();
            foreach ($variation_attributes as $attribute_name => $attribute_value) {
                if (!isset($used_attributes[$attribute_name])) {
                    $used_attributes[$attribute_name] = array();
                }
                if (!empty($attribute_value)) {
                    $used_attributes[$attribute_name][] = $attribute_value;
                }
            }
        }
        
        // Update product meta for variable attributes
        if (!empty($used_attributes)) {
            $product_attributes = $product->get_attributes();
            
            foreach ($product_attributes as $attribute) {
                $attribute_name = $attribute->get_name();
                
                if ($attribute->get_variation()) {
                    // This is a variation attribute
                    if (isset($used_attributes[$attribute_name])) {
                        // Get unique values
                        $values = array_unique($used_attributes[$attribute_name]);
                        
                        // Sync variation attribute values
                        
                        // Update default attributes meta
                        $default_attributes = $product->get_default_attributes();
                        if (empty($default_attributes[$attribute_name]) && !empty($values)) {
                            // Set first value as default if none set
                            $default_attributes[$attribute_name] = reset($values);
                            $product->set_default_attributes($default_attributes);
                        }
                    }
                }
            }
            
            // Save product
            $product->save();
        }
        
        // Also run WooCommerce's sync
        WC_Product_Variable::sync($product_id);
        
        // Variable product attributes synced
    }
    
    /**
     * Update product attribute lookup table
     *
     * @param int $product_id Product ID
     */
    private function update_product_attribute_lookup_table($product_id) {
        // Force regeneration of lookup table entries
        if (function_exists('wc_get_container')) {
            try {
                $data_store = wc_get_container()->get(\Automattic\WooCommerce\Internal\ProductAttributesLookup\DataRegenerator::class);
                if ($data_store && method_exists($data_store, 'regenerate_entries_for_product')) {
                    $data_store->regenerate_entries_for_product($product_id);
                }
            } catch (Exception $e) {
                // Failed to update attribute lookup table
            }
        }
        
        // Also clear any transients
        delete_transient('wc_attribute_taxonomies');
        WC_Cache_Helper::invalidate_cache_group('product_attributes');
    }

    /**
     * Handle Woodmart video gallery meta for product
     *
     * @param int $source_product_id Source product ID
     * @param int $target_product_id Target product ID
     * @param array $new_gallery_ids New gallery image IDs on target blog
     * @param int $source_blog_id Source blog ID
     * @param int $target_blog_id Target blog ID
     * @return void
     */
    private function handle_woodmart_video_gallery($source_product_id, $target_product_id, $new_gallery_ids, $source_blog_id, $target_blog_id) {
        // Only proceed if we have at least 2 gallery images
        if (empty($new_gallery_ids) || count($new_gallery_ids) < 2) {
            $this->log("Skipping Woodmart video gallery - insufficient gallery images", array(
                'gallery_count' => count($new_gallery_ids)
            ));
            return;
        }
        
        // Switch to source blog to get the video gallery meta
        switch_to_blog($source_blog_id);
        
        // Get the woodmart_wc_video_gallery meta from source
        $source_video_gallery = get_post_meta($source_product_id, 'woodmart_wc_video_gallery', true);
        
        // Switch back to target blog
        switch_to_blog($target_blog_id);
        
        // Skip if no video gallery data exists
        if (empty($source_video_gallery) || !is_array($source_video_gallery)) {
            $this->log("No Woodmart video gallery data found on source product");
            return;
        }
        
        $this->log("Processing Woodmart video gallery", array(
            'source_product_id' => $source_product_id,
            'target_product_id' => $target_product_id,
            'source_video_gallery_count' => count($source_video_gallery),
            'source_video_gallery_keys' => array_keys($source_video_gallery),
            'source_video_gallery_full' => $source_video_gallery
        ));
        
        // Get the second image ID from the new gallery
        $second_gallery_image_id = $new_gallery_ids[1]; // Index 1 = second image
        
        // Build new video gallery array with second image as key
        $target_video_gallery = array();
        $found_video = false;
        
        // Search through all entries to find one with a non-empty upload_video_url
        foreach ($source_video_gallery as $source_image_id => $video_data) {
            // Validate that video_data is an array
            if (!is_array($video_data)) {
                continue;
            }
            
            // Check if this entry has a non-empty upload_video_url
            if (!empty($video_data['upload_video_url'])) {
                // Found a video entry with URL - use second gallery image ID as key, preserve all values exactly
                $target_video_gallery[$second_gallery_image_id] = $video_data;
                $found_video = true;
                
                $this->log("Found and copied video gallery entry with URL", array(
                    'source_image_id' => $source_image_id,
                    'target_image_id' => $second_gallery_image_id,
                    'video_type' => isset($video_data['video_type']) ? $video_data['video_type'] : 'unknown',
                    'upload_video_url' => $video_data['upload_video_url'],
                    'upload_video_id' => isset($video_data['upload_video_id']) ? $video_data['upload_video_id'] : ''
                ));
                
                // Found the video data, stop searching
                break;
            } else {
                $this->log("Skipped video gallery entry without URL", array(
                    'source_image_id' => $source_image_id,
                    'has_url' => false
                ));
            }
        }
        
        if (!$found_video) {
            $this->log("No video gallery entry with upload_video_url found");
        }
        
        // Save the video gallery meta to target product
        if (!empty($target_video_gallery)) {
            update_post_meta($target_product_id, 'woodmart_wc_video_gallery', $target_video_gallery);
            
            $this->log("Saved Woodmart video gallery to target product", array(
                'target_product_id' => $target_product_id,
                'video_gallery_count' => count($target_video_gallery)
            ));
        }
    }

    /**
     * Update product on target blog
     *
     * @param int $source_product_id Source product ID
     * @param int $target_blog_id Target blog ID
     * @param int $target_product_id Target product ID
     * @return bool|WP_Error Success or error
     */
    public function update_product_on_blog($source_product_id, $target_blog_id, $target_product_id) {
        try {
            // Store source blog ID - should always be 5
            $source_blog_id = $this->source_blog_id;
            
            // Ensure we're on the source blog to get correct product data
            if (get_current_blog_id() !== $source_blog_id) {
                switch_to_blog($source_blog_id);
            }
            
            // Get source product
            $source_product = wc_get_product($source_product_id);
            if (!$source_product || !$source_product->is_type('variable')) {
                restore_current_blog();
                return new WP_Error('invalid_product', __('Source product must be a variable product.', 'wpc-multisite-products-copier'));
            }
            
            $this->log("Starting product update", array(
                'source_product_id' => $source_product_id,
                'target_blog_id' => $target_blog_id,
                'target_product_id' => $target_product_id,
                'current_blog_id' => get_current_blog_id(),
                'source_blog_id' => $source_blog_id
            ));
            
            // Get source product data
            $source_slug = $source_product->get_slug();
            
            // If slug is empty, generate one from the product name
            if (empty($source_slug)) {
                $source_post = get_post($source_product_id);
                if ($source_post && !empty($source_post->post_name)) {
                    $source_slug = $source_post->post_name;
                } else {
                    // Generate slug from product name
                    $source_slug = sanitize_title($source_product->get_name());
                }
            }
            
            $this->log("Source product data", array(
                'slug' => $source_slug,
                'name' => $source_product->get_name(),
                'generated_slug' => empty($source_product->get_slug()),
                'current_blog_id' => get_current_blog_id()
            ));
            
            // Get featured image ID
            $thumbnail_id = get_post_thumbnail_id($source_product_id);
            if (!$thumbnail_id) {
                // Try getting from meta directly
                $thumbnail_id = get_post_meta($source_product_id, '_thumbnail_id', true);
            }
            
            $this->log("Source featured image", array(
                'thumbnail_id' => $thumbnail_id,
                'has_thumbnail' => !empty($thumbnail_id)
            ));
            
            // Get gallery image IDs
            $gallery_ids = $source_product->get_gallery_image_ids();
            if (empty($gallery_ids)) {
                // Try getting from meta directly
                $gallery_ids_meta = get_post_meta($source_product_id, '_product_image_gallery', true);
                if ($gallery_ids_meta) {
                    $gallery_ids = array_filter(explode(',', $gallery_ids_meta));
                }
            }
            
            $this->log("Source gallery images", array(
                'gallery_ids' => $gallery_ids,
                'count' => count($gallery_ids)
            ));
            
            // Switch to target blog
            switch_to_blog($target_blog_id);
            
            // Verify target product exists
            $target_product = wc_get_product($target_product_id);
            if (!$target_product) {
                restore_current_blog();
                return new WP_Error('target_not_found', __('Target product not found.', 'wpc-multisite-products-copier'));
            }
            
            // Log current blog context
            $this->log("Blog context check", array(
                'current_blog_id' => get_current_blog_id(),
                'target_blog_id' => $target_blog_id,
                'should_match' => get_current_blog_id() === $target_blog_id
            ));
            
            // Update product slug only if we have a valid slug
            if (!empty($source_slug)) {
                $update_result = wp_update_post(array(
                    'ID' => $target_product_id,
                    'post_name' => $source_slug
                ), true);
            } else {
                $update_result = true; // Skip slug update if source has no slug
                $this->log("Skipping slug update - source has no slug");
            }
            
            if (is_wp_error($update_result)) {
                restore_current_blog();
                $this->log("Failed to update product slug", array(
                    'error' => $update_result->get_error_message()
                ), 'error');
                return $update_result;
            }
            
            $this->log("Updated product slug", array(
                'target_product_id' => $target_product_id,
                'new_slug' => $source_slug
            ));
            
            // Delete existing images to avoid duplicates
            // Get current images on target
            $current_thumbnail_id = get_post_thumbnail_id($target_product_id);
            $current_gallery_ids = $target_product->get_gallery_image_ids();
            
            // Store image IDs to potentially delete (if not reused)
            $images_to_check = array();
            if ($current_thumbnail_id) {
                $images_to_check[] = $current_thumbnail_id;
            }
            if (!empty($current_gallery_ids)) {
                $images_to_check = array_merge($images_to_check, $current_gallery_ids);
            }
            
            // Track new images for verification
            $new_thumbnail_id = 0;
            $new_gallery_ids = array();
            
            // Update featured image
            if ($thumbnail_id) {
                $this->log("Copying featured image", array(
                    'source_thumbnail_id' => $thumbnail_id,
                    'current_thumbnail_id' => $current_thumbnail_id
                ));
                
                $new_thumbnail_id = $this->copy_image_to_blog($thumbnail_id, $target_blog_id, $source_blog_id);
                if ($new_thumbnail_id && !is_wp_error($new_thumbnail_id)) {
                    // Log before operations
                    $this->log("Starting thumbnail update", array(
                        'target_product_id' => $target_product_id,
                        'new_thumbnail_id' => $new_thumbnail_id,
                        'old_thumbnail_id' => $current_thumbnail_id,
                        'current_blog_id' => get_current_blog_id(),
                        'target_blog_id' => $target_blog_id
                    ));
                    
                    // Remove old thumbnail
                    if ($current_thumbnail_id) {
                        delete_post_thumbnail($target_product_id);
                        // Add to images to check for cleanup later
                        $images_to_check[] = $current_thumbnail_id;
                    }
                    
                    // CRITICAL: Ensure we're on target blog before setting thumbnail
                    if (get_current_blog_id() !== $target_blog_id) {
                        switch_to_blog($target_blog_id);
                        $this->log("Switched to target blog for thumbnail operations");
                    }
                    
                    // Set new thumbnail using multiple methods to ensure it's set
                    $set_result = set_post_thumbnail($target_product_id, $new_thumbnail_id);
                    $meta_result = update_post_meta($target_product_id, '_thumbnail_id', $new_thumbnail_id);
                    
                    // Force clean the cache
                    clean_post_cache($target_product_id);
                    
                    // Verify immediately
                    $verify_thumb = get_post_thumbnail_id($target_product_id);
                    $verify_meta = get_post_meta($target_product_id, '_thumbnail_id', true);
                    
                    $this->log("Thumbnail set results", array(
                        'set_post_thumbnail_result' => $set_result,
                        'update_post_meta_result' => $meta_result,
                        'verify_get_post_thumbnail_id' => $verify_thumb,
                        'verify_get_post_meta' => $verify_meta,
                        'expected_id' => $new_thumbnail_id,
                        'current_blog_id' => get_current_blog_id()
                    ));
                    
                    // If verification failed, force update directly
                    if ($verify_thumb != $new_thumbnail_id) {
                        global $wpdb;
                        
                        // Delete any existing thumbnail meta
                        $wpdb->delete(
                            $wpdb->postmeta,
                            array(
                                'post_id' => $target_product_id,
                                'meta_key' => '_thumbnail_id'
                            )
                        );
                        
                        // Insert new thumbnail meta
                        $wpdb->insert(
                            $wpdb->postmeta,
                            array(
                                'post_id' => $target_product_id,
                                'meta_key' => '_thumbnail_id',
                                'meta_value' => $new_thumbnail_id
                            )
                        );
                        
                        clean_post_cache($target_product_id);
                        
                        $this->log("Force updated thumbnail via direct DB", array(
                            'product_id' => $target_product_id,
                            'thumbnail_id' => $new_thumbnail_id,
                            'verify_after_force' => get_post_thumbnail_id($target_product_id)
                        ));
                    }
                    
                    // Also set it on the product object
                    $target_product->set_image_id($new_thumbnail_id);
                    
                    // Switch back to source blog if we switched
                    if (get_current_blog_id() === $target_blog_id && $target_blog_id !== $source_blog_id) {
                        switch_to_blog($source_blog_id);
                        $this->log("Switched back to source blog after thumbnail");
                    }
                    
                    $this->log("Updated featured image completed", array(
                        'product_id' => $target_product_id,
                        'new_thumbnail_id' => $new_thumbnail_id,
                        'old_thumbnail_id' => $current_thumbnail_id
                    ));
                } else {
                    $this->log("Failed to copy featured image", array(
                        'error' => is_wp_error($new_thumbnail_id) ? $new_thumbnail_id->get_error_message() : 'Unknown error'
                    ), 'error');
                    $new_thumbnail_id = 0;
                }
            } else {
                // Remove featured image if source doesn't have one
                if ($current_thumbnail_id) {
                    delete_post_thumbnail($target_product_id);
                    $target_product->set_image_id(0);
                    $this->log("Removed featured image as source has none");
                }
            }
            
            // Update gallery images
            if (!empty($gallery_ids)) {
                $this->log("Copying gallery images", array(
                    'source_gallery_ids' => $gallery_ids,
                    'current_gallery_ids' => $current_gallery_ids
                ));
                
                // Reset gallery array (already declared above)
                $new_gallery_ids = array();
                foreach ($gallery_ids as $gallery_id) {
                    // Pass product ID and skip_if_thumbnail flag to prevent deletion of current thumbnail
                    $new_gallery_id = $this->copy_image_to_blog($gallery_id, $target_blog_id, $source_blog_id, $target_product_id, true);
                    if ($new_gallery_id && !is_wp_error($new_gallery_id)) {
                        $new_gallery_ids[] = $new_gallery_id;
                        $this->log("Copied gallery image", array(
                            'source_id' => $gallery_id,
                            'target_id' => $new_gallery_id
                        ));
                    } else {
                        $this->log("Failed to copy gallery image", array(
                            'gallery_id' => $gallery_id,
                            'error' => is_wp_error($new_gallery_id) ? $new_gallery_id->get_error_message() : 'Unknown error'
                        ), 'error');
                    }
                }
                
                // Set new gallery
                if (!empty($new_gallery_ids)) {
                    $target_product->set_gallery_image_ids($new_gallery_ids);
                    $this->log("Updated gallery images", array(
                        'product_id' => $target_product_id,
                        'new_gallery_ids' => $new_gallery_ids,
                        'count' => count($new_gallery_ids)
                    ));
                }
            } else {
                // Clear gallery if source has no gallery images
                $target_product->set_gallery_image_ids(array());
                $this->log("Cleared gallery images as source has none");
            }
            
            // CRITICAL: Ensure we're on the target blog before saving
            if (get_current_blog_id() !== $target_blog_id) {
                $this->log("SWITCHING TO TARGET BLOG BEFORE SAVE", array(
                    'current_blog_id' => get_current_blog_id(),
                    'target_blog_id' => $target_blog_id
                ));
                switch_to_blog($target_blog_id);
                
                // Re-get the product object after switching blogs
                $target_product = wc_get_product($target_product_id);
                if ($target_product) {
                    // Re-set the images on the correct blog context
                    if ($new_thumbnail_id) {
                        $target_product->set_image_id($new_thumbnail_id);
                        
                        // DOUBLE CHECK: Force set thumbnail again on correct blog
                        set_post_thumbnail($target_product_id, $new_thumbnail_id);
                        update_post_meta($target_product_id, '_thumbnail_id', $new_thumbnail_id);
                        
                        $this->log("Re-set thumbnail on target blog before save", array(
                            'product_id' => $target_product_id,
                            'thumbnail_id' => $new_thumbnail_id,
                            'verify' => get_post_thumbnail_id($target_product_id)
                        ));
                    }
                    if (!empty($new_gallery_ids)) {
                        $target_product->set_gallery_image_ids($new_gallery_ids);
                    }
                }
            }
            
            // Handle product categories
            $target_category_ids = $this->handle_product_categories($source_product_id, $target_blog_id, $source_blog_id);
            if (!empty($target_category_ids)) {
                $target_product->set_category_ids($target_category_ids);
                $this->log("Set categories on updated product", array(
                    'product_id' => $target_product_id,
                    'category_ids' => $target_category_ids
                ));
            }
            
            // Save the product with all image changes
            $save_result = $target_product->save();
            $this->log("Product save result", array(
                'save_result' => $save_result,
                'product_id' => $target_product_id,
                'image_id' => $target_product->get_image_id(),
                'gallery_ids' => $target_product->get_gallery_image_ids(),
                'current_blog_id' => get_current_blog_id()
            ));
            
            // Force update meta directly as a fallback
            if (!empty($new_gallery_ids)) {
                $gallery_string = implode(',', $new_gallery_ids);
                update_post_meta($target_product_id, '_product_image_gallery', $gallery_string);
                $this->log("Force updated gallery meta", array(
                    'gallery_string' => $gallery_string,
                    'current_blog_id' => get_current_blog_id()
                ));
            }
            
            // Force update thumbnail as a fallback if we have one
            if (isset($new_thumbnail_id) && $new_thumbnail_id) {
                update_post_meta($target_product_id, '_thumbnail_id', $new_thumbnail_id);
                set_post_thumbnail($target_product_id, $new_thumbnail_id);
                
                // Clear all caches
                clean_post_cache($target_product_id);
                wp_cache_delete($target_product_id, 'post_meta');
                
                $this->log("Force updated thumbnail after save", array(
                    'product_id' => $target_product_id,
                    'thumbnail_id' => $new_thumbnail_id
                ));
            }
            
            // Verify the updates
            $verify_thumbnail = get_post_thumbnail_id($target_product_id);
            $verify_gallery = get_post_meta($target_product_id, '_product_image_gallery', true);
            $verify_meta = get_post_meta($target_product_id, '_thumbnail_id', true);
            
            $this->log("Verification after save", array(
                'thumbnail_id' => $verify_thumbnail,
                'thumbnail_meta' => $verify_meta,
                'gallery_meta' => $verify_gallery,
                'expected_thumbnail' => isset($new_thumbnail_id) ? $new_thumbnail_id : 'none',
                'expected_gallery' => !empty($new_gallery_ids) ? implode(',', $new_gallery_ids) : 'none',
                'current_blog_id' => get_current_blog_id()
            ));
            
            // Final attempt if thumbnail still not set
            if (isset($new_thumbnail_id) && $new_thumbnail_id && ($verify_thumbnail != $new_thumbnail_id || $verify_meta != $new_thumbnail_id)) {
                global $wpdb;
                
                $this->log("FINAL THUMBNAIL FIX ATTEMPT", array(
                    'expected' => $new_thumbnail_id,
                    'actual_func' => $verify_thumbnail,
                    'actual_meta' => $verify_meta
                ));
                
                // Direct SQL update
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_thumbnail_id'",
                    $target_product_id
                ));
                
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, '_thumbnail_id', %s)",
                    $target_product_id,
                    $new_thumbnail_id
                ));
                
                clean_post_cache($target_product_id);
                wp_cache_delete($target_product_id, 'post_meta');
                wp_cache_delete($target_product_id . '_thumbnail_id', 'post_meta');
                
                // Final verification
                $final_verify = get_post_thumbnail_id($target_product_id);
                $this->log("Final verification after direct SQL", array(
                    'thumbnail_id' => $final_verify,
                    'expected' => $new_thumbnail_id,
                    'success' => $final_verify == $new_thumbnail_id
                ));
            }
            
            // Handle Woodmart video gallery meta if gallery images were updated
            if (!empty($new_gallery_ids)) {
                $this->handle_woodmart_video_gallery(
                    $source_product_id,
                    $target_product_id,
                    $new_gallery_ids,
                    $source_blog_id,
                    $target_blog_id
                );
            }
            
            // Update variations including stock management
            $this->update_product_variations(
                $source_product_id,
                $target_product_id,
                $source_blog_id,
                $target_blog_id
            );
            
            // Clean up old images that are no longer used
            // Only delete if they're not used by other products
            if (!empty($images_to_check)) {
                foreach ($images_to_check as $old_image_id) {
                    // Check if this image is used by any other products
                    $usage_check = new WP_Query(array(
                        'post_type' => 'product',
                        'post_status' => 'any',
                        'posts_per_page' => 1,
                        'post__not_in' => array($target_product_id),
                        'meta_query' => array(
                            'relation' => 'OR',
                            array(
                                'key' => '_thumbnail_id',
                                'value' => $old_image_id
                            ),
                            array(
                                'key' => '_product_image_gallery',
                                'value' => $old_image_id,
                                'compare' => 'LIKE'
                            )
                        )
                    ));
                    
                    if (!$usage_check->have_posts()) {
                        // Image not used elsewhere, can be deleted
                        wp_delete_attachment($old_image_id, true);
                        $this->log("Deleted unused old image", array('image_id' => $old_image_id));
                    }
                }
            }
            
            // Switch back to source blog
            restore_current_blog();
            
            // Update the last synced timestamp on source product
            update_post_meta($source_product_id, '_wpc_last_sync_' . $target_blog_id, current_time('timestamp'));
            
            // Update last sync time on target product
            switch_to_blog($target_blog_id);
            update_post_meta($target_product_id, '_wpc_last_sync', time());
            restore_current_blog();
            
            $this->log("Product update completed successfully", array(
                'source_product_id' => $source_product_id,
                'target_product_id' => $target_product_id,
                'target_blog_id' => $target_blog_id
            ));
            
            // Trigger action for logging
            do_action('wpc_after_product_update', $source_product_id, $target_product_id, $target_blog_id);
            
            return true;
            
        } catch (Exception $e) {
            if (ms_is_switched()) {
                restore_current_blog();
            }
            $this->log("Product update failed with exception", array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ), 'error');
            return new WP_Error('update_failed', $e->getMessage());
        }
    }

    /**
     * Initialize logging
     */
    private function init_logging() {
        if ($this->debug_enabled) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wpc-mpc-logs';
            
            // Create log directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            // Set log file path with date
            $this->log_file = $log_dir . '/debug-' . date('Y-m-d') . '.log';
        }
    }

    /**
     * Log debug message
     *
     * @param string $message The message to log
     * @param mixed $data Optional data to log
     * @param string $level Log level (info, warning, error)
     */
    private function log($message, $data = null, $level = 'info') {
        if (!$this->debug_enabled || empty($this->log_file)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $blog_id = get_current_blog_id();
        $log_entry = "[{$timestamp}] [{$level}] [Blog {$blog_id}] {$message}";
        
        if ($data !== null) {
            $log_entry .= "\nData: " . print_r($data, true);
        }
        
        $log_entry .= "\n" . str_repeat('-', 80) . "\n";
        
        // Write to log file
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wpc-multisite-products-copier',
            false,
            dirname(plugin_basename(WPC_MPC_PLUGIN_BASENAME)) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Check if multisite is enabled
        if (!is_multisite()) {
            wp_die(
                esc_html__('This plugin requires WordPress Multisite to be enabled.', 'wpc-multisite-products-copier'),
                esc_html__('Plugin Activation Error', 'wpc-multisite-products-copier'),
                array('back_link' => true)
            );
        }

        // Set default options
        add_option('wpc_mpc_version', WPC_MPC_VERSION);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clean up any scheduled tasks
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}