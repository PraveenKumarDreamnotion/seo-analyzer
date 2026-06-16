<?php

class SEO_Analyzer_Addon_OpenAI {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->plugin_name = 'seo-analyzer-add-on-openai';
        $this->version = SEO_ANALYZER_ADDON_OPENAI_VERSION;
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        
        // Add AJAX handler for Brevo submission
        add_action('wp_ajax_save_user_choice_to_brevo', array($this, 'save_user_choice_to_brevo'));
        add_action('wp_ajax_nopriv_save_user_choice_to_brevo', array($this, 'save_user_choice_to_brevo'));
    }

    private function load_dependencies() {
        require_once SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'includes/class-seo-analyzer-addon-openai-loader.php';
        require_once SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'admin/class-seo-analyzer-addon-openai-admin.php';
        require_once SEO_ANALYZER_ADDON_OPENAI_PLUGIN_DIR . 'public/class-seo-analyzer-addon-openai-public.php';

        $this->loader = new SEO_Analyzer_Addon_OpenAI_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new SEO_Analyzer_Addon_OpenAI_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_options_page');
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
    }

    private function define_public_hooks() {
        $plugin_public = new SEO_Analyzer_Addon_OpenAI_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        // Remove the following lines:
        // $this->loader->add_filter('the_content', $plugin_public, 'display_random_joke');
        // $this->loader->add_action('wp_ajax_get_random_joke', $plugin_public, 'get_random_joke');
        // $this->loader->add_action('wp_ajax_nopriv_get_random_joke', $plugin_public, 'get_random_joke');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }

    public function save_user_choice_to_brevo() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'seo_analyzer_brevo_nonce')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        $email = sanitize_email($_POST['email']);
        $brevo_api_key = get_option('seo_analyzer_brevo_api_key', '');
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        $choice = sanitize_text_field($_POST['choice']);

        // Prepare the contact data
        $contact_data = array(
            'email' => $email,
            'listIds' => array(9),
            'updateEnabled' => true,
            'attributes' => array(
                'CHOICE' => $choice === 'report' ? 'Complete Report' : 'Expert Assistance',
                'SOURCE' => 'SEO Analyzer Plugin',
                'URL' => $url,
                'DATE' => date('Y-m-d H:i:s')
            )
        );

        // Create or update contact using the contacts endpoint
        $response = wp_remote_post('https://api.brevo.com/v3/contacts', array(
            'headers' => array(
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'api-key' => $brevo_api_key
            ),
            'body' => json_encode($contact_data)
        ));

        if (is_wp_error($response)) {
            error_log('SEO Analyzer Brevo API Error: ' . $response->get_error_message());
            wp_send_json_error(array('message' => 'Failed to connect to Brevo'));
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        // 201: Created, 204: Updated
        if ($response_code === 201 || $response_code === 204) {
            wp_send_json_success(array('message' => 'Contact saved successfully'));
        } else {
            error_log('SEO Analyzer Brevo API Error: ' . print_r($response_body, true));
            wp_send_json_error(array('message' => 'Failed to save contact'));
        }
    }
}