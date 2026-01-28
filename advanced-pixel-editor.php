<?php
/**
 * Plugin Name: Advanced Pixel Editor
 * Plugin URI: https://github.com/rafael-minuesa/advanced-pixel-editor/
 * Description: Professional image editing tool with advanced filters, contrast adjustment, and unsharp masking. Real-time preview, accessibility features, and seamless WordPress integration.
 * Version: 2.9
 * Author: Rafael Minuesa
 * Author URI: https://github.com/rafael-minuesa
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-pixel-editor
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADVAIMG_VERSION', '2.9');
define('ADVAIMG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADVAIMG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADVAIMG_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once ADVAIMG_PLUGIN_DIR . 'includes/class-advanced-pixel-editor.php';
require_once ADVAIMG_PLUGIN_DIR . 'includes/class-advaimg-ajax-handler.php';
require_once ADVAIMG_PLUGIN_DIR . 'includes/advaimg-functions.php';

// Initialize the plugin
new Advanced_Pixel_Editor();
