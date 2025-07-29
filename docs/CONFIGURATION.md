# Configuration Guide

## Plugin Configuration

### Basic Settings

The plugin configuration is currently managed through code. Here are the main settings:

#### Source Blog ID
```php
private $source_blog_id = 5;
```
The blog ID where source products are located. Default is 5.

#### Debug Mode
```php
private $debug_enabled = false;
```
Enable/disable debug logging. Set to `true` for development.

#### Plugin Version
```php
private $version = '1.0.7';
```
Current plugin version.

### Advanced Configuration

#### Custom Source Blog ID

To use a different source blog ID, modify the class property:

```php
// In class-wpc-multisite-products-copier.php
private $source_blog_id = 10; // Change from 5 to 10
```

#### Debug Logging

To enable debug logging:

1. Set debug to true:
```php
private $debug_enabled = true;
```

2. Logs will be created in:
```
/wp-content/uploads/wpc-mpc-logs/debug-YYYY-MM-DD.log
```

3. Log files are automatically rotated when they exceed 5MB

#### Custom Log Directory

To change the log directory, modify the `init_logging()` method:

```php
private function init_logging() {
    if ($this->debug_enabled) {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/your-custom-logs';
        // ... rest of the method
    }
}
```

## Integration Configuration

### ACF Fields

The plugin automatically detects and handles these ACF fields:

```php
// Supported ACF image fields
$acf_fields = [
    'feed_image',    // Product feed image
    'swatch_image'   // Product swatch image
];
```

To add more ACF fields:

1. Add to the field detection in `create_product_on_blog()`:
```php
// Add your custom field
$your_field = get_field('your_field_name', $source_product_id, false);
if ($your_field && is_numeric($your_field)) {
    $acf_fields['your_field_name'] = [
        'value' => intval($your_field),
        'field_key' => $this->get_acf_field_key('your_field_name')
    ];
}
```

### Woodmart Video Gallery

The plugin handles Woodmart video galleries automatically. The expected structure:

```php
$video_gallery = [
    'image_id' => [
        'video_type' => 'mp4',
        'youtube_url' => '',
        'vimeo_url' => '',
        'upload_video_url' => 'https://example.com/video.mp4',
        'upload_video_id' => 123,
        'video_control' => 'theme',
        'hide_gallery_img' => '0',
        'video_size' => 'contain',
        'autoplay' => '1',
        'audio_status' => 'unmute',
        'hide_information' => '0'
    ]
];
```

## Custom Meta Fields

### Adding Custom Meta Fields

To copy additional meta fields, add them to the custom fields array:

```php
// In create_product_on_blog() method
$custom_fields = array(
    'feed_name' => get_post_meta($source_product_id, 'feed_name', true),
    '_color' => get_post_meta($source_product_id, '_color', true),
    '_gender' => get_post_meta($source_product_id, '_gender', true),
    'custom_order_wcp' => get_post_meta($source_product_id, 'custom_order_wcp', true),
    // Add your custom fields here
    'your_custom_field' => get_post_meta($source_product_id, 'your_custom_field', true)
);
```

### Excluding Meta Fields

To exclude certain meta fields from being copied:

```php
add_filter('wpc_skip_product_meta', function($skip_keys) {
    $skip_keys[] = '_internal_tracking_id';
    $skip_keys[] = '_temporary_data';
    return $skip_keys;
});
```

## Network Configuration

### Multisite Requirements

1. Ensure WordPress Multisite is enabled
2. Plugin must be network activated
3. Source blog (ID 5) must exist

### User Capabilities

Users must have the following capabilities:
- `edit_products` - Required for all operations
- Network admin access recommended for configuration

### Site Restrictions

To restrict which sites can be targets:

```php
add_filter('wpc_allowed_target_sites', function($sites) {
    // Only allow specific blog IDs
    $allowed_ids = [2, 3, 7];
    
    return array_filter($sites, function($site) use ($allowed_ids) {
        return in_array($site->blog_id, $allowed_ids);
    });
});
```

## Performance Configuration

### Memory Limits

For large products with many images:

```php
// In wp-config.php
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

### Timeout Settings

For slow servers or large galleries:

```php
// Increase execution time for large operations
add_action('wpc_before_product_copy', function() {
    set_time_limit(300); // 5 minutes
});
```

### Batch Processing

To process images in batches:

```php
// Modify copy_images_batch method
$batch_size = 5; // Process 5 images at a time
$chunks = array_chunk($attachment_ids, $batch_size);

foreach ($chunks as $chunk) {
    // Process chunk
    sleep(1); // Pause between batches
}
```

## Security Configuration

### Nonce Lifetime

To change nonce lifetime:

```php
add_filter('nonce_life', function($life) {
    if (doing_action('wp_ajax_wpc_mpc_create_product')) {
        return 4 * HOUR_IN_SECONDS; // 4 hours
    }
    return $life;
});
```

### Additional Security Checks

Add custom security validations:

```php
add_action('wpc_before_product_copy', function($source_id, $target_blog) {
    // Custom IP whitelist
    $allowed_ips = ['192.168.1.100', '10.0.0.50'];
    
    if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
        wp_die('Access denied from your IP');
    }
    
    // Time-based restrictions
    $hour = date('G');
    if ($hour < 9 || $hour > 17) {
        wp_die('Operations only allowed during business hours');
    }
}, 10, 2);
```

## Hooks Configuration

### Custom Actions

Add your own actions:

```php
// Log all product copies
add_action('wpc_after_product_copy', function($source_id, $new_id, $target_blog) {
    error_log(sprintf(
        'Product %d copied to blog %d as product %d by user %d',
        $source_id,
        $target_blog,
        $new_id,
        get_current_user_id()
    ));
}, 10, 3);

// Send email notification
add_action('wpc_after_product_copy', function($source_id, $new_id, $target_blog) {
    $site = get_site($target_blog);
    wp_mail(
        get_option('admin_email'),
        'Product Copied',
        sprintf('Product %d was copied to %s', $source_id, $site->blogname)
    );
}, 10, 3);
```

### Custom Filters

Modify plugin behavior:

```php
// Auto-publish copied products
add_filter('wpc_copy_product_data', function($data) {
    $data['post_status'] = 'publish';
    return $data;
});

// Add prefix to copied products
add_filter('wpc_copy_product_data', function($data) {
    $data['post_title'] = '[COPY] ' . $data['post_title'];
    return $data;
});

// Skip certain variations
add_filter('wpc_copy_variation_data', function($data, $variation_id) {
    $sku = get_post_meta($variation_id, '_sku', true);
    
    // Skip variations with specific SKU pattern
    if (strpos($sku, 'TEMP-') === 0) {
        return false; // Skip this variation
    }
    
    return $data;
}, 10, 2);
```

## Troubleshooting Configuration

### Enable WordPress Debug

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Plugin-Specific Debug

```php
// Force detailed logging
add_filter('wpc_debug_enabled', '__return_true');

// Log all blog switches
add_action('switch_blog', function($new_blog) {
    error_log('Switched to blog: ' . $new_blog);
});
```

### Clear Caches

```php
// Clear all caches after operations
add_action('wpc_after_product_copy', function() {
    wp_cache_flush();
    
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
});
```