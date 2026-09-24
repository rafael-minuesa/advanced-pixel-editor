<?php
/**
 * Plugin Name: Advanced Pixel Editor
 * Plugin URI: https://github.com/rafael-minuesa/advanced-pixel-editor/
 * Description: Edit images with an AI prompt, or crop, resize, rotate, flip, contrast and sharpen them inside the Media Library. Powered by ImageMagick.
 * Version: 3.8.0
 * Author: Rafael Minuesa
 * Author URI: https://github.com/rafael-minuesa
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: advanced-pixel-editor
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 7.1
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADVAIMG_VERSION', '3.8.0');
define('ADVAIMG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADVAIMG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADVAIMG_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once ADVAIMG_PLUGIN_DIR . 'includes/class-advanced-pixel-editor.php';
require_once ADVAIMG_PLUGIN_DIR . 'includes/class-advaimg-ajax-handler.php';
require_once ADVAIMG_PLUGIN_DIR . 'includes/advaimg-functions.php';
require_once ADVAIMG_PLUGIN_DIR . 'includes/class-advaimg-transform.php';
require_once ADVAIMG_PLUGIN_DIR . 'includes/class-advaimg-ai-edit.php';

// Initialize the plugin
new Advanced_Pixel_Editor();

register_deactivation_hook(__FILE__, ['ADVAIMG_AI_Edit', 'unschedule_cleanup']);
