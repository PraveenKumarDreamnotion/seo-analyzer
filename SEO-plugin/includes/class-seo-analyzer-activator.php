<?php

class Seo_Analyzer_Activator {

    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            url varchar(255) NOT NULL,
            keyword varchar(255) NOT NULL,
            competitor_url varchar(255),
            results longtext NOT NULL,
            competitor_results longtext,
            overall_score float,
            competitor_overall_score float,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}