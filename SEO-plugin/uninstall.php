<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

foreach (['seo_analyzer_results', 'seo_analyzer_otp', 'seo_analyzer_api_keys'] as $table) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . $table);
}

foreach ([
    'halo_audit_db_version',
    'halo_audit_psi_key',
    'halo_audit_scan_url',
    'halo_audit_scan_secret',
    'halo_audit_mail_failed_at',
    'seo_analyzer_brevo_api_key',
    'seo_analyzer_sender_email',
    'seo_analyzer_openai_api_key',
    'seo_analyzer_redirect_url',
    'seo_analyzer_pagespeed_api_key',
    'seo_analyzer_menu_created',
] as $option) {
    delete_option($option);
}

wp_clear_scheduled_hook('halo_audit_purge');
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_halo\_rl\_%' OR option_name LIKE '\_transient\_timeout\_halo\_rl\_%'");
