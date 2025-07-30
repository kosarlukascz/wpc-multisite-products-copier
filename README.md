# WPC Multisite Products Copier

A WordPress plugin for copying WooCommerce variable products between sites in a multisite network.

> 🤖 **Development Note**: This plugin was developed through vibe coding sessions with Claude (Anthropic's AI assistant), demonstrating the power of AI-assisted development for creating complex WordPress solutions.

## Overview

WPC Multisite Products Copier allows you to copy or update WooCommerce variable products from a source site (Blog ID 5) to any other site in your multisite network. It handles product variations, images, attributes, and integrations with popular plugins like ACF and Woodmart.

## Features

- ✅ Copy complete variable products with all variations
- ✅ Update existing products (slug, images, gallery)
- ✅ Automatic image copying and management
- ✅ Preserve product attributes and terms
- ✅ ACF (Advanced Custom Fields) support
- ✅ Woodmart theme video gallery support
- ✅ AJAX-powered admin interface
- ✅ Comprehensive logging system
- ✅ Automatic cleanup of unused images

## Requirements

- WordPress 5.0 or higher
- WordPress Multisite enabled
- WooCommerce 3.0 or higher
- PHP 7.4 or higher
- Source products must be on Blog ID 5

## Installation

1. Upload the `wpc-multisite-products-copier` folder to `/wp-content/plugins/`
2. Network activate the plugin through the 'Plugins' menu in WordPress Network Admin
3. The plugin will only be active on Blog ID 5 (source site)

## Usage

### Copying a Product

1. Navigate to a variable product on Blog ID 5
2. Look for the "Multisite Product Sync" metabox in the sidebar
3. Select the target site from the dropdown
4. Click "Create on selected site"
5. The product will be copied with all variations and images

### Updating a Product

1. If a product has already been copied, the target site will show in the "Synced to" list
2. Select the target site from the dropdown
3. Click "Update on selected site"
4. Only the slug, featured image, and gallery images will be updated

## Configuration

### Debug Logging

Debug logging is controlled by the `$debug_enabled` property in the main class:

```php
private $debug_enabled = false; // Set to true to enable logging
```

Logs are stored in: `wp-content/uploads/wpc-mpc-logs/`

### Source Blog ID

The source blog ID is set to 5 by default. To change it, modify:

```php
private $source_blog_id = 5;
```

## Integrations

### ACF (Advanced Custom Fields)

The plugin automatically handles ACF image fields:
- `feed_image` - Product feed image
- `swatch_image` - Product swatch image

ACF fields are copied along with the product and images are properly mapped to the target site.

### Woodmart Theme

Supports Woodmart's video gallery feature:
- Copies video gallery settings from source product
- Maps to the second gallery image on target site
- Preserves all video settings (autoplay, controls, etc.)
- Maintains original video URLs from source site

## How It Works

### Product Creation Flow

1. **Validation** - Checks if source product is variable
2. **Data Collection** - Gathers product data, meta, and custom fields
3. **Blog Switch** - Switches to target blog
4. **Product Creation** - Creates base product
5. **Attribute Mapping** - Maps attributes and creates missing terms
6. **Image Copying** - Copies featured and gallery images
7. **Variation Creation** - Creates all product variations
8. **Integration Handling** - Processes ACF and Woodmart data
9. **Cleanup** - Syncs attributes and updates lookup tables

### Product Update Flow

1. **Data Collection** - Gets updated slug and images from source
2. **Blog Switch** - Switches to target blog
3. **Slug Update** - Updates product slug
4. **Image Update** - Replaces featured and gallery images
5. **Integration Update** - Updates Woodmart video gallery
6. **Cleanup** - Removes unused old images

## API Reference

### AJAX Endpoints

#### `wpc_mpc_create_product`
Creates a new product on the target site.

**Parameters:**
- `product_id` (int) - Source product ID
- `target_blog_id` (int) - Target blog ID
- `nonce` - Security nonce

#### `wpc_mpc_update_product`
Updates an existing product on the target site.

**Parameters:**
- `product_id` (int) - Source product ID
- `target_blog_id` (int) - Target blog ID
- `nonce` - Security nonce

### Hooks

#### Actions

- `wpc_before_product_copy` - Fired before copying a product
- `wpc_after_product_copy` - Fired after successfully copying a product
- `wpc_before_product_update` - Fired before updating a product
- `wpc_after_product_update` - Fired after successfully updating a product

#### Filters

- `wpc_copy_product_data` - Filter product data before creation
- `wpc_copy_variation_data` - Filter variation data before creation
- `wpc_skip_product_meta` - Control which meta keys to skip

## Troubleshooting

### Images Not Updating

1. Clear any caching plugins
2. Check PHP OPcache settings
3. Verify file permissions on upload directories
4. Check debug logs for errors

### Products Not Syncing

1. Ensure source product is a variable product
2. Verify multisite network is properly configured
3. Check user capabilities (must have `edit_products`)
4. Review debug logs for specific errors

### Performance Issues

1. Disable debug logging in production
2. Ensure adequate PHP memory limit
3. Consider copying products in batches
4. Clean up old log files regularly

## Development

### File Structure

```
wpc-multisite-products-copier/
├── assets/
│   └── js/
│       └── admin.js             # Admin JavaScript
├── includes/
│   └── class-wpc-multisite-products-copier.php  # Main plugin class
├── languages/                    # Translation files
├── wpc-multisite-products-copier.php  # Plugin bootstrap
└── README.md
```

### Key Methods

- `create_product_on_blog()` - Main product creation logic
- `update_product_on_blog()` - Product update logic
- `copy_image_to_blog()` - Image copying logic
- `handle_woodmart_video_gallery()` - Woodmart integration

### Adding New Integrations

To add support for a new plugin/theme:

1. Create a handler method following the pattern of `handle_woodmart_video_gallery()`
2. Call it from both `create_product_on_blog()` and `update_product_on_blog()` methods
3. Use blog switching utilities to manage context

## Changelog

### Version 1.1.5
- Fixed critical bug where thumbnail was deleted when same image appeared in gallery
- Modified copy_image_to_blog to preserve thumbnail during gallery updates

### Version 1.1.3
- Added multiple site selection on product page
- Fixed initialization timing issues

### Version 1.1.0
- Added activity log tracking
- Created network admin UI for viewing copy/update history
- Added bulk operations for multiple products
- Created product mapping dashboard
- Enhanced documentation

### Version 1.0.7
- Fixed Woodmart video gallery to find entries with URLs
- Improved debug logging

### Version 1.0.6
- Added Woodmart video gallery support
- Enhanced image update logic

### Version 1.0.5
- Fixed blog context during save operations
- Improved image assignment

### Version 1.0.4
- Fixed image assignment issues
- Added slug generation fallback

### Version 1.0.3
- Force image updates during product updates
- Added version tracking

### Version 1.0.0
- Initial release

## Support

For issues, feature requests, or questions:
1. Check the debug logs first
2. Ensure you're running the latest version
3. Create an issue on the project repository

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for WooCommerce multisite product management.

🤖 This plugin was created through collaborative vibe coding sessions with Claude (Anthropic's AI assistant), showcasing how AI can help developers build sophisticated WordPress solutions efficiently.