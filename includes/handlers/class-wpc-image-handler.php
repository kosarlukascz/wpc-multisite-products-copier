<?php
/**
 * Image Handler Class
 *
 * @package WPC_Multisite_Products_Copier
 * @since 2.0.0
 */

namespace WPC\Handlers;

use WPC\Utilities\WPC_Blog_Switcher;
use WPC\Utilities\WPC_Logger;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles all image copying operations
 */
class WPC_Image_Handler {
    
    use WPC_Blog_Switcher;
    
    /**
     * Logger instance
     *
     * @var WPC_Logger
     */
    private WPC_Logger $logger;
    
    /**
     * Constructor
     *
     * @param WPC_Logger $logger Logger instance
     */
    public function __construct(WPC_Logger $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Copy image attachment to target blog
     *
     * @param int $attachment_id Source attachment ID
     * @param int $target_blog_id Target blog ID
     * @param int $source_blog_id Source blog ID
     * @param bool $force_update Force update even if exists
     * @return int|false New attachment ID or false on error
     */
    public function copy_image_to_blog(
        int $attachment_id, 
        int $target_blog_id, 
        int $source_blog_id,
        bool $force_update = false
    ) {
        $this->logger->info("Starting image copy", [
            'attachment_id' => $attachment_id,
            'source_blog_id' => $source_blog_id,
            'target_blog_id' => $target_blog_id,
            'force_update' => $force_update
        ]);
        
        // Get attachment data from source blog
        $attachment_data = $this->get_from_blog($source_blog_id, function() use ($attachment_id) {
            $attachment = get_post($attachment_id);
            if (!$attachment || $attachment->post_type !== 'attachment') {
                return null;
            }
            
            return [
                'post' => $attachment,
                'file_path' => get_attached_file($attachment_id),
                'metadata' => wp_get_attachment_metadata($attachment_id),
                'attached_file' => get_post_meta($attachment_id, '_wp_attached_file', true),
                'alt_text' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'url' => wp_get_attachment_url($attachment_id)
            ];
        });
        
        if (!$attachment_data) {
            $this->logger->error("Invalid attachment", ['attachment_id' => $attachment_id]);
            return false;
        }
        
        // Check if file exists
        if (!file_exists($attachment_data['file_path'])) {
            $this->logger->error("Source file does not exist", ['file_path' => $attachment_data['file_path']]);
            return false;
        }
        
        // Copy to target blog
        return $this->set_on_blog($target_blog_id, function() use ($attachment_data, $force_update) {
            // Check if attachment already exists
            if (!$force_update) {
                $existing_id = $this->find_existing_attachment($attachment_data['attached_file']);
                if ($existing_id) {
                    $this->logger->info("Attachment already exists", [
                        'existing_id' => $existing_id,
                        'filename' => $attachment_data['attached_file']
                    ]);
                    return $existing_id;
                }
            } else {
                // Force update - delete existing
                $this->delete_existing_attachment($attachment_data['attached_file']);
            }
            
            // Copy the file
            $new_attachment_id = $this->create_attachment($attachment_data);
            
            if ($new_attachment_id && $attachment_data['alt_text']) {
                update_post_meta($new_attachment_id, '_wp_attachment_image_alt', $attachment_data['alt_text']);
            }
            
            return $new_attachment_id;
        });
    }
    
    /**
     * Copy multiple images to target blog
     *
     * @param array $attachment_ids Array of attachment IDs
     * @param int $target_blog_id Target blog ID
     * @param int $source_blog_id Source blog ID
     * @param bool $force_update Force update even if exists
     * @return array Array of new attachment IDs
     */
    public function copy_images_batch(
        array $attachment_ids,
        int $target_blog_id,
        int $source_blog_id,
        bool $force_update = false
    ): array {
        $new_ids = [];
        
        foreach ($attachment_ids as $attachment_id) {
            $new_id = $this->copy_image_to_blog(
                $attachment_id,
                $target_blog_id,
                $source_blog_id,
                $force_update
            );
            
            if ($new_id) {
                $new_ids[] = $new_id;
            }
        }
        
        return $new_ids;
    }
    
    /**
     * Find existing attachment by filename
     *
     * @param string $filename Attachment filename
     * @return int|false Attachment ID or false
     */
    private function find_existing_attachment(string $filename) {
        $existing = get_posts([
            'post_type' => 'attachment',
            'meta_key' => '_wp_attached_file',
            'meta_value' => $filename,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        return !empty($existing) ? $existing[0]->ID : false;
    }
    
    /**
     * Delete existing attachment by filename
     *
     * @param string $filename Attachment filename
     * @return bool Success status
     */
    private function delete_existing_attachment(string $filename): bool {
        $existing_id = $this->find_existing_attachment($filename);
        
        if ($existing_id) {
            $this->logger->info("Deleting existing attachment", [
                'attachment_id' => $existing_id,
                'filename' => $filename
            ]);
            
            wp_delete_attachment($existing_id, true);
            return true;
        }
        
        return false;
    }
    
    /**
     * Create new attachment
     *
     * @param array $attachment_data Attachment data
     * @return int|false New attachment ID or false
     */
    private function create_attachment(array $attachment_data) {
        $upload_dir = wp_upload_dir();
        $target_file_path = $upload_dir['basedir'] . '/' . $attachment_data['attached_file'];
        
        // Create directory if needed
        $target_dir = dirname($target_file_path);
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Copy file
        if (!copy($attachment_data['file_path'], $target_file_path)) {
            $this->logger->error("Failed to copy file", [
                'source' => $attachment_data['file_path'],
                'target' => $target_file_path
            ]);
            return false;
        }
        
        // Get file type
        $filetype = wp_check_filetype(basename($target_file_path), null);
        
        // Create attachment post
        $attachment_post = [
            'post_mime_type' => $filetype['type'],
            'post_title' => $attachment_data['post']->post_title,
            'post_content' => $attachment_data['post']->post_content,
            'post_status' => 'inherit',
            'post_type' => 'attachment',
            'guid' => $upload_dir['url'] . '/' . $attachment_data['attached_file']
        ];
        
        $new_attachment_id = wp_insert_attachment($attachment_post, $target_file_path);
        
        if (!is_wp_error($new_attachment_id)) {
            // Generate attachment metadata
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($new_attachment_id, $target_file_path);
            wp_update_attachment_metadata($new_attachment_id, $attach_data);
            
            // Update the _wp_attached_file meta
            update_post_meta($new_attachment_id, '_wp_attached_file', $attachment_data['attached_file']);
            
            $this->logger->info("Successfully created attachment", [
                'new_attachment_id' => $new_attachment_id,
                'filename' => $attachment_data['attached_file']
            ]);
            
            return $new_attachment_id;
        }
        
        $this->logger->error("Failed to create attachment", [
            'error' => $new_attachment_id->get_error_message()
        ]);
        
        return false;
    }
    
    /**
     * Clean up unused images
     *
     * @param array $image_ids Array of image IDs to check
     * @param int $exclude_product_id Product ID to exclude from check
     * @return int Number of images deleted
     */
    public function cleanup_unused_images(array $image_ids, int $exclude_product_id): int {
        $deleted_count = 0;
        
        foreach ($image_ids as $image_id) {
            if ($this->is_image_unused($image_id, $exclude_product_id)) {
                wp_delete_attachment($image_id, true);
                $deleted_count++;
                
                $this->logger->info("Deleted unused image", ['image_id' => $image_id]);
            }
        }
        
        return $deleted_count;
    }
    
    /**
     * Check if image is unused by other products
     *
     * @param int $image_id Image ID
     * @param int $exclude_product_id Product ID to exclude
     * @return bool True if unused
     */
    private function is_image_unused(int $image_id, int $exclude_product_id): bool {
        $usage_check = new \WP_Query([
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => 1,
            'post__not_in' => [$exclude_product_id],
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_thumbnail_id',
                    'value' => $image_id
                ],
                [
                    'key' => '_product_image_gallery',
                    'value' => $image_id,
                    'compare' => 'LIKE'
                ]
            ]
        ]);
        
        return !$usage_check->have_posts();
    }
}