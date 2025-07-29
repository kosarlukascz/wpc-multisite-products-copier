# API Documentation

## Public Methods

### `get_instance()`

Get the singleton instance of the plugin.

**Returns:** `WPC_Multisite_Products_Copier` - Plugin instance

**Example:**
```php
$copier = WPC_Multisite_Products_Copier::get_instance();
```

### `ajax_create_product()`

AJAX handler for creating a product on target blog.

**AJAX Action:** `wp_ajax_wpc_mpc_create_product`

**Parameters (POST):**
- `nonce` (string) - Security nonce
- `product_id` (int) - Source product ID
- `target_blog_id` (int) - Target blog ID

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Product created successfully!",
    "target_product_id": 123
  }
}
```

### `ajax_update_product()`

AJAX handler for updating a product on target blog.

**AJAX Action:** `wp_ajax_wpc_mpc_update_product`

**Parameters (POST):**
- `nonce` (string) - Security nonce
- `product_id` (int) - Source product ID
- `target_blog_id` (int) - Target blog ID

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Product updated successfully!"
  }
}
```

## Private Methods

### `create_product_on_blog($source_product_id, $target_blog_id)`

Creates a complete copy of a variable product on the target blog.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$target_blog_id` (int) - Target blog ID

**Returns:** `int|WP_Error` - New product ID or error

**Process:**
1. Validates source product
2. Collects all product data
3. Switches to target blog
4. Creates product and variations
5. Copies images and meta
6. Handles integrations

### `update_product_on_blog($source_product_id, $target_blog_id, $target_product_id)`

Updates an existing product's slug and images.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$target_blog_id` (int) - Target blog ID
- `$target_product_id` (int) - Target product ID

**Returns:** `bool|WP_Error` - Success status or error

**Updates:**
- Product slug
- Featured image
- Gallery images
- Woodmart video gallery (if applicable)

### `copy_image_to_blog($attachment_id, $target_blog_id, $source_blog_id)`

Copies an image attachment between blogs.

**Parameters:**
- `$attachment_id` (int) - Source attachment ID
- `$target_blog_id` (int) - Target blog ID
- `$source_blog_id` (int) - Source blog ID

**Returns:** `int|WP_Error` - New attachment ID or error

**Features:**
- Preserves file structure
- Copies alt text
- Generates thumbnails
- Handles duplicates

### `handle_woodmart_video_gallery($source_product_id, $target_product_id, $new_gallery_ids, $source_blog_id, $target_blog_id)`

Handles Woodmart theme video gallery meta.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$target_product_id` (int) - Target product ID
- `$new_gallery_ids` (array) - New gallery image IDs
- `$source_blog_id` (int) - Source blog ID
- `$target_blog_id` (int) - Target blog ID

**Process:**
1. Finds video entry with URL
2. Maps to second gallery image
3. Preserves all video settings

## Meta Keys

### Product Meta

**Synced Meta:**
- `_wpc_synced_product_ids` - Array of synced products by blog ID
- `_wpc_source_product` - Reference to source product
- `_wpc_last_sync_{blog_id}` - Last sync timestamp

**Copied Meta:**
- `feed_name` - Feed name
- `_color` - Product color
- `_gender` - Product gender
- `custom_order_wcp` - Custom order data

### Image Meta

**Preserved Meta:**
- `_wp_attached_file` - File path
- `_wp_attachment_metadata` - Image metadata
- `_wp_attachment_image_alt` - Alt text

### ACF Fields

**Supported Fields:**
- `feed_image` - Feed image attachment
- `swatch_image` - Swatch image attachment

### Woodmart Meta

**Video Gallery Structure:**
```php
[
    'image_id' => [
        'video_type' => 'mp4',
        'upload_video_url' => 'https://...',
        'upload_video_id' => 123,
        'autoplay' => '1',
        // ... other settings
    ]
]
```

## JavaScript API

### `wpc_mpc_ajax` Object

Global object available in admin:

```javascript
wpc_mpc_ajax = {
    ajax_url: 'admin-ajax.php',
    nonce: 'security_nonce',
    product_id: 123,
    messages: {
        select_site: 'Please select a target site.',
        creating: 'Creating product...',
        updating: 'Updating product...',
        success_create: 'Product created successfully!',
        success_update: 'Product updated successfully!',
        error: 'An error occurred. Please try again.'
    }
};
```

### Events

**Triggered Events:**
- `wpc:product:creating` - Before create
- `wpc:product:created` - After create
- `wpc:product:updating` - Before update
- `wpc:product:updated` - After update
- `wpc:product:error` - On error

**Example:**
```javascript
jQuery(document).on('wpc:product:created', function(e, data) {
    console.log('Product created:', data.target_product_id);
});
```

## Error Codes

### WP_Error Codes

- `invalid_product` - Source product validation failed
- `target_not_found` - Target product not found
- `invalid_attachment` - Attachment validation failed
- `file_not_found` - Source file missing
- `copy_failed` - File copy operation failed
- `creation_failed` - Product creation failed
- `update_failed` - Product update failed

### AJAX Error Responses

```json
{
  "success": false,
  "data": {
    "message": "Error message here"
  }
}
```

## Filters

### `wpc_copy_product_data`

Filter product data before creation.

**Parameters:**
- `$product_data` (array) - Product post data
- `$source_product_id` (int) - Source product ID
- `$target_blog_id` (int) - Target blog ID

**Example:**
```php
add_filter('wpc_copy_product_data', function($data, $source_id, $target_blog) {
    $data['post_status'] = 'publish'; // Auto-publish
    return $data;
}, 10, 3);
```

### `wpc_copy_variation_data`

Filter variation data before creation.

**Parameters:**
- `$variation_data` (array) - Variation data
- `$source_variation_id` (int) - Source variation ID
- `$parent_product_id` (int) - Parent product ID

### `wpc_skip_product_meta`

Control which meta keys to skip during copy.

**Parameters:**
- `$skip_keys` (array) - Meta keys to skip
- `$source_product_id` (int) - Source product ID

**Example:**
```php
add_filter('wpc_skip_product_meta', function($skip_keys) {
    $skip_keys[] = '_custom_internal_field';
    return $skip_keys;
});
```

## Actions

### `wpc_before_product_copy`

Fired before copying a product.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$target_blog_id` (int) - Target blog ID

### `wpc_after_product_copy`

Fired after successfully copying a product.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$new_product_id` (int) - New product ID
- `$target_blog_id` (int) - Target blog ID

### `wpc_before_product_update`

Fired before updating a product.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$target_product_id` (int) - Target product ID
- `$target_blog_id` (int) - Target blog ID

### `wpc_after_product_update`

Fired after successfully updating a product.

**Parameters:**
- `$source_product_id` (int) - Source product ID
- `$target_product_id` (int) - Target product ID
- `$target_blog_id` (int) - Target blog ID