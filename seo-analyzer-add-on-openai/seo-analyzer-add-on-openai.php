<?php
/**
 * Plugin Name: SEO Analyzer Add-on OpenAI
 * Plugin URI: https://thrivemattic.com/seo-analysis/
 * Description: An OpenAI-powered add-on for the SEO Analyzer plugin that provides AI-generated SEO suggestions.
 * Version: 1.0.0
 * Author: thrivemattic
 * Author URI: https://thrivemattic.com/
 * Text Domain: seo-analyzer-add-on-openai
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Add this near the top of the file, after the plugin header
define('SEO_ANALYZER_ADDON_OPENAI_API_KEY_OPTION', 'seo_analyzer_openai_api_key');

// Add these constants near the top of the file, after the existing definitions
define('SEO_ANALYZER_ADDON_OPENAI_VERSION', '1.0.0');
define('SEO_ANALYZER_ADDON_OPENAI_PLUGIN_NAME', 'seo-analyzer-add-on-openai');
define('SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Add this function to the file
function seo_analyzer_addon_openai_get_api_key() {
    return get_option(SEO_ANALYZER_ADDON_OPENAI_API_KEY_OPTION);
}

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Check if the main SEO Analyzer plugin is active
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Function to find the SEO Analyzer plugin file
function find_seo_analyzer_plugin() {
    $plugins_dir = ABSPATH . 'wp-content/plugins/';
    $plugin_files = glob($plugins_dir . '*/seo-analyzer.php');
    return !empty($plugin_files) ? plugin_basename($plugin_files[0]) : false;
}

$seo_analyzer_plugin = find_seo_analyzer_plugin();

if (!$seo_analyzer_plugin || !is_plugin_active($seo_analyzer_plugin)) {
    add_action('admin_notices', function() use ($seo_analyzer_plugin) {
        echo '<div class="error"><p>SEO Analyzer Add-on OpenAI requires the SEO Analyzer plugin to be installed and activated.</p>';
        echo '<p>Debug info: ';
        echo 'ABSPATH: ' . ABSPATH . '<br>';
        echo 'Plugin file found: ' . ($seo_analyzer_plugin ? 'Yes' : 'No') . '<br>';
        if ($seo_analyzer_plugin) {
            echo 'Plugin file path: ' . $seo_analyzer_plugin . '<br>';
            echo 'Plugin is active: ' . (is_plugin_active($seo_analyzer_plugin) ? 'Yes' : 'No') . '<br>';
        }
        echo 'is_plugin_active function exists: ' . (function_exists('is_plugin_active') ? 'Yes' : 'No') . '<br>';
        echo '</p></div>';
    });
    return;
}

// Check if required files exist before including them
$required_files = [
    SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'includes/class-seo-analyzer-addon-openai.php',
    SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'includes/class-seo-analyzer-addon-openai-api.php',
    SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'includes/class-seo-analyzer-addon-openai-loader.php'
];

$missing_files = array_filter($required_files, function($file) {
    return !file_exists($file);
});

if (!empty($missing_files)) {
    add_action('admin_notices', function() use ($missing_files) {
        echo '<div class="error"><p>SEO Analyzer Add-on OpenAI is missing required files:</p>';
        echo '<ul>';
        foreach ($missing_files as $file) {
            echo '<li>' . esc_html($file) . '</li>';
        }
        echo '</ul>';
        echo '<p>Please reinstall the plugin or contact support.</p></div>';
    });
    return;
}

// If all required files exist, include them
foreach ($required_files as $file) {
    require_once $file;
}

function run_seo_analyzer_addon_openai() {
    $plugin = new SEO_Analyzer_Addon_OpenAI();
    $plugin->run();
}

run_seo_analyzer_addon_openai();

register_activation_hook(__FILE__, 'seo_analyzer_addon_openai_activate');

function seo_analyzer_addon_openai_activate() {
    error_log('SEO Analyzer Add-on OpenAI: Activation started');
    update_option('seo_analyzer_addon_openai_was_active', true);
    
    // Check if the main SEO Analyzer plugin is active
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    
    $seo_analyzer_plugin = find_seo_analyzer_plugin();
    error_log('SEO Analyzer Add-on OpenAI: Main plugin file - ' . ($seo_analyzer_plugin ? $seo_analyzer_plugin : 'Not found'));
    
    if (!$seo_analyzer_plugin || !is_plugin_active($seo_analyzer_plugin)) {
        // Deactivate this add-on
        deactivate_plugins(plugin_basename(__FILE__));
        error_log('SEO Analyzer Add-on OpenAI: Deactivated due to main plugin not active');
        wp_die('SEO Analyzer Add-on OpenAI requires the SEO Analyzer plugin to be installed and activated. Please install and activate SEO Analyzer first.');
    }
    
    error_log('SEO Analyzer Add-on OpenAI: Activation completed successfully');
}

// Update the check_main_plugin function
function seo_analyzer_addon_openai_check_main_plugin() {
    $seo_analyzer_plugin = find_seo_analyzer_plugin();
    if (!$seo_analyzer_plugin || !is_plugin_active($seo_analyzer_plugin)) {
        // Deactivate this add-on
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Add an admin notice
        add_action('admin_notices', 'seo_analyzer_addon_openai_admin_notice');
        
        // Remove the activation notice
        delete_option('seo_analyzer_addon_openai_was_active');
    }
    // Remove the else block that was adding the filter for the joke display
}

// Admin notice function
function seo_analyzer_addon_openai_admin_notice() {
    echo '<div class="error"><p>SEO Analyzer Add-on OpenAI has been deactivated because it requires the SEO Analyzer plugin to be installed and activated.</p></div>';
}

// Hook the check function to plugins_loaded instead of admin_init
remove_action('admin_init', 'seo_analyzer_addon_openai_check_main_plugin');
add_action('plugins_loaded', 'seo_analyzer_addon_openai_check_main_plugin');

// Update this function
function seo_analyzer_addon_openai_register_ajax() {
    require_once SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'public/class-seo-analyzer-addon-openai-public.php';
    $plugin_public = new SEO_Analyzer_Addon_OpenAI_Public(SEO_ANALYZER_ADDON_OPENAI_PLUGIN_NAME, SEO_ANALYZER_ADDON_OPENAI_VERSION);
    // No need to add any actions here
}
add_action('init', 'seo_analyzer_addon_openai_register_ajax');