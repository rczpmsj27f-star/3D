# 3D Print Manager

A PHP-based order management system for 3D printing services.

## Setup Instructions

### 1. Configure Database Credentials

1. Copy `config.example.php` to `config.php`:
   ```bash
   cp config.example.php config.php
   ```

2. Edit `config.php` and update with your actual database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_database_name');
   define('DB_USER', 'your_database_user');
   define('DB_PASS', 'your_database_password');
   ```

### 2. File Structure

- **config.php** - Database and app configuration (DO NOT COMMIT)
- **db.php** - Database connection handler
- **functions.php** - Utility and business logic functions
- **header.php** - HTML header template
- **footer.php** - HTML footer template
- **index.php** - Dashboard
- **orders.php** - Orders list
- **order_edit.php** - Order editing and management
- **filaments.php** - Filaments inventory
- **filament_edit.php** - Edit filament details
- **models.php** - 3D model list
- **model_edit.php** - Edit model details
- **cost_variables.php** - Cost calculation variables
- **cost_variable_edit.php** - Edit cost variables
- **postage_services.php** - Postage configuration

### 3. Security Notes

⚠️ **Important**: Never commit `config.php` to version control. It's excluded by `.gitignore`.

- Store database credentials in `config.php` (local only, not in repo)
- Consider using environment variables for production deployments
- Always use prepared statements for SQL queries (already implemented)

## Features

- **Order Management**: Create, edit, and track orders
- **Inventory Tracking**: Manage filament stock and 3D models
- **Cost Calculation**: Track material, electricity, and time costs
- **Margin Analysis**: Calculate profit margins on orders
- **Postage Integration**: Manage shipping options and costs
- **Stock Warnings**: Track low-stock filaments

## Recent Fixes

- Fixed duplicate database connection functions
- Simplified item description logic in order editing
- Improved code organization and consistency
- Added security-focused configuration template
