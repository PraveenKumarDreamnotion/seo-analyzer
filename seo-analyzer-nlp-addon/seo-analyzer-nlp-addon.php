<?php
/**
 * Plugin Name: SEO Analyzer NLP Addon
 * Plugin URI: https://thrivemattic.com/nlp-analysis/
 * Description: An NLP-powered add-on for the SEO Analyzer plugin that retrieves and displays the full text content of a URL.
 * Version: 1.0.0
 * Author: thrivemattic
 * Author URI: https://thrivemattic.com/
 * Text Domain: seo-analyzer-nlp-addon
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define constants
define('SEO_ANALYZER_NLP_ADDON_VERSION', '1.0.0');
define('SEO_ANALYZER_NLP_ADDON_PLUGIN_NAME', 'seo-analyzer-nlp-addon');
define('SEO_ANALYZER_NLP_ADDON_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include the loader class
require_once SEO_ANALYZER_NLP_ADDON_PLUGIN_DIR . 'includes/class-seo-analyzer-nlp-addon-loader.php';

// Include the main class
require_once SEO_ANALYZER_NLP_ADDON_PLUGIN_DIR . 'includes/class-seo-analyzer-nlp-addon.php';

// Load the Google Cloud Client Library
require_once __DIR__ . '/vendor/autoload.php';

// Run the plugin
function run_seo_analyzer_nlp_addon() {
    $plugin = new SEO_Analyzer_NLP_Addon();
    $plugin->run();
}
run_seo_analyzer_nlp_addon();