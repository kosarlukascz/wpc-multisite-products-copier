<?php
/**
 * Plugin Name: WPC Multisite Products Copier
 * Plugin URI: https://github.com/kosarlukascz/wpc-multisite-products-copier
 * Description: A WordPress plugin to copy products across multisite networks. Developed through vibe coding with Claude AI.
 * Version: 1.1.3
 * Author: kosarlukascz & Claude AI
 * Author URI: https://github.com/kosarlukascz/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wpc-multisite-products-copier
 * Domain Path: /languages
 * Network: true
 * 
 * 🤖 Developed through collaborative vibe coding sessions with Claude (Anthropic's AI assistant)
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPC_MPC_VERSION', '1.1.3');
define('WPC_MPC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPC_MPC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPC_MPC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include the main plugin class
require_once WPC_MPC_PLUGIN_DIR . 'includes/class-wpc-multisite-products-copier.php';

// Initialize the plugin
function wpc_multisite_products_copier_init() {
    return WPC_Multisite_Products_Copier::get_instance();
}

// Hook into plugins_loaded to ensure WordPress is fully loaded
add_action('plugins_loaded', 'wpc_multisite_products_copier_init');

// Activation hook
register_activation_hook(__FILE__, array('WPC_Multisite_Products_Copier', 'activate'));

// Deactivation hook
register_deactivation_hook(__FILE__, array('WPC_Multisite_Products_Copier', 'deactivate'));