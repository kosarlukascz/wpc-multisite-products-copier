# Product Mapping Dashboard Documentation

## Overview

The Product Network Map provides a visual overview of how products are distributed across your multisite network, making it easy to see which products exist on which sites and their sync status.

## Access

Navigate to: **Network Admin** → **Product Copy Log** → **Product Map**

## Features

### 1. Visual Product Matrix

The dashboard displays products in a matrix format:
- **Rows**: Variable products from the source site
- **Columns**: All sites in the network
- **Cells**: Sync status for each product/site combination

### 2. Status Indicators

Each cell shows the sync status with color-coded dots:
- 🟢 **Green (Synced)**: Product is up-to-date on the target site
- 🟡 **Yellow (Outdated)**: Product exists but source has been modified since last sync
- ⚪ **Gray (Not Exists)**: Product doesn't exist on the target site
- 🔵 **Blue (Source)**: Indicates the source site

### 3. Quick Actions

For each product/site combination:
- **Create**: Add product to a site where it doesn't exist
- **Update**: Sync an outdated product with the latest source version
- **View**: Open the product edit page on the target site

### 4. Filtering & Search

Filter products by:
- **Search**: Find products by name
- **Sync Status**: All, Fully Synced, Partially Synced, Not Synced, Has Outdated Copies
- **Category**: Filter by WooCommerce product categories

### 5. Export Functionality

Export the entire product map to CSV:
- Click "Export CSV" button
- Downloads a spreadsheet with all products and their sync status
- Useful for reporting and planning

## Understanding the Interface

### Product Information

Each row displays:
- Product name
- SKU (if available)
- Categories
- "View Details" link for more information

### Site Columns

Each column represents a site:
- Source site is highlighted with blue background
- Site names are shown in the header
- Source site has a special "Source" badge

### Interactive Elements

- **Refresh Button**: Reload the product map with latest data
- **Pagination**: Navigate through large product catalogs
- **Tooltips**: Hover for additional information

## Use Cases

### 1. Identify Unsyncted Products

Use the "Not Synced" filter to find products that haven't been copied to any sites yet.

### 2. Find Outdated Products

Use the "Has Outdated Copies" filter to identify products that need updates across the network.

### 3. Verify Full Sync

Use the "Fully Synced" filter to see products that exist on all sites.

### 4. Category-Based Management

Filter by category to manage product distribution for specific product lines.

## How Sync Detection Works

### Outdated Detection

A product is marked as outdated when:
- The source product's last modified date is newer than the last sync time
- Changes have been made to the source after the last update

### Sync Tracking

The system tracks:
- When each product was last synced to each site
- The relationship between source and target products
- Modification timestamps for change detection

## Performance Tips

### Large Catalogs

For sites with many products:
- Use filters to narrow down the view
- Products load 50 at a time for performance
- Use search to find specific products quickly

### Refresh Frequency

- The map shows real-time data
- Click Refresh to update after making changes
- Sync status is calculated on-demand

## Troubleshooting

### Products Not Showing

- Ensure products are variable type
- Check that products are published
- Verify you're viewing the correct source site

### Incorrect Sync Status

- Use Refresh button to reload latest data
- Check product modification dates
- Verify sync metadata is properly stored

### Slow Loading

- Large catalogs may take a moment to load
- Use filters to reduce the dataset
- Check network connectivity

## Integration with Other Features

### Activity Log

- All sync operations from the mapping dashboard are logged
- View detailed history in the Activity Log

### Bulk Operations

- Use Bulk Operations for multiple products
- Product Map is ideal for individual product management

### Single Product Metabox

- Same sync functionality available on individual product edit pages
- Product Map provides network-wide overview

## Best Practices

1. **Regular Reviews**: Check the map weekly to ensure products are properly synced
2. **Use Filters**: Don't try to view all products at once on large sites
3. **Plan Updates**: Use the export feature to plan sync operations
4. **Monitor Changes**: Check for outdated products after major updates
5. **Document Patterns**: Note which sites should have which products