<?php

class SEO_Analyzer_Addon_OpenAI_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        // Enqueue admin styles
    }

    public function enqueue_scripts() {
        // Enqueue admin scripts
    }

    public function add_options_page() {
        add_options_page(
            'SEO Analyzer Add-on OpenAI Settings',
            'SEO Analyzer OpenAI',
            'manage_options',
            'seo-analyzer-addon-openai',
            array($this, 'display_options_page')
        );
    }

    public function display_options_page() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/seo-analyzer-addon-openai-admin-display.php';
    }

    public function register_settings() {
        register_setting('seo_analyzer_addon_openai_options', SEO_ANALYZER_ADDON_OPENAI_API_KEY_OPTION);
        
        add_settings_section(
            'seo_analyzer_addon_openai_general',
            __('OpenAI API Settings', 'seo-analyzer-add-on-openai'),
            array($this, 'settings_section_callback'),
            'seo_analyzer_addon_openai'
        );

        add_settings_field(
            'seo_analyzer_addon_openai_api_key',
            __('OpenAI API Key', 'seo-analyzer-add-on-openai'),
            array($this, 'api_key_field_callback'),
            'seo_analyzer_addon_openai',
            'seo_analyzer_addon_openai_general'
        );
    }

    public function settings_section_callback() {
        echo '<p>' . __('Enter your OpenAI API settings below:', 'seo-analyzer-add-on-openai') . '</p>';
    }

    public function api_key_field_callback() {
        $api_key = seo_analyzer_addon_openai_get_api_key();
        echo '<input type="text" name="' . SEO_ANALYZER_ADDON_OPENAI_API_KEY_OPTION . '" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your OpenAI API key here.', 'seo-analyzer-add-on-openai') . '</p>';
    }

    public function save_api_key() {
        if (isset($_POST['openai_api_key'])) {
            $api_key = sanitize_text_field($_POST['openai_api_key']);
            update_option('seo_analyzer_openai_api_key', $api_key);
        }
    }
}