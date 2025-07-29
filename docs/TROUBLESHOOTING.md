# Troubleshooting Guide

## Common Issues and Solutions

### 1. Products Not Copying

#### Symptoms
- Click "Create on selected site" but nothing happens
- No error messages displayed
- Product doesn't appear on target site

#### Solutions

**Check Product Type:**
```php
// Product must be variable type
$product = wc_get_product($product_id);
if (!$product->is_type('variable')) {
    // This is the issue
}
```

**Verify User Permissions:**
- User must have `edit_products` capability
- Check both source and target sites

**Enable Debug Logging:**
```php
private $debug_enabled = true;
```
Then check logs at `/wp-content/uploads/wpc-mpc-logs/`

**Check AJAX Response:**
```javascript
// In browser console
jQuery(document).ajaxComplete(function(event, xhr, settings) {
    if (settings.url.includes('admin-ajax.php')) {
        console.log('Response:', xhr.responseJSON);
    }
});
```

### 2. Images Not Copying

#### Symptoms
- Product copies but without images
- Featured image missing
- Gallery images missing

#### Debug Steps

1. **Check File Permissions:**
```bash
# Ensure write permissions
chmod -R 755 wp-content/uploads
chown -R www-data:www-data wp-content/uploads
```

2. **Verify Source Images Exist:**
```php
// Add to debug
$this->log("Image check", [
    'file_exists' => file_exists($file_path),
    'is_readable' => is_readable($file_path),
    'file_path' => $file_path
]);
```

3. **Check Memory Limits:**
```php
// In wp-config.php
define('WP_MEMORY_LIMIT', '256M');
```

4. **Monitor Blog Context:**
```php
// Ensure correct blog when getting images
$this->log("Current blog", get_current_blog_id());
```

### 3. Images Not Updating

#### Symptoms
- Click "Update on selected site" but images don't change
- Old images remain on target
- Logs show old code running

#### Solutions

**Clear All Caches:**

1. **PHP OPcache:**
```php
if (function_exists('opcache_reset')) {
    opcache_reset();
}
```

2. **WordPress Object Cache:**
```php
wp_cache_flush();
```

3. **Plugin Version:**
```php
// Bump version to force reload
private $version = '1.0.8'; // Increment this
```

4. **Manual Cache Clear:**
```bash
# Restart PHP-FPM
sudo service php7.4-fpm restart

# Or for Apache
sudo service apache2 restart
```

### 4. Variations Not Copying

#### Symptoms
- Parent product copies but variations missing
- Some variations copy, others don't
- Attribute terms missing

#### Debug Process

1. **Check Attribute Mapping:**
```php
// Log attribute processing
$this->log("Attribute mapping", [
    'source_terms' => $source_terms,
    'target_terms' => $target_term_ids,
    'taxonomy' => $taxonomy
]);
```

2. **Verify Terms Exist:**
```sql
-- Check if terms exist on target blog
SELECT * FROM wp_7_terms WHERE slug = 'your-term-slug';
```

3. **Force Term Creation:**
```php
// If terms don't exist, create them
if (!$target_term) {
    $new_term = wp_insert_term(
        $source_term->name,
        $taxonomy,
        ['slug' => $source_term->slug]
    );
}
```

### 5. Woodmart Video Gallery Issues

#### Symptoms
- Video gallery data not copying
- Empty video data on target
- Wrong image ID used as key

#### Solutions

1. **Check Source Data:**
```php
// Debug Woodmart data
$source_video_gallery = get_post_meta($source_product_id, 'woodmart_wc_video_gallery', true);
$this->log("Video gallery source", $source_video_gallery);
```

2. **Verify Gallery Images:**
```php
// Need at least 2 gallery images
if (count($new_gallery_ids) < 2) {
    // Cannot set video gallery
}
```

3. **Check Video URL:**
```php
// Must have non-empty upload_video_url
if (empty($video_data['upload_video_url'])) {
    // Skip this entry
}
```

### 6. Performance Issues

#### Symptoms
- Timeout errors
- White screen during copy
- Partial product creation

#### Optimizations

