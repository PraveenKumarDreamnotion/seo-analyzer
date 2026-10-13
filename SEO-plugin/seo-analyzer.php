<?php
/**
 * Plugin Name: SEO Analyzer
 * Plugin URI: https://thrivemattic.com/seo-analysis/
 * Description: A comprehensive SEO analysis tool for WordPress sites.
 * Version: 1.0.6
 * Author: thrivemattic
 * Author URI: https://thrivemattic.com/
 * Text Domain: seo-analyzer
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('SEO_ANALYZER_VERSION', '1.0.6');
define('SEO_ANALYZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEO_ANALYZER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add this function to check if the OpenAI addon is active
function seo_analyzer_is_addon_openai_active() {
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    return is_plugin_active('seo-analyzer-add-on-openai/seo-analyzer-add-on-openai.php');
}

/**
 * The code that runs during plugin activation.
 */
function activate_seo_analyzer() {
    require_once SEO_ANALYZER_PLUGIN_DIR . 'includes/class-seo-analyzer-activator.php';
    Seo_Analyzer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_seo_analyzer() {
    require_once SEO_ANALYZER_PLUGIN_DIR . 'includes/class-seo-analyzer-deactivator.php';
    Seo_Analyzer_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_seo_analyzer');
register_deactivation_hook(__FILE__, 'deactivate_seo_analyzer');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-seo-analyzer.php';

/**
 * Begins execution of the plugin.
 */
function run_seo_analyzer() {
    $plugin = new Seo_Analyzer();
    $plugin->run();

    // Ensure scripts are enqueued
    $public = new Seo_Analyzer_Public('seo-analyzer', SEO_ANALYZER_VERSION);
    add_action('wp_enqueue_scripts', array($public, 'enqueue_scripts'));
}
run_seo_analyzer();

/**
 * AJAX handlers
 */
function seo_analyzer_ajax_handler() {
    require_once SEO_ANALYZER_PLUGIN_DIR . 'public/class-seo-analyzer-public.php';
    $public = new Seo_Analyzer_Public('seo-analyzer', SEO_ANALYZER_VERSION);

    add_action('wp_ajax_perform_seo_analysis', array($public, 'perform_seo_analysis'));
    add_action('wp_ajax_nopriv_perform_seo_analysis', array($public, 'perform_seo_analysis'));

    // Make sure this action is registered only once
    add_action('wp_ajax_download_pdf_report', array($public, 'download_pdf_report'));
    add_action('wp_ajax_nopriv_download_pdf_report', array($public, 'download_pdf_report'));
}
add_action('init', 'seo_analyzer_ajax_handler');

/**
 * Register the shortcode
 */
function seo_analyzer_register_shortcode() {
    require_once SEO_ANALYZER_PLUGIN_DIR . 'public/class-seo-analyzer-public.php';
    $public = new Seo_Analyzer_Public('seo-analyzer', SEO_ANALYZER_VERSION);
    add_shortcode('seo_analyzer', array($public, 'seo_analyzer_shortcode'));
}
add_action('init', 'seo_analyzer_register_shortcode');

// Update this function at the end of the file
function seo_analyzer_integrate_addons() {
    if (seo_analyzer_is_addon_openai_active()) {
        // We don't need to add the filter here anymore
        // The add-on will add its own filter when it's active
    }
}
add_action('plugins_loaded', 'seo_analyzer_integrate_addons');

register_activation_hook(__FILE__, 'seo_analyzer_activate');

function seo_analyzer_activate() {
    $admin = new Seo_Analyzer_Admin('seo-analyzer', '1.0.0');
    $admin->create_results_table();
    $admin->create_api_key_table();
}

// Add this line to include Composer's autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Add after the other require_once statements
require_once plugin_dir_path(__FILE__) . 'includes/class-seo-analyzer-mailer.php';
