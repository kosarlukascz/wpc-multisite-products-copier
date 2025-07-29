# Developer Documentation

> 🤖 **Development Note**: This plugin was developed through vibe coding sessions with Claude (Anthropic's AI assistant), demonstrating effective AI-human collaboration in software development.

## Architecture Overview

The WPC Multisite Products Copier plugin follows a singleton pattern with a monolithic class structure. While functional, we're in the process of refactoring to a more modular architecture.

## Current Architecture (v1.x)

### Main Class: `WPC_Multisite_Products_Copier`

The entire plugin functionality is contained in a single class with the following responsibilities:

1. **Initialization** - Hook registration, plugin setup
2. **UI Rendering** - Admin metabox and interface
3. **AJAX Handling** - Processing create/update requests
4. **Product Operations** - Core copying/updating logic
5. **Image Management** - Attachment copying and cleanup
6. **Integration Support** - ACF and Woodmart handling
7. **Logging** - Debug and error logging

### Key Design Patterns

#### Singleton Pattern
```php
public static function get_instance() {
    if (null === self::$instance) {
        self::$instance = new self();
    }
    return self::$instance;
}
```

#### Blog Switching Pattern
```php
switch_to_blog($target_blog_id);
// Perform operations
restore_current_blog();
```

## Refactored Architecture (v2.x - In Progress)

### Design Principles

1. **Single Responsibility Principle** - Each class has one reason to change
2. **Dependency Injection** - Dependencies are injected, not created
3. **Interface Segregation** - Small, focused interfaces
4. **Open/Closed Principle** - Open for extension, closed for modification

### New Class Structure

```
├── Interfaces/
│   └── WPC_Handler_Interface       # Common handler interface
├── Handlers/
│   ├── WPC_Product_Handler         # Product operations
│   ├── WPC_Image_Handler           # Image operations
│   └── WPC_Attribute_Handler       # Attribute management
├── Integrations/
│   ├── WPC_Integration_Abstract    # Base integration class
│   ├── WPC_ACF_Integration         # ACF support
│   └── WPC_Woodmart_Integration    # Woodmart support
├── Repositories/
│   ├── WPC_Product_Repository      # Product data access
│   └── WPC_Attachment_Repository   # Attachment data access
├── Admin/
│   ├── WPC_Admin_UI                # UI rendering
│   └── WPC_Ajax_Handler            # AJAX processing
└── Utilities/
    ├── WPC_Logger                  # Logging utility
    └── WPC_Blog_Switcher           # Blog switching trait
```

## Code Examples

### Current Implementation

#### Creating a Product
```php
private function create_product_on_blog($source_product_id, $target_blog_id) {
    try {
        // Get source product
        $source_product = wc_get_product($source_product_id);
        
        // Switch to target blog
        switch_to_blog($target_blog_id);
        
        // Create product
        $new_product_id = wp_insert_post($product_data);
        
        // Copy images, attributes, variations
        // ... 300+ lines of logic ...
        
        restore_current_blog();
        
        return $new_product_id;
    } catch (Exception $e) {
        // Error handling
    }
}
```

### Refactored Implementation

#### Using the New Architecture
```php
// Dependency injection setup
$logger = new WPC_Logger($config['debug_enabled']);
$image_handler = new WPC_Image_Handler($logger);
$attribute_handler = new WPC_Attribute_Handler($logger);

// Integration setup
$integrations = [
    new WPC_ACF_Integration($logger),
    new WPC_Woodmart_Integration($logger)
];

// Product handler with dependencies
$product_handler = new WPC_Product_Handler(
    $logger,
    $image_handler,
    $attribute_handler,
    $integrations
);

// Clean API
$result = $product_handler->copy_product($source_id, $target_blog_id);
```

#### Using the Blog Switcher Trait
```php
class WPC_Image_Handler {
    use WPC_Blog_Switcher;
    
    public function copy_image($image_id, $target_blog_id, $source_blog_id) {
        // Get data from source blog
        $image_data = $this->get_from_blog($source_blog_id, function() use ($image_id) {
            return [
                'file' => get_attached_file($image_id),
                'meta' => wp_get_attachment_metadata($image_id)
            ];
        });
        
        // Set data on target blog
        return $this->set_on_blog($target_blog_id, function() use ($image_data) {
            return wp_insert_attachment($image_data);
        });
    }
}
```

## Testing

### Unit Testing

The refactored architecture supports unit testing:

```php
class WPC_Image_Handler_Test extends WP_UnitTestCase {
    private $handler;
    private $logger;
    
    public function setUp() {
        parent::setUp();
        $this->logger = $this->createMock(WPC_Logger::class);
        $this->handler = new WPC_Image_Handler($this->logger);
    }
    
    public function test_copy_image_to_blog() {
        // Create test attachment
        $attachment_id = $this->factory->attachment->create();
        
        // Copy to another blog
        $new_id = $this->handler->copy_image_to_blog(
            $attachment_id,
            2, // target blog
            1  // source blog
        );
        
        // Assert
        $this->assertNotFalse($new_id);
        $this->assertNotEquals($attachment_id, $new_id);
    }
}
```

### Integration Testing

Test the complete flow:

```php
public function test_complete_product_copy() {
    // Setup
    $source_product = $this->create_test_variable_product();
    
    // Execute
    $handler = new WPC_Product_Handler(/* dependencies */);
    $result = $handler->copy_product($source_product->get_id(), 2);
    
    // Verify
    switch_to_blog(2);
    $copied_product = wc_get_product($result);
    
    $this->assertEquals(
        $source_product->get_name(),
        $copied_product->get_name()
    );
    
    restore_current_blog();
}
```

## Performance Considerations

### Database Queries

1. **Use Caching** - Cache repeated queries
2. **Batch Operations** - Process multiple items together
3. **Lazy Loading** - Load data only when needed

### Blog Switching

1. **Minimize Switches** - Group operations by blog
2. **Use Direct Queries** - When possible, query directly
3. **Cache Blog State** - Store blog-specific data

### Image Processing

1. **Async Processing** - Consider background jobs for large galleries
2. **Size Optimization** - Only copy needed image sizes
3. **Cleanup Strategy** - Regular cleanup of orphaned images

## Security

### Nonce Verification
```php
if (!wp_verify_nonce($_POST['nonce'], 'wpc_mpc_ajax')) {
    wp_send_json_error(['message' => 'Security check failed']);
}
```

### Capability Checks
```php
if (!current_user_can('edit_products')) {
    wp_send_json_error(['message' => 'Insufficient permissions']);
}
```

### Data Validation
```php
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$blog_id = isset($_POST['blog_id']) ? intval($_POST['blog_id']) : 0;

if (!$product_id || !$blog_id) {
    wp_send_json_error(['message' => 'Invalid parameters']);
}
```

## Extending the Plugin

### Adding a New Integration

1. Create a class extending `WPC_Integration_Abstract`:

```php
class WPC_YourPlugin_Integration extends WPC_Integration_Abstract {
    protected string $name = 'YourPlugin';
    
    protected function init(): void {
        // Initialization logic
    }
    
    public function is_available(): bool {
        return class_exists('YourPlugin');
    }
    
    public function handle_product_create($source_id, $target_id, $source_blog, $target_blog, $context): bool {
        // Your creation logic
        return true;
    }
    
    public function handle_product_update($source_id, $target_id, $source_blog, $target_blog, $context): bool {
        // Your update logic
        return true;
    }
}
```

2. Register it with the product handler:

```php
$integrations[] = new WPC_YourPlugin_Integration($logger);
```

### Adding Custom Hooks

```php
// Before copying
do_action('wpc_before_product_copy', $source_id, $target_blog_id);

// After copying
do_action('wpc_after_product_copy', $source_id, $new_product_id, $target_blog_id);

// Filter product data
$product_data = apply_filters('wpc_copy_product_data', $product_data, $source_id);
```

## Debugging

### Enable Debug Mode
```php
private $debug_enabled = true;
```

### Log Locations
- Debug logs: `/wp-content/uploads/wpc-mpc-logs/debug-YYYY-MM-DD.log`
- Error logs: Check WordPress debug.log

### Common Issues

1. **Blog Context Issues**
   - Always verify current blog with `get_current_blog_id()`
   - Use proper switching pattern
   - Check logs for "Blog X" prefixes

2. **Image Copy Failures**
   - Verify file permissions
   - Check source file existence
   - Monitor memory usage

3. **Attribute Mapping**
   - Ensure terms exist on target
   - Check taxonomy registration
   - Verify term slug uniqueness

## Best Practices

1. **Always Use Type Hints**
```php
public function copy_product(int $source_id, int $target_blog_id): int
```

2. **Document Complex Logic**
```php
/**
 * Maps source term slugs to target term IDs
 * 
 * This is necessary because term IDs differ between blogs
 * but slugs should remain consistent
 */
```

3. **Handle Errors Gracefully**
```php
try {
    // Operations
} catch (Exception $e) {
    $this->logger->error('Operation failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    return new WP_Error('operation_failed', $e->getMessage());
}
```

4. **Use Early Returns**
```php
if (!$this->validate_product($product_id)) {
    return false;
}

if (!$this->can_copy_to_blog($target_blog_id)) {
    return false;
}

// Main logic here
```

## Future Improvements

1. **Background Processing** - For large product catalogs
2. **Bulk Operations** - Copy multiple products at once
3. **Scheduling** - Automated sync on schedule
4. **Conflict Resolution** - Handle concurrent updates
5. **API Endpoints** - REST API for external integration
6. **Import/Export** - Configuration portability
7. **Multi-source Support** - Copy from any blog, not just blog 5