1. **Increase Timeouts:**
```php
set_time_limit(300); // 5 minutes
ini_set('max_execution_time', 300);
```

2. **Process in Batches:**
```php
// For galleries with many images
$batch_size = 5;
foreach (array_chunk($images, $batch_size) as $batch) {
    // Process batch
    sleep(1); // Pause between batches
}
```

3. **Disable Debug in Production:**
```php
private $debug_enabled = false; // Disable logging
```

### 7. Database Errors

#### Symptoms
- "Error establishing database connection"
- Queries timing out
- Deadlock errors

#### Solutions

1. **Check Database Limits:**
```sql
SHOW VARIABLES LIKE 'max_connections';
SHOW PROCESSLIST;
```

2. **Optimize Queries:**
```php
// Use direct queries for bulk operations
global $wpdb;
$wpdb->query("SET SESSION wait_timeout = 300");
```

3. **Add Indexes:**
```sql
-- Add index for meta queries
ALTER TABLE wp_postmeta ADD INDEX meta_key_value (meta_key(191), meta_value(100));
```

### 8. Blog Switching Issues

#### Symptoms
- Data saved to wrong blog
- Cannot find products after creation
- Mixed blog contexts

#### Debug Steps

1. **Track Blog Context:**
```php
$this->log("Blog context", [
    'before' => get_current_blog_id(),
    'expected' => $target_blog_id,
    'after_switch' => get_current_blog_id()
]);
```

2. **Always Restore:**
```php
try {
    switch_to_blog($target_blog_id);
    // Operations
} finally {
    restore_current_blog();
}
```

3. **Verify After Switch:**
```php
switch_to_blog($target_blog_id);
if (get_current_blog_id() !== $target_blog_id) {
    throw new Exception('Blog switch failed');
}
```

## Debug Logging

### Enable Comprehensive Logging

```php
// Temporary debug function
private function debug_product_state($product_id, $context) {
    $this->log("Product Debug: " . $context, [
        'product_id' => $product_id,
        'exists' => wc_get_product($product_id) !== false,
        'type' => wc_get_product($product_id) ? wc_get_product($product_id)->get_type() : 'not found',
        'blog_id' => get_current_blog_id(),
        'memory_usage' => memory_get_usage(true),
        'peak_memory' => memory_get_peak_usage(true)
    ]);
}
```

### Log Analysis

```bash
# Find errors in logs
grep -i "error" /path/to/wpc-mpc-logs/*.log

# Track blog switches
grep "Blog [0-9]" debug-2024-01-15.log

# Find specific product
grep "product_id.*12345" debug-*.log
```

## Emergency Fixes

### Reset Plugin State

```php
// Clear all plugin data
delete_site_option('wpc_mpc_version');
delete_site_transient('wpc_mpc_cache');

// Clear product sync data
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wpc_%'");
```

### Force Cleanup

```php
// Remove orphaned images
$orphaned = $wpdb->get_results("
    SELECT ID FROM {$wpdb->posts} 
    WHERE post_type = 'attachment' 
    AND post_parent = 0
    AND ID NOT IN (
        SELECT meta_value FROM {$wpdb->postmeta} 
        WHERE meta_key IN ('_thumbnail_id', '_product_image_gallery')
    )
");

foreach ($orphaned as $attachment) {
    wp_delete_attachment($attachment->ID, true);
}
```

### Rebuild Product Data

```php
// Fix broken variations
$product = wc_get_product($product_id);
if ($product && $product->is_type('variable')) {
    WC_Product_Variable::sync($product_id);
    wc_delete_product_transients($product_id);
}
```

## Getting Help

### Information to Provide

1. **WordPress Environment:**
```
WordPress Version: 
WooCommerce Version:
PHP Version:
Multisite: Yes/No
Active Theme:
Active Plugins:
```

2. **Error Details:**
- Exact error message
- When it occurs
- What you were trying to do
- Browser console errors

3. **Debug Logs:**
- Recent entries from `/wpc-mpc-logs/`
- WordPress debug.log entries
- PHP error logs

4. **Steps to Reproduce:**
- Exact sequence of actions
- Source product ID
- Target blog ID
- User role/capabilities