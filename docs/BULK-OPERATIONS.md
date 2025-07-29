# Bulk Operations Documentation

## Overview

The WPC Multisite Products Copier plugin now includes powerful bulk operations that allow you to copy or update multiple products across your multisite network in one go.

## Features

### 1. Bulk Actions in Products List

New bulk actions are available in the WooCommerce products list (only on the source blog - ID 5):

- **Copy to Sites**: Create copies of selected products on multiple target sites
- **Update on Sites**: Update existing copies of selected products across all synced sites

### 2. Network Sync Column

A new "Network Sync" column in the products list shows:
- Number of sites where the product is synced
- Visual indicator (multisite icon)
- Tooltip with list of synced sites

### 3. Site Selection Modal

When performing bulk copy operations:
- A modal appears allowing you to select target sites
- Select one or multiple sites where products should be copied
- Clear interface with site names and IDs

### 4. Progress Tracking

Real-time progress tracking includes:
- Progress bar showing completion percentage
- Current operation status (e.g., "Processing 5 of 20 products...")
- Error reporting for failed operations
- Success summary upon completion

## How to Use Bulk Operations

### Bulk Copy Products

1. Navigate to **Products** → **All Products** on the source site (ID 5)
2. Select products using the checkboxes
3. From the "Bulk Actions" dropdown, choose "Copy to Sites"
4. Click "Apply"
5. Select target sites in the modal
6. Click "Start Operation"
7. Monitor progress and review results

### Bulk Update Products

1. Navigate to **Products** → **All Products** on the source site (ID 5)
2. Select products that have existing copies on other sites
3. From the "Bulk Actions" dropdown, choose "Update on Sites"
4. Click "Apply"
5. The update will automatically process all synced sites
6. Monitor progress and review results

## Technical Details

### Batch Processing

- Products are processed in batches of 5 to prevent timeouts
- Each batch is processed with a 1-second delay
- Operations continue even if individual products fail

### Error Handling

- Non-variable products are skipped with an error message
- Failed operations are logged and displayed
- Partial success is possible (some products succeed, others fail)

### Performance Considerations

- Large operations may take several minutes
- Browser must remain open during processing
- Operations use AJAX to prevent page timeouts

### Permissions

- Requires `edit_products` capability
- Only available on the designated source blog (ID 5)

## Troubleshooting

### Operation Not Starting

- Ensure you're on the source blog (ID 5)
- Check that you have the required permissions
- Verify JavaScript is enabled

### Products Not Copying

- Confirm products are variable type
- Check target sites are accessible
- Review error messages in the progress display

### Timeout Issues

- For very large operations, consider selecting fewer products
- Process products in smaller batches
- Check server timeout settings

## Activity Logging

All bulk operations are logged in the Activity Log:
- Each successful copy/update is recorded
- User who initiated the operation is tracked
- Timestamps for audit trail

## Best Practices

1. **Test First**: Try bulk operations with a small number of products first
2. **Off-Peak Hours**: Run large operations during low-traffic periods
3. **Monitor Progress**: Keep the browser tab open and monitor progress
4. **Review Results**: Check the activity log after completion
5. **Backup**: Always maintain backups before large operations