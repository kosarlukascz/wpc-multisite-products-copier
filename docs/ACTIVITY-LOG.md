# Activity Log Documentation

## Overview

The WPC Multisite Products Copier plugin now includes a comprehensive activity logging system that tracks all product copy and update operations across your multisite network.

## Features

### 1. Network Admin Menu

A new menu item "Product Copy Log" is added to the Network Admin dashboard:
- **Location**: Network Admin → Product Copy Log
- **Capability Required**: `manage_network`
- **Icon**: Clipboard icon in the admin menu

### 2. Activity Log Page

The main activity log page displays:

#### Information Columns
- **Date/Time**: When the action occurred
- **Action**: Type of action (Create/Update) with visual icon
- **User**: Staff member who performed the action
- **Source Product**: Product name with link to edit (from source site)
- **Source Site**: Name of the source site
- **Target Product**: Product name with link to edit (on target site)
- **Target Site**: Name of the target site
- **Status**: Success indicator

#### Filtering Options
- **Action Filter**: All Actions / Created / Updated
- **User Filter**: Filter by specific staff member
- **Source Site Filter**: Filter by source blog
- **Target Site Filter**: Filter by target blog
- **Date Range**: From and To date pickers
- **Clear Filters**: Reset all filters

#### Pagination
- Shows 50 items per page
- Navigation links at top and bottom
- Display total number of items

### 3. Dashboard Widget

A dashboard widget shows the 10 most recent activities:
- **Location**: Network Admin Dashboard
- **Title**: "Recent Product Copy Activity"
- **Columns**: Time, Action, User, Product, Sites (From → To)
- **View All Button**: Links to full activity log

## How It Works

### Activity Tracking

Activities are automatically logged when:
1. A product is successfully created via "Create on selected site"
2. A product is successfully updated via "Update on selected site"

### Data Storage

Currently, activities are stored in the WordPress options table as a demonstration. In production, you should:
1. Create a custom database table
2. Implement proper data retention policies
3. Add export functionality

### Action Hooks

The system uses these hooks:
- `wpc_after_product_copy` - Fired after successful product creation
- `wpc_after_product_update` - Fired after successful product update

## UI Components

### Main Activity Log
```
┌─────────────────────────────────────────────────────────────────┐
│ Product Copy Activity Log                                        │
├─────────────────────────────────────────────────────────────────┤
│ [Action ▼] [User ▼] [Source ▼] [Target ▼] [From] [To] [Filter]  │
├─────────────────────────────────────────────────────────────────┤
│ Date/Time │ Action │ User │ Source │ Source Site │ Target │ ... │
├───────────┼────────┼──────┼────────┼─────────────┼────────┼─────┤
│ 2 hrs ago │ ⊕ Create│ John │ Shirt  │ Main Store  │ Shirt  │ ... │
│ 5 hrs ago │ ↻ Update│ Jane │ Pants  │ Main Store  │ Pants  │ ... │
└─────────────────────────────────────────────────────────────────┘
```

### Dashboard Widget
```
┌─────────────────────────────────────┐
│ Recent Product Copy Activity        │
├─────────────────────────────────────┤
│ Time │ Action │ User │ Product │ Sites│
├──────┼────────┼──────┼─────────┼──────┤
│ 2h   │ ⊕      │ John │ Shirt...│ 5→7  │
│ 5h   │ ↻      │ Jane │ Pants...│ 5→3  │
└─────────────────────────────────────┘
│        [View All Activity]          │
└─────────────────────────────────────┘
```

## Customization

### Adding Custom Columns

To add custom columns to the activity log:

```php
add_filter('wpc_activity_log_columns', function($columns) {
    $columns['ip_address'] = __('IP Address', 'wpc-multisite-products-copier');
    return $columns;
});
```

### Modifying Data Collection

To collect additional data during logging:

```php
add_filter('wpc_activity_log_data', function($data, $action) {
    $data['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    return $data;
}, 10, 2);
```

### Custom Filters

To add custom filter options:

```php
add_action('wpc_activity_log_filters', function() {
    ?>
    <select name="custom_filter">
        <option value="">Custom Filter</option>
        <!-- Add options -->
    </select>
    <?php
});
```

## Performance Considerations

1. **Data Retention**: Implement automatic cleanup of old records
2. **Indexing**: Add database indexes for frequently queried columns
3. **Caching**: Cache filter results for better performance
4. **Pagination**: Current implementation shows 50 items per page

## Security

- Only network administrators can view the activity log
- All data is escaped before display
- Nonces are used for filter forms
- Direct file access is prevented

## Future Enhancements

1. **Export Functionality**: Export logs to CSV/Excel
2. **Email Notifications**: Alert admins of specific activities
3. **Advanced Filtering**: More complex filter combinations
4. **Charts/Analytics**: Visual representation of activity trends
5. **Audit Trail**: Track failed attempts and errors
6. **Role-Based Access**: Allow site admins to view their own logs