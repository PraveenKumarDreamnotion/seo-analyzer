<?php

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Access the database via SQL
global $wpdb;
$table_name = $wpdb->prefix . 'seo_analyzer_results';

// Drop the table
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Delete options
delete_option('seo_analyzer_pagespeed_api_key');

// Clear any cached data
wp_cache_flush();

// If you have added any user meta, you can delete it for all users:
// $wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'seo_analyzer_%'");

// If you have added any post meta, you can delete it for all posts:
// $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE 'seo_analyzer_%'");

// If you have created any custom taxonomies, you might want to remove all the terms:
// $taxonomy = 'your_custom_taxonomy';
// $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
// foreach ($terms as $term) {
//     wp_delete_term($term->term_id, $taxonomy);
// }

// If you have created any custom post types, you might want to delete all posts of that type:
// $post_type = 'your_custom_post_type';
// $posts = get_posts(['post_type' => $post_type, 'numberposts' => -1]);
// foreach ($posts as $post) {
//     wp_delete_post($post->ID, true);
// }

// If you have created any files or directories, remove them
// $upload_dir = wp_upload_dir();
// $directory = $upload_dir['basedir'] . '/seo-analyzer-files/';
// if (is_dir($directory)) {
//     foreach (glob($directory.'*.*') as $v) {
//         unlink($v);
//     }
//     rmdir($directory);
// }

// Add this to your existing uninstall.php file
$table_name = $wpdb->prefix . 'seo_analyzer_api_keys';
$wpdb->query("DROP TABLE IF EXISTS $table_name");