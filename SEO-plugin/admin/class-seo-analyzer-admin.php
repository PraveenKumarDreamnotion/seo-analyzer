<?php

// Add this at the beginning of the file
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

use Mpdf\Mpdf;

class Seo_Analyzer_Admin {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add these lines to register the admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add this line to register settings
        add_action('admin_init', array($this, 'register_and_build_fields'));
        
        // Add this line to show the settings updated message
        add_action('admin_notices', array($this, 'show_settings_updated_message'));
        
        // Add this line to handle report actions
        add_action('admin_init', array($this, 'handle_report_actions'));
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/seo-analyzer-admin.css', array('dashicons'), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/seo-analyzer-admin.js', array('jquery'), $this->version, false);
        wp_add_inline_script($this->plugin_name, "
            jQuery(document).ready(function($) {
                $('form').on('submit', function() {
                    console.log('Form submitted');
                    console.log('API Key:', $('#seo_analyzer_pagespeed_api_key').val());
                });
            });
        ");
    }

    public function register_and_build_fields() {
        add_settings_section(
            'seo_analyzer_general_section',
            'General Settings',
            array($this, 'seo_analyzer_display_general_section'),
            'seo_analyzer_settings'
        );

        add_settings_field(
            'seo_analyzer_pagespeed_api_key',
            'PageSpeed API Key',
            array($this, 'seo_analyzer_render_pagespeed_api_key_field'),
            'seo_analyzer_settings',
            'seo_analyzer_general_section'
        );

        register_setting(
            'seo_analyzer_settings',
            'seo_analyzer_pagespeed_api_key',
            array($this, 'validate_pagespeed_api_key')
        );

        // Add this new section
        $this->add_openai_api_key_field();

        // Add this line after $this->add_openai_api_key_field();
        $this->add_brevo_fields();
    }

    private function add_openai_api_key_field() {
        // Check if the SEO Analyzer Add-on OpenAI plugin is active
        if (is_plugin_active('seo-analyzer-add-on-openai/seo-analyzer-add-on-openai.php')) {
            add_settings_field(
                'seo_analyzer_openai_api_key',
                'OpenAI API Key',
                array($this, 'render_openai_api_key_field'),
                'seo_analyzer_settings',
                'seo_analyzer_general_section'
            );

            register_setting(
                'seo_analyzer_settings',
                'seo_analyzer_openai_api_key',
                array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'default' => ''
                )
            );
        }

        // Add redirect URL field
        add_settings_field(
            'seo_analyzer_redirect_url',
            'Redirect URL for Timeout',
            array($this, 'render_redirect_url_field'),
            'seo_analyzer_settings',
            'seo_analyzer_general_section'
        );

        register_setting(
            'seo_analyzer_settings',
            'seo_analyzer_redirect_url',
            array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_redirect_url'),
                'default' => ''
            )
        );
    }

    private function add_brevo_fields() {
        // Add Brevo API key field
        add_settings_field(
            'seo_analyzer_brevo_api_key',
            'Brevo API Key',
            array($this, 'render_brevo_api_key_field'),
            'seo_analyzer_settings',
            'seo_analyzer_general_section'
        );

        register_setting(
            'seo_analyzer_settings',
            'seo_analyzer_brevo_api_key',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => ''
            )
        );

        // Add Sender Email field
        add_settings_field(
            'seo_analyzer_sender_email',
            'Sender Email',
            array($this, 'render_sender_email_field'),
            'seo_analyzer_settings',
            'seo_analyzer_general_section'
        );

        register_setting(
            'seo_analyzer_settings',
            'seo_analyzer_sender_email',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'default' => get_option('admin_email')
            )
        );
    }

    public function render_openai_api_key_field() {
        $openai_api_key = get_option('seo_analyzer_openai_api_key');
        echo '<input type="text" id="seo_analyzer_openai_api_key" name="seo_analyzer_openai_api_key" value="' . esc_attr($openai_api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your OpenAI API key. This is required for the SEO Analyzer Add-on OpenAI functionality.</p>';
    }

    public function render_redirect_url_field() {
        $redirect_url = get_option('seo_analyzer_redirect_url', '');
        ?>
        <input type="text" 
               id="seo_analyzer_redirect_url" 
               name="seo_analyzer_redirect_url" 
               value="<?php echo esc_attr($redirect_url); ?>" 
               class="regular-text"
               placeholder="Enter URL or path (e.g., /other-tools or https://example.com)">
        <p class="description">Enter the URL or path where users will be redirected when clicking 'Explore another tool' in the timeout popup. You can use a full URL (https://example.com) or a relative path (/page).</p>
        <?php
    }

    public function render_brevo_api_key_field() {
        $api_key = get_option('seo_analyzer_brevo_api_key');
        ?>
        <input type="password" 
               id="seo_analyzer_brevo_api_key" 
               name="seo_analyzer_brevo_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text"
               autocomplete="new-password">
        <p class="description">
            <?php _e('Enter your Brevo API v3 key. You can find this in your Brevo account under SMTP & API > API Keys.', 'seo-analyzer'); ?>
            <a href="https://app.brevo.com/settings/keys/api" target="_blank"><?php _e('Get API Key', 'seo-analyzer'); ?></a>
        </p>
        <?php
    }

    public function render_sender_email_field() {
        $sender_email = get_option('seo_analyzer_sender_email', get_option('admin_email'));
        ?>
        <input type="email" 
               id="seo_analyzer_sender_email" 
               name="seo_analyzer_sender_email" 
               value="<?php echo esc_attr($sender_email); ?>" 
               class="regular-text">
        <p class="description">
            <?php _e('Enter the email address that will be used as the sender for all emails sent by the plugin.', 'seo-analyzer'); ?>
        </p>
        <?php
    }

    public function seo_analyzer_render_pagespeed_api_key_field() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_api_keys';
        
        $pagespeed_api_key = $wpdb->get_var("SELECT api_key FROM $table_name LIMIT 1");
        
        echo '<input type="text" id="seo_analyzer_pagespeed_api_key" name="seo_analyzer_pagespeed_api_key" value="' . esc_attr($pagespeed_api_key) . '" class="regular-text" />';
        echo '<p class="description">Enter your Google PageSpeed Insights API key. You can get one from the <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank">Google Developers Console</a>.</p>';
    }

    public function seo_analyzer_display_general_section() {
        echo '<p>Enter your API keys here. You can get an API key from your account dashboard.</p>';
    }

    public function export_user_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

        if (!empty($results)) {
            $filename = 'seo_analyzer_user_data_' . date('Y-m-d') . '.csv';
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            // Output header row
            fputcsv($output, array_keys($results[0]));

            // Output data rows
            foreach ($results as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } else {
            wp_die('No data to export.');
        }
    }

    public function email_report($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $user_id), ARRAY_A);

        if ($result) {
            $to = $result['email'];
            $subject = 'Your SEO Analysis Report';
            $message = $this->generate_report_html($result);
            $headers = array('Content-Type: text/html; charset=UTF-8');

            wp_mail($to, $subject, $message, $headers);
        } else {
            wp_die('Report not found.');
        }
    }

    public function download_pdf_report($user_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $user_id), ARRAY_A);

        if ($result) {
            $mpdf = new Mpdf();
            
            // Add custom CSS
            $stylesheet = file_get_contents(plugin_dir_path(__FILE__) . '../assets/css/seo-analyzer-pdf.css');
            $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);

            $html = $this->generate_report_html($result);
            $mpdf->WriteHTML($html);

            $mpdf->Output('seo_analysis_report.pdf', 'D');
        } else {
            wp_die('Report not found.');
        }
    }

    public function download_report($report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $report_id), ARRAY_A);

        if (!$report) {
            wp_die('Report not found.');
        }

        $results = json_decode($report['results'], true);
        $competitor_results = json_decode($report['competitor_results'], true);

        $filename = 'seo_analysis_report_' . $report_id . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Write headers
        fputcsv($output, array('Category', 'Your Score', 'Factor', 'Your Factor Score', 'Your Explanation', 'Your Recommendation', 'AI-Generated Recommendation', 'Competitor Score', 'Competitor Factor Score'));

        // Write overall scores
        fputcsv($output, array(
            'Overall', 
            $report['overall_score'], 
            'Overall Score',
            '',
            '',
            '',
            '',
            $report['competitor_overall_score'],
            ''
        ));

        // Write category scores and factors
        foreach ($results['category_scores'] as $category => $data) {
            // Write category score
            fputcsv($output, array(
                $category, 
                $data['score'], 
                'Category Score',
                '',
                '',
                '',
                '',
                $competitor_results['category_scores'][$category]['score'] ?? '',
                ''
            ));
            
            // Write factors
            if (isset($data['factors'])) {
                foreach ($data['factors'] as $factor) {
                    $competitor_factor = $this->find_competitor_factor($competitor_results, $category, $factor['name']);
                    
                    // Format AI recommendation
                    $ai_recommendation = '';
                    if (isset($factor['ai_recommendation']['content'])) {
                        $content = $factor['ai_recommendation']['content'];
                        
                        // Convert HTML list items to text with bullet points
                        $content = preg_replace('/<li[^>]*>/i', '# ', $content);
                        $content = strip_tags($content);
                        
                        // Clean up extra whitespace and line breaks
                        $lines = preg_split('/\r\n|\r|\n/', $content);
                        $formatted_lines = array();
                        
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                // Add bullet point if line doesn't start with one
                                if (!str_starts_with($line, '#')) {
                                    $line = '# ' . $line;
                                }
                                $formatted_lines[] = $line;
                            }
                        }
                        
                        // Join lines with proper line breaks
                        $ai_recommendation = implode("\n", $formatted_lines);
                    }

                    fputcsv($output, array(
                        '',  // Category (left blank for factors)
                        '',  // Your Score (left blank for factors)
                        $factor['name'],
                        $factor['score'],
                        $factor['explanation'],
                        $factor['recommendation'],
                        $ai_recommendation,
                        '',  // Competitor category score (left blank for factors)
                        $competitor_factor ? $competitor_factor['score'] : ''
                    ));
                }
            }

            // Add an empty line after each category
            fputcsv($output, array());
        }

        fclose($output);
        exit;
    }

    // Add this new helper method
    private function find_competitor_factor($competitor_results, $category, $factor_name) {
        if (isset($competitor_results['category_scores'][$category]['factors'])) {
            foreach ($competitor_results['category_scores'][$category]['factors'] as $factor) {
                if ($factor['name'] === $factor_name) {
                    return $factor;
                }
            }
        }
        return null;
    }

    public function generate_pdf($report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $report_id), ARRAY_A);

        if ($report) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'vendor/autoload.php';

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16,
                'margin_header' => 9,
                'margin_footer' => 9
            ]);

            $results = json_decode($report['results'], true);
            $competitor_results = json_decode($report['competitor_results'], true);

            $html = $this->generate_report_html($report);

            $mpdf->WriteHTML($html);

            $mpdf->Output('seo_analysis_report.pdf', 'D');
        } else {
            wp_die('Report not found.');
        }
    }

    private function generate_report_html($report) {
        $results = json_decode($report['results'], true);
        $competitor_results = json_decode($report['competitor_results'], true);

        ob_start();
        ?>
        <h1>SEO Analysis Report</h1>
        <p><strong>URL:</strong> <?php echo esc_html($report['url']); ?></p>
        <p><strong>Keyword:</strong> <?php echo esc_html($report['keyword']); ?></p>
        <p><strong>Overall Score:</strong> <?php echo esc_html($report['overall_score']); ?></p>

        <h2>SEO Analysis</h2>
        <p><?php echo esc_html($results['openai_summary']); ?></p>

        <h2>Category Scores</h2>
        <table>
            <tr>
                <th>Category</th>
                <th>Score</th>
                <?php if ($competitor_results): ?>
                    <th>Competitor Score</th>
                <?php endif; ?>
            </tr>
            <?php foreach ($results['category_scores'] as $category => $data): ?>
                <tr>
                    <td><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></td>
                    <td class="<?php echo $this->get_score_class($data['score']); ?>"><?php echo esc_html($data['score']); ?></td>
                    <?php if ($competitor_results): ?>
                        <td class="<?php echo $this->get_score_class($competitor_results['category_scores'][$category]['score']); ?>"><?php echo esc_html($competitor_results['category_scores'][$category]['score']); ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>

        <h2>Detailed Results</h2>
        <?php if (isset($results['openai_summary'])): ?>
            <h3>Content Analysis</h3>
            <p><?php echo esc_html($results['openai_summary']); ?></p>
        <?php endif; ?>

        <?php foreach ($results['category_scores'] as $category => $data): ?>
            <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $category))); ?></h3>
            <table>
                <tr>
                    <th>Factor</th>
                    <th>Score</th>
                    <th>Recommendation</th>
                </tr>
                <?php foreach ($data['factors'] as $factor): ?>
                    <tr>
                        <td><?php echo esc_html($factor['name']); ?></td>
                        <td class="<?php echo $this->get_score_class($factor['score']); ?>">
                            <?php echo esc_html($factor['score']); ?>
                            <br>
                            <small>(<?php echo esc_html($factor['explanation']); ?>)</small>
                        </td>
                        <td><?php echo esc_html($factor['ai_recommendation']['content'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endforeach; ?>

        <?php
        return ob_get_clean();
    }

    private function get_progress_bar_class($score) {
        if ($score >= 80) return 'progress-bar-green';
        if ($score >= 60) return 'progress-bar-yellow';
        if ($score >= 40) return 'progress-bar-orange';
        return 'progress-bar-red';
    }

    private function get_score_class($score) {
        if ($score >= 80) return 'score-good';
        if ($score >= 60) return 'score-average';
        return 'score-poor';
    }

    // Add a method to create the database table if it doesn't exist
    public function create_database_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            url varchar(255) NOT NULL,
            keyword varchar(100) NOT NULL,
            competitor_url varchar(255),
            results longtext NOT NULL,
            overall_score float,
            competitor_overall_score float,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // Add this method to the plugin activation hook
    public static function activate() {
        $admin = new self('seo-analyzer', '1.0.0');
        $admin->create_database_table();
        $admin->create_api_key_table();
    }

    public function validate_pagespeed_api_key($input) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_api_keys';

        $new_input = sanitize_text_field($input);
        
        error_log('Validating PageSpeed API Key: ' . $new_input);
        
        if (empty($new_input)) {
            add_settings_error(
                'seo_analyzer_pagespeed_api_key',
                'seo_analyzer_pagespeed_api_key_error',
                'The PageSpeed API Key cannot be empty.',
                'error'
            );
            error_log('PageSpeed API Key validation failed: Empty input');
            return '';
        }
        
        // Check if the table exists, if not create it
        $this->create_api_key_table();

        // Check if there's an existing key
        $existing_key = $wpdb->get_var("SELECT api_key FROM $table_name LIMIT 1");

        if ($existing_key) {
            // Update existing key
            $wpdb->update(
                $table_name,
                array('api_key' => $new_input),
                array('id' => 1)
            );
        } else {
            // Insert new key
            $wpdb->insert(
                $table_name,
                array('api_key' => $new_input)
            );
        }

        error_log('PageSpeed API Key validation successful. Saving: ' . $new_input);
        return $new_input;
    }

    public function show_settings_updated_message() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            $message = __('Settings saved successfully.', 'seo-analyzer');
            echo "<div class='notice notice-success is-dismissible'><p>$message</p></div>";
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'SEO Analyzer',
            'SEO Analyzer',
            'manage_options',
            'seo-analyzer',
            array($this, 'display_main_page'),
            'dashicons-chart-area',
            6
        );

        add_submenu_page(
            'seo-analyzer',
            'SEO Analyzer Settings',
            'Settings',
            'manage_options',
            'seo-analyzer-settings',
            array($this, 'display_settings_page')
        );

        add_submenu_page(
            'seo-analyzer',
            'SEO Analysis Reports',
            'Reports',
            'manage_options',
            'seo-analyzer-reports',
            array($this, 'display_reports_page')
        );
    }

    public function display_main_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/seo-analyzer-admin-display.php';
    }

    public function display_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors(); ?>
            <form action="options.php" method="post">
            <?php
                settings_fields('seo_analyzer_settings');
                do_settings_sections('seo_analyzer_settings');
                submit_button('Save Settings');
            ?>
            </form>
        </div>
        <?php
    }

    public function display_reports_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        // Get all reports
        $reports = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);

        require_once plugin_dir_path(__FILE__) . 'partials/seo-analyzer-reports-display.php';
    }

    public function create_api_key_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_api_keys';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            api_key varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function handle_report_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'seo-analyzer-reports') {
            return;
        }

        if (isset($_GET['action']) && isset($_GET['id'])) {
            $action = $_GET['action'];
            $id = intval($_GET['id']);

            switch ($action) {
                case 'view':
                    $this->view_report($id);
                    break;
                case 'download':
                    $this->download_report($id);
                    exit; // Make sure to exit after sending the file
                case 'email':
                    $this->email_report($id);
                    break;
            }
        }
    }

    private function view_report($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';
        $report = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id), ARRAY_A);

        if (!$report) {
            wp_die('Report not found.');
        }

        require_once plugin_dir_path(__FILE__) . 'partials/seo-analyzer-report-view.php';
        exit;
    }

    // Add this new method to save reports
    public function save_report($name, $email, $url, $keyword, $competitor_url, $results, $competitor_results, $competitor_overall_score = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        // Ensure the table exists
        $this->create_results_table();

        // Debug logging
        error_log('Save report function called');
        error_log('Results: ' . print_r($results, true));
        error_log('Competitor results: ' . print_r($competitor_results, true));
        error_log('Competitor overall score: ' . $competitor_overall_score);

        // Extract overall scores
        $overall_score = isset($results['overall_score']) ? $results['overall_score'] : null;

        // Prepare the data to be inserted
        $data = array(
            'name' => $name,
            'email' => $email,
            'url' => $url,
            'keyword' => $keyword,
            'competitor_url' => $competitor_url,
            'results' => json_encode($results),
            'competitor_results' => $competitor_results ? json_encode($competitor_results) : null,
            'overall_score' => $overall_score,
            'competitor_overall_score' => $competitor_overall_score,
            'created_at' => current_time('mysql')
        );

        // Debug logging
        error_log('Data to be inserted: ' . print_r($data, true));

        // Insert the data into the database
        $inserted = $wpdb->insert($table_name, $data, array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s'));

        // Debug logging
        error_log('Last SQL query: ' . $wpdb->last_query);
        error_log('Last SQL error: ' . $wpdb->last_error);

        if ($inserted === false) {
            error_log('Failed to insert data into the database');
            return false;
        }

        // Return the ID of the newly inserted row
        return $wpdb->insert_id;
    }

    public function get_reports() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_reports';
        $query = "SELECT id, name, email, url, keyword, competitor_url, overall_score, competitor_overall_score, created_at FROM $table_name ORDER BY created_at DESC";
        return $wpdb->get_results($query, ARRAY_A);
    }

    public function create_results_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';
        
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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

    public function sanitize_redirect_url($input) {
        // Allow empty value or '/'
        if (empty($input) || $input === '/') {
            return $input;
        }

        // If it's a full URL, sanitize it
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            return esc_url_raw($input);
        }

        // If it starts with a slash, it's a relative path
        if (strpos($input, '/') === 0) {
            return $input;
        }

        // For any other input, ensure it starts with a slash
        return '/' . ltrim($input, '/');
    }

    private function get_pagespeed_data($url, $retry_count = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_api_keys';
        $max_retries = 2; // Maximum number of retry attempts
        $retry_delay = 3; // Delay in seconds between retries
        
        try {
            $api_key = $wpdb->get_var("SELECT api_key FROM $table_name LIMIT 1");
            error_log('SEO Analyzer: Starting PageSpeed analysis for URL: ' . $url . ' (Attempt ' . ($retry_count + 1) . ')');

            if (!$api_key) {
                error_log('SEO Analyzer: No API key found in database');
                return [
                    'score' => 0,
                    'message' => 'PageSpeed API key not found. Please set it in the plugin settings.',
                    'factors' => []
                ];
            }

            // ... existing URL cleaning code ...

            $response = wp_remote_get($api_url, array(
                'timeout' => 90,
                'sslverify' => true,
                'user-agent' => 'SEO Analyzer Bot/1.0',
                'headers' => array(
                    'Accept' => 'application/json',
                    'Cache-Control' => 'no-cache'
                )
            ));

            // Handle WP_Error properly
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                error_log('SEO Analyzer: PageSpeed API Error - ' . $error_message);
                
                if ($retry_count < $max_retries) {
                    error_log('SEO Analyzer: Retrying PageSpeed API request after error (Attempt ' . ($retry_count + 2) . ')');
                    sleep($retry_delay);
                    return $this->get_pagespeed_data($url, $retry_count + 1);
                }
                
                return [
                    'score' => 0,
                    'message' => 'Error fetching PageSpeed data: ' . $error_message,
                    'factors' => []
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            // Handle various response codes
            if ($response_code === 500 || $response_code !== 200) {
                $error_message = 'PageSpeed API returned status code ' . $response_code;
                error_log('SEO Analyzer: ' . $error_message);
                
                if ($retry_count < $max_retries) {
                    error_log('SEO Analyzer: Retrying PageSpeed API request after ' . $response_code . ' (Attempt ' . ($retry_count + 2) . ')');
                    sleep($retry_delay);
                    return $this->get_pagespeed_data($url, $retry_count + 1);
                }
                
                return [
                    'score' => 0,
                    'message' => $error_message . '. Please try again.',
                    'factors' => []
                ];
            }

            // ... existing response processing code ...

            $data = json_decode($response_body, true);
            
            // Check if we got a valid score
            if (isset($data['lighthouseResult']['categories']['performance']['score'])) {
                $performance_score = round($data['lighthouseResult']['categories']['performance']['score'] * 100);
                
                // If score is 0 and we haven't reached max retries, try again
                if ($performance_score === 0 && $retry_count < $max_retries) {
                    error_log('SEO Analyzer: Got 0% performance score, retrying (Attempt ' . ($retry_count + 2) . ')');
                    sleep($retry_delay);
                    return $this->get_pagespeed_data($url, $retry_count + 1);
                }
            }

            // Continue with existing code for processing results...
            
            return [
                'score' => round($performance_score),
                'message' => "Page Performance Score: $performance_score",
                'factors' => $factors
            ];

        } catch (Exception $e) {
            error_log('SEO Analyzer: Exception in PageSpeed analysis - ' . $e->getMessage());
            
            if ($retry_count < $max_retries) {
                error_log('SEO Analyzer: Retrying PageSpeed API request after exception (Attempt ' . ($retry_count + 2) . ')');
                sleep($retry_delay);
                return $this->get_pagespeed_data($url, $retry_count + 1);
            }
            
            return [
                'score' => 0,
                'message' => 'Error analyzing page performance: ' . $e->getMessage(),
                'factors' => []
            ];
        }
    }
}// Register the activation hook in your main plugin file
register_activation_hook(__FILE__, array('Seo_Analyzer_Admin', 'activate'));

