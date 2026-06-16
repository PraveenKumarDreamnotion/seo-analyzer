<?php

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete all options created by the add-on
delete_option('seo_analyzer_addon_openai_api_key');
delete_option('seo_analyzer_addon_openai_option');

// If there are any transients, delete them
delete_transient('seo_analyzer_addon_openai_transient');

// If there are any custom database tables, drop them
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}seo_analyzer_addon_openai_table");

// Clear any scheduled cron jobs
wp_clear_scheduled_hook('seo_analyzer_addon_openai_cron_job');

// Optionally, you can add a notice to the admin panel
add_action('admin_notices', function() {
    echo '<div class="notice notice-warning is-dismissible"><p>SEO Analyzer Add-on OpenAI has been uninstalled. If you were using its features, please review your SEO settings.</p></div>';
});