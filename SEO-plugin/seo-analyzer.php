<?php
/**
 * Plugin Name: SEO Analyzer
 * Plugin URI: https://thrivemattic.com/ai-tools/seo-analysis/
 * Description: Halo Audit. Scores one page for on-page SEO, technical health, AI readiness and speed.
 * Version: 2.0.0
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: thrivemattic
 * Author URI: https://thrivemattic.com/
 * Text Domain: seo-analyzer
 */

if (!defined('WPINC')) {
    die;
}

define('SEO_ANALYZER_VERSION', '2.0.0');
define('SEO_ANALYZER_PLUGIN_FILE', __FILE__);
define('SEO_ANALYZER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SEO_ANALYZER_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!file_exists(SEO_ANALYZER_PLUGIN_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>SEO Analyzer: run <code>composer install --no-dev</code> in the plugin folder.</p></div>';
    });
    return;
}

require_once SEO_ANALYZER_PLUGIN_DIR . 'vendor/autoload.php';
require_once SEO_ANALYZER_PLUGIN_DIR . 'includes/class-seo-analyzer-mailer.php';

\HaloAudit\Wp\Plugin::boot();
