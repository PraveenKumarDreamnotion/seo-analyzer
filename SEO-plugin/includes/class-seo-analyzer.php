<?php

class Seo_Analyzer {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        if (defined('SEO_ANALYZER_VERSION')) {
            $this->version = SEO_ANALYZER_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'seo-analyzer';

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once SEO_ANALYZER_PLUGIN_DIR . 'includes/class-seo-analyzer-loader.php';
        require_once SEO_ANALYZER_PLUGIN_DIR . 'admin/class-seo-analyzer-admin.php';
        require_once SEO_ANALYZER_PLUGIN_DIR . 'public/class-seo-analyzer-public.php';
        require_once SEO_ANALYZER_PLUGIN_DIR . 'includes/class-seo-analyzer-api.php';

        $this->loader = new Seo_Analyzer_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new Seo_Analyzer_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_and_build_fields');
    }

    private function define_public_hooks() {
        $plugin_public = new Seo_Analyzer_Public($this->get_plugin_name(), $this->get_version());
        $api_handler = new Seo_Analyzer_API();

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_shortcode('seo_analyzer', $plugin_public, 'seo_analyzer_shortcode');
        
        $this->loader->add_filter('seo_analyzer_api_params', $api_handler, 'modify_api_params');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}