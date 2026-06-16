<?php
/**
 * Plugin Name: SEO Analyzer
 * Plugin URI: https://thrivemattic.com/seo-analysis/
 * Description: A comprehensive SEO analysis tool for WordPress sites.
 * Version: 1.0.1
 * Author: thrivemattic
 * Author URI: https://thrivemattic.com/
 * Text Domain: seo-analyzer
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('SEO_ANALYZER_VERSION', '1.0.1');
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

/**
 * Remove all options and transients related to the plugin
 */
function seo_analyzer_cleanup_database() {
    global $wpdb;
    
    // Remove options
    delete_option('seo_analyzer_menu_created');
    delete_option('seo_analyzer_pagespeed_api_key');
    
    // Remove capabilities
    $role = get_role('administrator');
    if ($role) {
        $role->remove_cap('seo_analyzer_access');
    }
    
    // Remove user meta data
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '%seo_analyzer%'");
    
    // Clear transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%seo_analyzer%'");
}

// Uncomment the following line to run the cleanup, then comment it out again after use
add_action('admin_init', 'seo_analyzer_cleanup_database');

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

add_action('wp_ajax_verify_otp_and_save_data', 'seo_analyzer_verify_otp_and_save_data');
add_action('wp_ajax_nopriv_verify_otp_and_save_data', 'seo_analyzer_verify_otp_and_save_data');

function seo_analyzer_verify_otp_and_save_data() {
    check_ajax_referer('seo_analyzer_nonce', 'nonce');

    error_log('verify_otp_and_save_data function called');

    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $otp = sanitize_text_field($_POST['otp']);
    $widget_id = sanitize_text_field($_POST['widget_id']);
    $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
    $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
    $competitor_url = isset($_POST['competitor_url']) ? esc_url_raw($_POST['competitor_url']) : '';
    $results = json_decode(stripslashes($_POST['results']), true);
    $competitor_results = isset($_POST['competitor_results']) ? json_decode(stripslashes($_POST['competitor_results']), true) : null;
    $competitor_overall_score = isset($_POST['competitor_overall_score']) ? floatval($_POST['competitor_overall_score']) : null;

    error_log('Received data: ' . print_r($_POST, true));

    // Verify OTP
    $otp_verified = verify_otp($email, $otp, $widget_id);

    if ($otp_verified) {
        error_log('OTP verified successfully');
        error_log('Full results: ' . print_r($results, true));
        error_log('Competitor results: ' . print_r($competitor_results, true));
        error_log('Competitor overall score: ' . $competitor_overall_score);

        $admin = new Seo_Analyzer_Admin('seo-analyzer', '1.0.0');
        
        $report_id = $admin->save_report(
            $name,
            $email,
            $url,
            $keyword,
            $competitor_url,
            $results,
            $competitor_results,
            $competitor_overall_score
        );

        if ($report_id === false) {
            error_log('Failed to save data to the database');
            wp_send_json_error('Failed to save data to the database');
        } else {
            error_log('Data saved successfully. Report ID: ' . $report_id);
            wp_send_json_success(array('message' => 'Data saved successfully', 'report_id' => $report_id));
        }
    } else {
        error_log('Invalid OTP');
        wp_send_json_error('Invalid OTP');
    }
}

// Add this line to include Composer's autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

// Add after the other require_once statements
require_once plugin_dir_path(__FILE__) . 'includes/class-seo-analyzer-mailer.php';
