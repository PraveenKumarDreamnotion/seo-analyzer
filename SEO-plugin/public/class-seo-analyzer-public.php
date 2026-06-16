<?php

class Seo_Analyzer_Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action('wp_ajax_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_nopriv_send_otp', array($this, 'send_otp'));
        add_action('wp_ajax_verify_otp_and_save_data', array($this, 'verify_otp_and_save_data'));
        add_action('wp_ajax_nopriv_verify_otp_and_save_data', array($this, 'verify_otp_and_save_data'));
        add_action('wp_ajax_test_wp_mail', array($this, 'test_wp_mail'));
        add_action('wp_ajax_nopriv_test_wp_mail', array($this, 'test_wp_mail'));
        add_action('wp_ajax_save_competitor_score', array($this, 'save_competitor_score'));
        add_action('wp_ajax_nopriv_save_competitor_score', array($this, 'save_competitor_score'));
        add_action('wp_ajax_save_competitor_data', array($this, 'save_competitor_data'));
        add_action('wp_ajax_nopriv_save_competitor_data', array($this, 'save_competitor_data'));
        add_action('wp_ajax_save_competitor_results', array($this, 'save_competitor_results'));
        add_action('wp_ajax_nopriv_save_competitor_results', array($this, 'save_competitor_results'));
        add_action('wp_ajax_save_competitor_overall_score', array($this, 'save_competitor_overall_score'));
        add_action('wp_ajax_nopriv_save_competitor_overall_score', array($this, 'save_competitor_overall_score'));
        add_action('wp_ajax_download_pdf_report', array($this, 'download_pdf_report'));
        add_action('wp_ajax_nopriv_download_pdf_report', array($this, 'download_pdf_report'));
        add_action('wp_ajax_fetch_page_title', array($this, 'fetch_page_title'));
        add_action('wp_ajax_nopriv_fetch_page_title', array($this, 'fetch_page_title'));

        // Ensure OTP table exists
        $this->create_otp_table();

        // Add this near the top of the file where other hooks are defined
        add_filter('pre_http_request', array($this, 'log_outgoing_requests'), 10, 3);
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . '/css/seo-analyzer-public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . '/js/seo-analyzer-public.js', array('jquery'), $this->version, true);
        wp_localize_script($this->plugin_name, 'seo_analyzer_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_analyzer_nonce')
        ));

        // Add this block to enqueue NLP Addon scripts when NLP is enabled
        if (function_exists('seo_analyzer_nlp_addon_enqueue_scripts')) {
            seo_analyzer_nlp_addon_enqueue_scripts();
        }

        // Pass the redirect URL to JavaScript
        $redirect_url = get_option('seo_analyzer_redirect_url', '');
        wp_add_inline_script($this->plugin_name, 
            'window.seoAnalyzerRedirectUrl = ' . json_encode($redirect_url) . ';', 
            'before'
        );
    }

    public function seo_analyzer_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'SEO Analyzer',
            'popup' => false,
            'comparison' => false,
            'nlp' => false,
            'competitor' => false  // Add this line
        ), $atts, 'seo_analyzer');

        $instance = uniqid();
        $popup = filter_var($atts['popup'], FILTER_VALIDATE_BOOLEAN);
        $comparison = filter_var($atts['comparison'], FILTER_VALIDATE_BOOLEAN);
        $nlp = filter_var($atts['nlp'], FILTER_VALIDATE_BOOLEAN);
        $competitor = filter_var($atts['competitor'], FILTER_VALIDATE_BOOLEAN);  // Add this line

        ob_start();
        ?>
        <div class="seo_analyzer_widget_outer">
            <div class="seo_analyzer_widget_div">
            <h2 class="seo_analyzer_widget_title"><?php echo esc_html($atts['title']); ?></h2>
                <div class="seo_analyzer_widget_outer_inner">
                    
        <div id="seo-analyzer-widget-<?php echo $instance; ?>" 
             class="seo-analyzer-widget" 
             data-popup="<?php echo $popup ? 'true' : 'false'; ?>" 
             data-comparison="<?php echo $comparison ? 'true' : 'false'; ?>"
             data-nlp="<?php echo $nlp ? 'true' : 'false'; ?>"
             data-competitor="<?php echo $competitor ? 'true' : 'false'; ?>"  // Add this line
             data-title="<?php echo esc_attr($atts['title']); ?>"
             data-shortcode-atts="<?php echo esc_attr(json_encode($atts)); ?>">
             <div class="seo-analyzer-form-outer">
            <form class="seo-analyzer-form">
                <div class="seo-analyzer-form-inner">
                <input type="text" name="url" class="seo-analyzer-url" placeholder="Enter Your URL" required>
                <?php if ($comparison): ?>
                <input type="text" name="competitor_url" class="seo-analyzer-competitor-url" placeholder="Enter Competitor URL" required>
                <?php endif; ?>
                <input type="text" name="keyword" class="seo-analyzer-keyword" placeholder="Enter primary keyword" required>
                </div>
                <button type="submit">Analyze</button>
            </form>
            <div class="seo-analyzer-loading" style="display:none;">
                <div class="progress-bar-container">
                    <div class="progress-bar"></div>
                </div>
                <div class="progress-text">Analyzing: <span class="progress-percentage">0%</span></div>
            </div>
            <div class="seo-analyzer-error" style="display:none;"></div>
                </div>
            <div class="seo-analyzer-results" style="display:none;">
                <!-- Results will be populated by JavaScript -->
            </div>
            <div class="nlp-result" style="display:none;">
                <!-- NLP results will be populated by JavaScript -->
            </div>
        </div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        
        // Add a filter to allow the NLP Addon to modify the content
        $content = apply_filters('seo_analyzer_after_form', $content);
        
        return $content;
    }

    public function perform_seo_analysis() {
        try {
            error_log('perform_seo_analysis method called');

            // Check nonce for security
            if (!check_ajax_referer('seo_analyzer_nonce', 'nonce', false)) {
                error_log('Nonce check failed');
                wp_send_json_error('Security check failed');
                return;
            }

            $url = esc_url_raw(trim($_POST['url']));
            $competitor_url = !empty($_POST['competitor_url']) ? esc_url_raw(trim($_POST['competitor_url'])) : '';
            $keyword = sanitize_text_field($_POST['keyword']);

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                wp_send_json_error('Invalid URL provided');
                return;
            }

            if (!empty($competitor_url) && !filter_var($competitor_url, FILTER_VALIDATE_URL)) {
                wp_send_json_error('Invalid competitor URL provided');
                return;
            }

            if (empty($keyword)) {
                wp_send_json_error('Invalid keyword provided');
                return;
            }

            error_log('Received data: URL: ' . $url . ', Competitor URL: ' . $competitor_url . ', Keyword: ' . $keyword);

            // Set longer PHP execution time for complex analyses
            set_time_limit(300); // 5 minutes
            
            // Increase memory limit if needed
            ini_set('memory_limit', '256M');
            
            $results = $this->analyze_seo($url, $keyword);
            
            if (!$results['success']) {
                wp_send_json_error($results['message']);
                return;
            }

            if ($competitor_url) {
                $competitor_results = $this->analyze_seo($competitor_url, $keyword);
                if ($competitor_results['success']) {
                    $results['competitor'] = $competitor_results;
                } else {
                    error_log('Competitor analysis failed: ' . $competitor_results['message']);
                }
            }

            // Apply OpenAI enhancement if the add-on is active
            if (function_exists('seo_analyzer_is_addon_openai_active') && seo_analyzer_is_addon_openai_active()) {
                try {
                    $openai_addon = new SEO_Analyzer_Addon_OpenAI_Public(SEO_ANALYZER_ADDON_OPENAI_PLUGIN_NAME, SEO_ANALYZER_ADDON_OPENAI_VERSION);
                    $enhanced_results = $openai_addon->enhance_seo_analysis($results, $url);
                    if ($enhanced_results) {
                        $results = $enhanced_results;
                    }
                } catch (Exception $e) {
                    error_log('OpenAI Enhancement Error: ' . $e->getMessage());
                    $results['openai_error'] = 'OpenAI analysis unavailable: ' . $e->getMessage();
                }
            }

            wp_send_json_success($results);

        } catch (Exception $e) {
            error_log('SEO Analysis Error: ' . $e->getMessage());
            wp_send_json_error('An error occurred during analysis: ' . $e->getMessage());
        }
    }

    private function get_pagespeed_data($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_api_keys';
        
        try {
            $api_key = $wpdb->get_var("SELECT api_key FROM $table_name LIMIT 1");
            error_log('SEO Analyzer: Starting PageSpeed analysis for URL: ' . $url);

            if (!$api_key) {
                error_log('SEO Analyzer: No API key found in database');
                return [
                    'score' => 0,
                    'message' => 'PageSpeed API key not found. Please set it in the plugin settings.',
                    'factors' => []
                ];
            }

            // Clean and validate URL
            $url = trim($url);
            $url = str_replace(array(' ', '\t', '\n', '\r'), '', $url);
            
            // Remove any fragments
            $url = preg_replace('/#.*$/', '', $url);
            
            // Remove multiple forward slashes
            $url = preg_replace('#(?<!:)//+#', '/', $url);
            
            // Ensure URL has protocol
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                $url = "https://" . $url;
            }

            // Additional URL validation
            $parsed_url = parse_url($url);
            if (!$parsed_url || !isset($parsed_url['host'])) {
                error_log('SEO Analyzer: Invalid URL structure - ' . $url);
                return [
                    'score' => 0,
                    'message' => 'Invalid URL structure. Please enter a valid website URL.',
                    'factors' => []
                ];
            }

            // Check for valid domain
            if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $parsed_url['host'])) {
                error_log('SEO Analyzer: Invalid domain name - ' . $parsed_url['host']);
                return [
                    'score' => 0,
                    'message' => 'Invalid domain name. Please enter a valid website URL.',
                    'factors' => []
                ];
            }

            // Reconstruct the URL
            $clean_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
            if (isset($parsed_url['path'])) {
                $clean_url .= $parsed_url['path'];
            }
            if (isset($parsed_url['query'])) {
                $clean_url .= '?' . $parsed_url['query'];
            }

            error_log('SEO Analyzer: Cleaned URL - ' . $clean_url);

            // Build API URL with all necessary parameters
            $api_url = add_query_arg(
                array(
                    'url' => $clean_url,
                    'key' => $api_key,
                    'strategy' => 'mobile',
                    'category' => array('performance', 'accessibility'),
                    'locale' => 'en'
                ),
                'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
            );

            error_log('SEO Analyzer: Calling PageSpeed API with URL: ' . str_replace($api_key, '[REDACTED]', $api_url));

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
                return [
                    'score' => 0,
                    'message' => 'Error fetching PageSpeed data: ' . $error_message,
                    'factors' => []
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            // Handle various response codes
            if ($response_code !== 200) {
                $error_message = 'PageSpeed API returned status code ' . $response_code;
                error_log('SEO Analyzer: ' . $error_message);
                return [
                    'score' => 0,
                    'message' => $error_message . '. Please try again.',
                    'factors' => []
                ];
            }

            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);
            
            error_log('SEO Analyzer: PageSpeed API Response Code: ' . $response_code);
            error_log('SEO Analyzer: PageSpeed API Response Headers: ' . print_r($response_headers, true));
            error_log('SEO Analyzer: PageSpeed API Response Body: ' . substr($response_body, 0, 1000) . '...'); // Log first 1000 chars

            if ($response_code === 403) {
                error_log('SEO Analyzer: API key unauthorized');
                return [
                    'score' => 0,
                    'message' => 'PageSpeed API key is not authorized. Please check your API key configuration.',
                    'factors' => []
                ];
            }

            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('SEO Analyzer: JSON decode error - ' . json_last_error_msg());
                return [
                    'score' => 0,
                    'message' => 'Error parsing PageSpeed response: ' . json_last_error_msg(),
                    'factors' => []
                ];
            }

            if (!$data || isset($data['error'])) {
                $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
                error_log('SEO Analyzer: Invalid PageSpeed data - ' . $error_message);
                error_log('SEO Analyzer: Full response data - ' . print_r($data, true));
                return [
                    'score' => 0,
                    'message' => 'Error analyzing page performance: ' . $error_message,
                    'factors' => []
                ];
            }

            if (!isset($data['lighthouseResult']['categories']['performance']['score'])) {
                error_log('SEO Analyzer: No performance score in PageSpeed response');
                error_log('SEO Analyzer: Available data structure - ' . print_r(array_keys($data), true));
                if (isset($data['lighthouseResult'])) {
                    error_log('SEO Analyzer: Lighthouse result structure - ' . print_r(array_keys($data['lighthouseResult']), true));
                }
                return [
                    'score' => 0,
                    'message' => 'Unable to calculate performance score. Please try analyzing again.',
                    'factors' => []
                ];
            }

            $performance_score = $data['lighthouseResult']['categories']['performance']['score'] * 100;
            error_log('SEO Analyzer: Performance score calculated: ' . $performance_score);

            $factors = [];
            $audits = $data['lighthouseResult']['audits'];

            $important_metrics = [
                'first-contentful-paint' => 'First Contentful Paint',
                'speed-index' => 'Speed Index',
                'largest-contentful-paint' => 'Largest Contentful Paint',
                'interactive' => 'Time to Interactive',
                'total-blocking-time' => 'Total Blocking Time',
                'cumulative-layout-shift' => 'Cumulative Layout Shift'
            ];

            foreach ($important_metrics as $metric_key => $metric_name) {
                if (isset($audits[$metric_key])) {
                    $metric = $audits[$metric_key];
                    $recommendation = $metric['description'];
                    
                    // Remove the "Learn more about..." part from the recommendation
                    $recommendation = preg_replace('/\s*\[Learn more about.*?\]\(.*?\)\.?/i', '', $recommendation);
                    
                    $metric_score = $metric['score'] * 100;
                    error_log("SEO Analyzer: Metric {$metric_name} score: {$metric_score}");
                    
                    $factors[] = [
                        'name' => $metric_name,
                        'score' => $metric_score,
                        'explanation' => $metric['title'],
                        'recommendation' => trim($recommendation)
                    ];
                } else {
                    error_log("SEO Analyzer: Metric {$metric_name} not found in audits");
                }
            }

            $result = [
                'score' => round($performance_score),
                'message' => "Page Performance Score: $performance_score",
                'factors' => $factors
            ];
            error_log('SEO Analyzer: Final PageSpeed result - ' . print_r($result, true));
            
            return $result;
        } catch (Exception $e) {
            error_log('SEO Analyzer: Exception in PageSpeed analysis - ' . $e->getMessage());
            return [
                'score' => 0,
                'message' => 'Error analyzing page performance: ' . $e->getMessage(),
                'factors' => []
            ];
        }
    }

    public function send_otp() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');

        $email = sanitize_email($_POST['email']);
        $widget_id = sanitize_text_field($_POST['widget_id']);

        if (!is_email($email)) {
            wp_send_json_error('Invalid email address');
            return;
        }

        $otp = wp_rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', current_time('timestamp') + (15 * 60)); // OTP valid for 15 minutes (WordPress-timezone-aware)

        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_otp';

        // Delete any existing OTP for this email and widget
        $wpdb->delete($table_name, array('email' => $email, 'widget_id' => $widget_id));

        // Insert new OTP
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'email' => $email,
                'widget_id' => $widget_id,
                'otp' => $otp,
                'expiry' => $expiry
            ),
            array('%s', '%s', '%s', '%s')
        );

        if ($inserted === false) {
            error_log('Failed to insert OTP into database: ' . $wpdb->last_error);
            wp_send_json_error('Failed to generate OTP. Please try again.');
            return;
        }

        $subject = 'Your SEO Analyzer OTP';
        $message = "Your OTP for SEO Analyzer is: $otp\n\nThis OTP will expire in 15 minutes.";

        $sent = Seo_Analyzer_Mailer::send_email($email, $subject, $message);

        if ($sent) {
            wp_send_json_success('OTP sent successfully');
        } else {
            wp_send_json_error('Failed to send OTP. Please try again.');
        }
    }

    public function verify_otp_and_save_data() {
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

        error_log('Received data: ' . print_r($_POST, true));

        global $wpdb;
        $otp_table_name = $wpdb->prefix . 'seo_analyzer_otp';

        $stored_otp_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $otp_table_name WHERE email = %s AND widget_id = %s AND otp = %s AND expiry > %s",
            $email, $widget_id, $otp, current_time('mysql')
        ));

        if (!$stored_otp_data) {
            error_log('Invalid or expired OTP');
            wp_send_json_error('Invalid or expired OTP');
            return;
        }

        error_log('OTP verified successfully');

        // OTP is valid, proceed with saving data
        $results_table_name = $wpdb->prefix . 'seo_analyzer_results';

        // Extract competitor results if they exist
        $competitor_results = isset($results['competitor']) ? $results['competitor'] : null;
        
        // Extract overall scores
        $overall_score = isset($results['overall_score']) ? $results['overall_score'] : null;
        $competitor_overall_score = isset($competitor_results['overall_score']) ? $competitor_results['overall_score'] : null;

        // Debug logging
        error_log('Full results: ' . print_r($results, true));
        error_log('Competitor results: ' . print_r($competitor_results, true));

        $data_to_insert = array(
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

        error_log('Attempting to insert data: ' . print_r($data_to_insert, true));

        $inserted = $wpdb->insert(
            $results_table_name,
            $data_to_insert,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s')
        );

        if ($inserted) {
            // Delete the OTP after successful verification
            $wpdb->delete($otp_table_name, array('email' => $email, 'widget_id' => $widget_id));
            
            $report_id = $wpdb->insert_id;
            
            error_log('Data inserted successfully. Report ID: ' . $report_id);
            
            wp_send_json_success(array(
                'message' => 'Data saved successfully',
                'report_id' => $report_id
            ));
        } else {
            error_log('Failed to insert data. MySQL error: ' . $wpdb->last_error);
            wp_send_json_error('Failed to save data: ' . $wpdb->last_error);
        }
    }

    private function create_otp_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_otp';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                email varchar(100) NOT NULL,
                widget_id varchar(50) NOT NULL,
                otp varchar(6) NOT NULL,
                expiry datetime NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY email_widget (email, widget_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    private function analyze_seo($url, $keyword) {
        try {
            $page_content_result = $this->fetch_page_content($url);
            if (!$page_content_result['success']) {
                return [
                    'success' => false,
                    'message' => $page_content_result['error'],
                    'category_scores' => [
                        'page_performance' => [
                            'score' => 0,
                            'message' => 'Could not fetch page content: ' . $page_content_result['error'],
                            'factors' => []
                        ]
                    ]
                ];
            }

            $page_content = $page_content_result['content'];
            $dom = new DOMDocument();
            @$dom->loadHTML($page_content);

            // Initialize results array with all required sections
            $results = [
                'success' => true,
                'url' => $url,
                'keyword' => $keyword,
                'category_scores' => [],
                'seo_analysis' => [],
                'content_analysis' => []
            ];

            // Fetch PageSpeed Insights data
            $pagespeed_data = $this->get_pagespeed_data($url);
            
            // Check if pagespeed_data is WP_Error
            if (is_wp_error($pagespeed_data)) {
                error_log('PageSpeed API Error: ' . $pagespeed_data->get_error_message());
                $results['category_scores']['page_performance'] = [
                    'score' => 0,
                    'message' => 'Unable to analyze page performance: ' . $pagespeed_data->get_error_message(),
                    'factors' => []
                ];
            } else {
                $results['category_scores']['page_performance'] = $pagespeed_data;
            }

            // Calculate other scores
            $scores = [
                'content_quality' => $this->analyze_content_quality($page_content, $keyword),
                'technical_seo' => $this->analyze_technical_seo($url, $page_content),
                'on_page_elements' => $this->analyze_on_page_elements($dom, $url, $keyword),
                'media_and_formatting' => $this->analyze_media_and_formatting($dom),
                'user_experience_and_accessibility' => $this->analyze_user_experience_and_accessibility($dom, $results['category_scores']['page_performance']),
                'links_and_structured_data' => $this->analyze_links_and_structured_data($dom, $url, $page_content),
                'security_and_social' => $this->analyze_security_and_social($url, $page_content),
                'page_performance' => $results['category_scores']['page_performance'],
                'llm_search_compatibility' => $this->analyze_llm_search_compatibility($url)
            ];

            $weights = [
                'content_quality' => 0.15,
                'technical_seo' => 0.15,
                'on_page_elements' => 0.15,
                'media_and_formatting' => 0.10,
                'user_experience_and_accessibility' => 0.10,
                'links_and_structured_data' => 0.05,
                'security_and_social' => 0.05,
                'page_performance' => 0.15,
                'llm_search_compatibility' => 0.10
            ];

            $overall_score = $this->calculate_overall_score($scores, $weights);
            $performance_tier = $this->get_performance_tier($overall_score);
            $recommendations = $this->generate_recommendations($scores);

            // Populate all sections of the results
            $results['overall_score'] = $overall_score;
            $results['performance_tier'] = $performance_tier;
            $results['category_scores'] = $scores;
            $results['recommendations'] = $recommendations;
            $results['seo_analysis'] = $this->get_seo_analysis_content();
            $results['content_analysis'] = $this->get_content_analysis_results();

            return $results;

        } catch (Exception $e) {
            error_log('SEO Analysis Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error during analysis: ' . $e->getMessage(),
                'category_scores' => [
                    'page_performance' => [
                        'score' => 0,
                        'message' => 'Analysis failed',
                        'factors' => []
                    ]
                ]
            ];
        }
    }

    private function calculate_overall_score($scores, $weights) {
        $weighted_score = 0;
        foreach ($scores as $category => $data) {
            $weighted_score += $data['score'] * $weights[$category];
        }
        return round($weighted_score);
    }

    private function get_performance_tier($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Very Good';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Above Average';
        if ($score >= 50) return 'Average';
        if ($score >= 40) return 'Below Average';
        if ($score >= 30) return 'Poor';
        return 'Very Poor';
    }

    private function get_pagespeed_insights($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_api_keys';
        
        $api_key = $wpdb->get_var("SELECT api_key FROM $table_name LIMIT 1");
        
        if (!$api_key) {
            error_log('SEO Analyzer: PageSpeed Insights API key is not set');
            return null;
        }

        $api_url = "https://www.googleapis.com/pagespeedonline/v5/runPagespeed?url=" . urlencode($url) . "&key=" . $api_key;

        $response = wp_remote_get($api_url, array('timeout' => 30));
        if (is_wp_error($response)) {
            error_log('SEO Analyzer: Error fetching PageSpeed Insights data - ' . $response->get_error_message());
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        error_log('SEO Analyzer: PageSpeed API response - ' . $body);

        $data = json_decode($body, true);

        if (!$data || isset($data['error'])) {
            error_log('SEO Analyzer: Invalid PageSpeed Insights data received - ' . json_encode($data));
            return null;
        }

        return $data;
    }

    private function analyze_on_page_elements($dom, $url, $keyword) {
        $factors = [];

        // Keyword in H1
        $h1_elements = $dom->getElementsByTagName('h1');
        $keyword_in_h1 = false;
        foreach ($h1_elements as $h1) {
            if (stripos($h1->textContent, $keyword) !== false) {
                $keyword_in_h1 = true;
                break;
            }
        }

        $factors[] = [
            'name' => 'Keyword in H1',
            'score' => $keyword_in_h1 ? 100 : 0,
            'severity' => $keyword_in_h1 ? 'low' : 'high',
            'explanation' => $keyword_in_h1 ? 'Keyword found in H1 tag' : 'Keyword not found in H1 tag',
            'recommendation' => $keyword_in_h1 ? 'Good job! Keep the keyword in your H1 tag' : 'Include your target keyword in the H1 tag of your page'
        ];

        // Meta Description
        $meta_desc_tags = $dom->getElementsByTagName('meta');
        $meta_description = '';
        foreach ($meta_desc_tags as $tag) {
            if ($tag->getAttribute('name') == 'description') {
                $meta_description = $tag->getAttribute('content');
                break;
            }
        }
        $meta_desc_length = strlen($meta_description);
        $meta_desc_score = ($meta_desc_length >= 120 && $meta_desc_length <= 160) ? 100 : 50;
        $factors[] = [
            'name' => 'Meta Description',
            'score' => $meta_desc_score,
            'explanation' => ($meta_desc_score == 100) ? 'Good meta description length' : 'Meta description should be between 120-160 characters',
            'recommendation' => ($meta_desc_score == 100) ? 'Maintain this meta description length' : 'Consider revising the meta description to meet the recommended length'
        ];

        // Keyword in URL
        $url_parts = parse_url($url);
        $path = isset($url_parts['path']) ? $url_parts['path'] : '';
        $host = isset($url_parts['host']) ? $url_parts['host'] : '';
        
        // Combine host and path for a more comprehensive check
        $full_url_string = strtolower($host . $path);
        
        // Prepare keyword for matching
        $keyword_parts = explode(' ', strtolower($keyword));
        
        $keyword_in_url = true;
        foreach ($keyword_parts as $part) {
            if (strpos($full_url_string, $part) === false) {
                $keyword_in_url = false;
                break;
            }
        }

        $factors[] = [
            'name' => 'Keyword in URL',
            'score' => $keyword_in_url ? 100 : 0,
            'explanation' => $keyword_in_url ? 'Keyword found in URL' : 'Keyword not found in URL',
            'recommendation' => $keyword_in_url ? 'Good job!' : 'Consider including the keyword in the URL if possible'
        ];

        // Calculate category score
        $category_score = array_reduce($factors, function($sum, $factor) {
            return $sum + $factor['score'];
        }, 0) / count($factors);

        return [
            'score' => round($category_score),
            'factors' => $factors
        ];
    }

    private function analyze_user_experience_and_accessibility($dom, $pagespeed_data) {
        $factors = [];

        if ($pagespeed_data && isset($pagespeed_data['lighthouseResult']['categories']['accessibility']['score'])) {
            $accessibility_score = $pagespeed_data['lighthouseResult']['categories']['accessibility']['score'] * 100;
            $factors[] = [
                'name' => 'Accessibility',
                'score' => $accessibility_score,
                'explanation' => "Accessibility score from PageSpeed Insights",
                'recommendation' => "Improve accessibility to meet WCAG 2.1 AA standards"
            ];
        }

        // Viewport Meta Tag
        $viewport_tags = $dom->getElementsByTagName('meta');
        $has_viewport = false;
        foreach ($viewport_tags as $tag) {
            if ($tag->getAttribute('name') == 'viewport') {
                $has_viewport = true;
                break;
            }
        }
        $factors[] = [
            'name' => 'Viewport Meta Tag',
            'score' => $has_viewport ? 100 : 0,
            'explanation' => $has_viewport ? 'Viewport meta tag found' : 'No viewport meta tag found',
            'recommendation' => $has_viewport ? 'Good job!' : 'Add a viewport meta tag for better mobile responsiveness'
        ];

        // ARIA Landmarks
        $aria_landmarks = $dom->getElementsByTagName('*');
        $has_aria_landmarks = false;
        foreach ($aria_landmarks as $element) {
            if ($element->hasAttribute('role')) {
                $has_aria_landmarks = true;
                break;
            }
        }
        $factors[] = [
            'name' => 'ARIA Landmarks',
            'score' => $has_aria_landmarks ? 100 : 0,
            'explanation' => $has_aria_landmarks ? 'ARIA landmarks found' : 'No ARIA landmarks found',
            'recommendation' => $has_aria_landmarks ? 'Good job!' : 'Consider adding ARIA landmarks to improve accessibility'
        ];

        // Language Attribute
        $html_tag = $dom->getElementsByTagName('html')->item(0);
        $has_lang_attribute = $html_tag && $html_tag->hasAttribute('lang');
        $factors[] = [
            'name' => 'Language Attribute',
            'score' => $has_lang_attribute ? 100 : 0,
            'explanation' => $has_lang_attribute ? 'Language attribute found' : 'No language attribute found',
            'recommendation' => $has_lang_attribute ? 'Good job!' : 'Add a lang attribute to the <html> tag'
        ];

        // Calculate category score
        $category_score = array_reduce($factors, function($sum, $factor) {
            return $sum + $factor['score'];
        }, 0) / count($factors);

        return [
            'score' => round($category_score),
            'factors' => $factors
        ];
    }

    private function analyze_links_and_structured_data($dom, $url, $page_content) {
        $score = 0;
        $factors = [];

        // Internal Links
        $internal_links = 0;
        $external_links = 0;
        $links = $dom->getElementsByTagName('a');
        $domain = parse_url($url, PHP_URL_HOST);
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, $domain) !== false || strpos($href, 'http') !== 0) {
                $internal_links++;
            } else {
                $external_links++;
            }
        }

        $factors[] = [
            'name' => 'Internal Links',
            'score' => $internal_links > 0 ? 100 : 0,
            'explanation' => "Found $internal_links internal links",
            'recommendation' => "Maintain and improve internal linking structure"
        ];

        $factors[] = [
            'name' => 'External Links',
            'score' => $external_links > 0 ? 100 : 50,
            'explanation' => "Found $external_links external links",
            'recommendation' => "Include relevant external links to enhance content credibility"
        ];

        // Structured Data
        $has_structured_data = strpos($page_content, 'application/ld+json') !== false;
        $factors[] = [
            'name' => 'Structured Data',
            'score' => $has_structured_data ? 100 : 0,
            'explanation' => $has_structured_data ? 'Structured data found' : 'No structured data found',
            'recommendation' => $has_structured_data ? 'Maintain structured data usage' : 'Add structured data to enhance search engine understanding'
        ];

        // Calculate average score
        $score = array_sum(array_column($factors, 'score')) / count($factors);

        return [
            'score' => round($score),
            'factors' => $factors
        ];
    }

    private function analyze_security_and_social($url, $page_content) {
        $score = 0;
        $factors = [];

        // HTTPS
        $is_https = strpos($url, 'https://') === 0;
        $factors[] = [
            'name' => 'HTTPS',
            'score' => $is_https ? 100 : 0,
            'explanation' => $is_https ? 'The website uses HTTPS' : 'The website does not use HTTPS',
            'recommendation' => $is_https ? 'Good job!' : 'Implement HTTPS to secure your website'
        ];

        // Check for robots meta tag
        $has_robots_tag = strpos($page_content, '<meta name="robots"') !== false;
        $factors[] = [
            'name' => 'Robots Meta Tag',
            'score' => $has_robots_tag ? 100 : 50,
            'severity' => $has_robots_tag ? 'low' : 'medium',
            'explanation' => $has_robots_tag ? 'Robots meta tag found' : 'No robots meta tag found',
            'recommendation' => $has_robots_tag ? 'Ensure the robots meta tag is correctly set' : 'Consider adding a robots meta tag to control search engine crawling and indexing'
        ];

        // Check for XML sitemap
        $has_sitemap_link = strpos($page_content, '/sitemap.xml') !== false;
        $factors[] = [
            'name' => 'XML Sitemap',
            'score' => $has_sitemap_link ? 100 : 50,
            'severity' => $has_sitemap_link ? 'low' : 'medium',
            'explanation' => $has_sitemap_link ? 'XML sitemap link found' : 'No XML sitemap link found',
            'recommendation' => $has_sitemap_link ? 'Ensure your sitemap is up to date' : 'Consider adding an XML sitemap to help search engines discover your content'
        ];

        // Calculate category score
        $category_score = array_reduce($factors, function($sum, $factor) {
            return $sum + $factor['score'];
        }, 0) / count($factors);

        $message = implode(' ', array_map(function($factor) {
            return $factor['explanation'] . ' ' . $factor['recommendation'];
        }, $factors));

        return [
            'score' => round($category_score),
            'message' => $message,
            'factors' => $factors
        ];
    }

    private function analyze_page_performance($url) {
        $data = $this->get_pagespeed_insights($url);

        if (!isset($data['lighthouseResult']['categories']['performance']['score'])) {
            return [
                'score' => 0,
                'message' => 'There was an issue running performance tests. Please try analyzing again.',
                'factors' => []
            ];
        }

        $performance_score = $data['lighthouseResult']['categories']['performance']['score'] * 100;

        $factors = [];
        $audits = $data['lighthouseResult']['audits'];

        $important_metrics = [
            'first-contentful-paint' => 'First Contentful Paint',
            'speed-index' => 'Speed Index',
            'largest-contentful-paint' => 'Largest Contentful Paint',
            'interactive' => 'Time to Interactive',
            'total-blocking-time' => 'Total Blocking Time',
            'cumulative-layout-shift' => 'Cumulative Layout Shift'
        ];

        foreach ($important_metrics as $metric_key => $metric_name) {
            if (isset($audits[$metric_key])) {
                $metric = $audits[$metric_key];
                $recommendation = $metric['description'];
                
                // Remove the "Learn more about..." part from the recommendation
                $recommendation = preg_replace('/\s*\[Learn more about.*?\]\(.*?\)\.?/i', '', $recommendation);
                
                $factors[] = [
                    'name' => $metric_name,
                    'score' => $metric['score'] * 100,
                    'explanation' => $metric['title'],
                    'recommendation' => trim($recommendation)
                ];
            }
        }

        return [
            'score' => round($performance_score),
            'message' => "Page Performance Score: $performance_score",
            'factors' => $factors
        ];
    }

    private function fetch_page_content($url) {
        try {
            // Local/dev hosts (e.g. DevKinsta *.local) use self-signed certificates,
            // so verifying SSL would fail with cURL error 60. Keep verification ON for
            // public hosts (security), but skip it for local/self-signed hosts only.
            $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
            $is_local_host = (
                $host === 'localhost'
                || $host === '127.0.0.1'
                || substr($host, -6) === '.local'
                || substr($host, -5) === '.test'
                || (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'local')
            );
            $sslverify = ! $is_local_host;

            // Add custom user agent and headers to appear more like a regular browser.
            // Some target sites (heavy college/govt homepages) respond slowly, so use a
            // generous timeout and retry once on a transient failure (e.g. cURL 28 timeout)
            // instead of failing the whole report on the first slow response.
            $request_args = array(
                'timeout' => 60,
                'redirection' => 5,
                'sslverify' => $sslverify,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'headers' => array(
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache'
                )
            );

            $max_attempts = 2;
            $response = null;
            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                $response = wp_remote_get($url, $request_args);
                if (!is_wp_error($response)) {
                    break;
                }
                error_log('Error fetching URL content (attempt ' . $attempt . '/' . $max_attempts . ') for ' . $url . ': ' . $response->get_error_message());
            }

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'error' => 'Could not access the URL: ' . $response->get_error_message()
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            
            // Handle various HTTP response codes
            if ($response_code === 403) {
                error_log('Access forbidden (403) for URL: ' . $url);
                return [
                    'success' => false,
                    'error' => 'This website is blocking our analysis tool. This usually happens with e-commerce sites like Zara, Amazon, etc. Please try analyzing a different page or contact support for a manual analysis.'
                ];
            }

            if ($response_code !== 200) {
                error_log('HTTP error ' . $response_code . ' for URL: ' . $url);
                return [
                    'success' => false,
                    'error' => 'Could not access the URL (HTTP ' . $response_code . '). Please verify the URL is correct and publicly accessible.'
                ];
            }

            $content = wp_remote_retrieve_body($response);
            if (empty($content)) {
                error_log('Empty content received for URL: ' . $url);
                return [
                    'success' => false,
                    'error' => 'The page returned empty content. Please verify the URL is correct.'
                ];
            }

            return [
                'success' => true,
                'content' => $content
            ];

        } catch (Exception $e) {
            error_log('Exception while fetching URL content: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error accessing the URL: ' . $e->getMessage()
            ];
        }
    }

    public function save_competitor_data() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');

        $report_id = intval($_POST['report_id']);
        $competitor_overall_score = floatval($_POST['competitor_overall_score']);
        $competitor_results = json_decode(stripslashes($_POST['competitor_results']), true);

        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $updated = $wpdb->update(
            $table_name,
            array(
                'competitor_overall_score' => $competitor_overall_score,
                'competitor_results' => json_encode($competitor_results)
            ),
            array('id' => $report_id),
            array('%f', '%s'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Competitor data saved successfully');
        } else {
            wp_send_json_error('Failed to save competitor data');
        }
    }

    public function save_competitor_score() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');

        $report_id = intval($_POST['report_id']);
        $competitor_overall_score = floatval($_POST['competitor_overall_score']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $updated = $wpdb->update(
            $table_name,
            array('competitor_overall_score' => $competitor_overall_score),
            array('id' => $report_id),
            array('%f'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Competitor score saved successfully');
        } else {
            wp_send_json_error('Failed to save competitor score');
        }
    }

    private function analyze_content_quality($page_content, $keyword) {
        $factors = [];
        
        // Keyword in Title
        $title = $this->get_title($page_content);
        $keyword_in_title = stripos($title, $keyword) !== false ? 100 : 0;
        $factors[] = [
            'name' => 'Keyword in Title',
            'score' => $keyword_in_title,
            'explanation' => $keyword_in_title ? 'Keyword found in title' : 'Keyword not found in title',
            'recommendation' => $keyword_in_title ? 'Good job!' : 'Include the keyword in the title tag'
        ];

        // Content Length
        $word_count = str_word_count(strip_tags($page_content));
        $content_length_score = $word_count < 300 ? 30 : ($word_count < 600 ? 70 : 100);
        $factors[] = [
            'name' => 'Content Length',
            'score' => $content_length_score,
            'explanation' => "Content length: $word_count words",
            'recommendation' => $content_length_score < 100 ? 'Aim for at least 600 words of content' : 'Good content length'
        ];

        // Keyword Density
        $keyword_count = substr_count(strtolower($page_content), strtolower($keyword));
        $keyword_density = ($keyword_count / $word_count) * 100;
        $keyword_density_score = ($keyword_density >= 0.5 && $keyword_density <= 2.5) ? 100 : 50;
        $factors[] = [
            'name' => 'Keyword Density',
            'score' => $keyword_density_score,
            'explanation' => "Keyword density: " . round($keyword_density, 2) . "%",
            'recommendation' => $keyword_density_score < 100 ? 'Aim for a keyword density between 0.5% and 2.5%' : 'Good keyword density'
        ];

        $category_score = array_sum(array_column($factors, 'score')) / count($factors);

        return [
            'score' => round($category_score),
            'factors' => $factors
        ];
    }

    private function get_title($page_content) {
        preg_match('/<title>(.*?)<\/title>/i', $page_content, $matches);
        return isset($matches[1]) ? $matches[1] : '';
    }

    private function get_body_content($page_content) {
        $dom = new DOMDocument();
        @$dom->loadHTML($page_content);
        $body = $dom->getElementsByTagName('body')->item(0);
        return $body ? $body->textContent : '';
    }

    private function analyze_image_optimization($dom) {
        $score = 0;
        $message = '';

        $images = $dom->getElementsByTagName('img');
        $img_count = $images->length;

        if ($img_count > 0) {
            $score += 2;
            $message .= $img_count . ' images found. ';

            $alt_count = 0;
            foreach ($images as $img) {
                if ($img->hasAttribute('alt') && trim($img->getAttribute('alt'))) {
                    $alt_count++;
                }
            }

            if ($alt_count == $img_count) {
                $score += 3;
                $message .= 'All images have alt tags. ';
            } else {
                $message .= ($img_count - $alt_count) . ' images missing alt tags. Add alt text to all images. ';
            }
        } else {
            $message .= 'No images found. Consider adding relevant images to enhance your content. ';
        }

        return ['score' => $score, 'message' => $message];
    }

    private function analyze_internal_links($dom) {
        $score = 0;
        $message = '';

        $links = $dom->getElementsByTagName('a');
        $internal_links = 0;
        $total_links = $links->length;

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, $_SERVER['HTTP_HOST']) !== false || strpos($href, '/') === 0) {
                $internal_links++;
            }
        }

        if ($internal_links > 0) {
            $score += 5;
            $message .= $internal_links . ' internal links found. ';
        } else {
            $message .= 'No internal links found. Consider adding internal links to improve site structure. ';
        }

        $message .= 'Total links: ' . $total_links . '. ';

        return ['score' => $score, 'message' => $message];
    }

    private function analyze_external_links($dom) {
        $score = 0;
        $message = '';

        $links = $dom->getElementsByTagName('a');
        $external_links = 0;
        $total_links = $links->length;

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, $_SERVER['HTTP_HOST']) === false && strpos($href, 'http') === 0) {
                $external_links++;
            }
        }

        if ($external_links > 0) {
            $score += 3;
            $message .= $external_links . ' external links found. ';
        } else {
            $message .= 'No external links found. Consider adding relevant external links to enhance content credibility. ';
        }

        return ['score' => $score, 'message' => $message];
    }

    private function analyze_mobile_friendliness($url) {
        // Note: This is a simplified version. For a more accurate analysis, consider using Google's Mobile-Friendly Test API
        $score = 0;
        $message = '';

        $user_agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/12.0 Mobile/15E148 Safari/604.1';
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: $user_agent\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $content = @file_get_contents($url, false, $context);

        if ($content !== false) {
            if (strpos($content, 'viewport') !== false) {
                $score += 5;
                $message .= 'Viewport meta tag found. ';
            } else {
                $message .= 'Viewport meta tag not found. This may affect mobile rendering. ';
            }

            if (strpos($content, '@media') !== false) {
                $score += 5;
                $message .= 'Responsive design detected. ';
            } else {
                $message .= 'No responsive design detected. Consider implementing responsive CSS. ';
            }
        } else {
            $message .= 'Unable to analyze mobile-friendliness. ';
        }

        return ['score' => $score, 'message' => $message];
    }

    private function analyze_schema_markup($dom) {
        $score = 0;
        $message = '';
        $recommendations = [];

        $scripts = $dom->getElementsByTagName('script');
        $schema_found = false;
        $schema_types = [];

        foreach ($scripts as $script) {
            if ($script->getAttribute('type') === 'application/ld+json') {
                $schema_found = true;
                $schema_content = json_decode($script->nodeValue, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $score += 5;
                    $message .= 'Valid schema markup found. ';
                    
                    // Check for schema type
                    if (isset($schema_content['@type'])) {
                        $type = $schema_content['@type'];
                        $schema_types[] = $type;
                        $score += 2;
                        $message .= "Schema type defined: $type. ";
                        
                        // Validate common schema types
                        switch ($type) {
                            case 'Organization':
                                $score += $this->validate_organization_schema($schema_content);
                                break;
                            case 'LocalBusiness':
                                $score += $this->validate_local_business_schema($schema_content);
                                break;
                            case 'Product':
                                $score += $this->validate_product_schema($schema_content);
                                break;
                            case 'Article':
                                $score += $this->validate_article_schema($schema_content);
                                break;
                            // Add more cases for other common schema types
                        }
                    } else {
                        $recommendations[] = 'Add @type property to your schema markup.';
                    }

                    // Check for nested schemas
                    if (isset($schema_content['@graph']) && is_array($schema_content['@graph'])) {
                        $score += 3;
                        $message .= 'Nested schema found using @graph. ';
                        foreach ($schema_content['@graph'] as $nested_schema) {
                            if (isset($nested_schema['@type'])) {
                                $schema_types[] = $nested_schema['@type'];
                            }
                        }
                    }
                } else {
                    $message .= 'Schema markup found but it\'s not valid JSON. ';
                    $recommendations[] = 'Fix the JSON syntax in your schema markup.';
                }
            }
        }

        if (!$schema_found) {
            $message .= 'No schema markup found. ';
            $recommendations[] = 'Add schema markup to improve search engine understanding of your content.';
        } else {
            $schema_types = array_unique($schema_types);
            $message .= 'Schema types found: ' . implode(', ', $schema_types) . '. ';
            
            if (count($schema_types) > 1) {
                $score += 3;
                $message .= 'Multiple schema types are used, which is good for rich results. ';
            }
        }

        return [
            'score' => min($score, 20), // Cap the score at 20
            'message' => $message,
            'recommendations' => $recommendations
        ];
    }

    private function validate_organization_schema($schema) {
        $score = 0;
        if (isset($schema['name'])) $score++;
        if (isset($schema['url'])) $score++;
        if (isset($schema['logo'])) $score++;
        return $score;
    }

    private function validate_local_business_schema($schema) {
        $score = 0;
        if (isset($schema['name'])) $score++;
        if (isset($schema['address'])) $score++;
        if (isset($schema['telephone'])) $score++;
        if (isset($schema['openingHours'])) $score++;
        return $score;
    }

    private function validate_product_schema($schema) {
        $score = 0;
        if (isset($schema['name'])) $score++;
        if (isset($schema['description'])) $score++;
        if (isset($schema['offers'])) $score++;
        if (isset($schema['image'])) $score++;
        return $score;
    }

    private function validate_article_schema($schema) {
        $score = 0;
        if (isset($schema['headline'])) $score++;
        if (isset($schema['author'])) $score++;
        if (isset($schema['datePublished'])) $score++;
        if (isset($schema['publisher'])) $score++;
        return $score;
    }

    private function calculate_readability($text) {
        $text = strip_tags($text);
        $word_count = str_word_count($text);
        $sentence_count = preg_match_all('/[.!?]/', $text, $matches);
        $syllable_count = $this->count_syllables($text);

        if ($sentence_count == 0) {
            return 0;
        }

        return 206.835 - 1.015 * ($word_count / $sentence_count) - 84.6 * ($syllable_count / $word_count);
    }

    private function count_syllables($word) {
        $word = strtolower($word);
        $word = preg_replace('/[^a-z]/', '', $word);
        $syllable_count = 0;
        $vowels = ['a', 'e', 'i', 'o', 'u', 'y'];
        $previous_char = '';

        for ($i = 0; $i < strlen($word); $i++) {
            if (in_array($word[$i], $vowels) && !in_array($previous_char, $vowels)) {
                $syllable_count++;
            }
            $previous_char = $word[$i];
        }

        if ($word[-1] == 'e') {
            $syllable_count--;
        }
        if ($word[-2] == 'l' && $word[-1] == 'e' && strlen($word) > 2 && !in_array($word[-3], $vowels)) {
            $syllable_count++;
        }
        if ($syllable_count == 0) {
            $syllable_count = 1;
        }

        return $syllable_count;
    }

    private function generate_recommendations($scores) {
        $recommendations = [];

        foreach ($scores as $key => $score) {
            if ($score['score'] < 7 && !empty($score['message'])) {
                $recommendations[] = "Improve your " . str_replace('_', ' ', $key) . ": " . $score['message'];
            }
        }

        // If no recommendations were generated, add default ones
        if (empty($recommendations)) {
            $recommendations = [
                "Improve content quality by creating valuable, engaging, and original content.",
                "Address technical SEO issues, such as optimizing site speed and fixing broken links.",
                "Optimize on-page elements like meta tags, headings, and internal linking for better visibility.",
                "Enhance media and formatting by optimizing images, videos, and ensuring mobile responsiveness."
            ];
        }

        return $recommendations;
    }

    private function analyze_media_and_formatting($dom) {
        $factors = [];

        // Check for images
        $images = $dom->getElementsByTagName('img');
        $image_count = $images->length;
        $images_with_alt = 0;

        foreach ($images as $img) {
            if ($img->hasAttribute('alt') && trim($img->getAttribute('alt'))) {
                $images_with_alt++;
            }
        }

        $image_alt_ratio = $image_count > 0 ? $images_with_alt / $image_count : 0;

        if ($image_count === 0) {
            $image_score = 50;
            $image_severity = 'medium';
            $image_explanation = 'No images found on the page';
            $image_recommendation = 'Consider adding relevant images to enhance your content';
        } elseif ($image_alt_ratio < 0.8) {
            $image_score = 50;
            $image_severity = 'medium';
            $image_explanation = 'Some images are missing alt text';
            $image_recommendation = 'Add descriptive alt text to all images for better accessibility and SEO';
        } else {
            $image_score = 100;
            $image_severity = 'low';
            $image_explanation = 'All or most images have alt text';
            $image_recommendation = 'Good job! Maintain descriptive alt text for all images';
        }

        $factors[] = [
            'name' => 'Image Optimization',
            'score' => $image_score,
            'severity' => $image_severity,
            'explanation' => $image_explanation,
            'recommendation' => $image_recommendation
        ];

        // Check for heading structure
        $heading_structure = [];
        for ($i = 1; $i <= 6; $i++) {
            $headings = $dom->getElementsByTagName('h' . $i);
            foreach ($headings as $heading) {
                $heading_structure[] = 'H' . $i;
            }
        }

        $has_proper_structure = true;
        for ($i = 1; $i < count($heading_structure); $i++) {
            if (intval(substr($heading_structure[$i], 1)) < intval(substr($heading_structure[$i-1], 1))) {
                $has_proper_structure = false;
                break;
            }
        }

        $factors[] = [
            'name' => 'Heading Structure',
            'score' => $has_proper_structure ? 100 : 50,
            'severity' => $has_proper_structure ? 'low' : 'medium',
            'explanation' => $has_proper_structure ? 'Proper heading structure used' : 'Improper heading structure detected',
            'recommendation' => 'Ensure your headings follow a logical hierarchy (H1 > H2 > H3, etc.)'
        ];

        // Calculate category score
        $category_score = array_reduce($factors, function($sum, $factor) {
            return $sum + $factor['score'];
        }, 0) / count($factors);

        return [
            'score' => round($category_score),
            'factors' => $factors
        ];
    }

    private function analyze_technical_seo($url, $page_content) {
        $factors = [];
        
        // Check for HTTPS
        $is_https = strpos($url, 'https://') === 0;
        $factors[] = [
            'name' => 'HTTPS',
            'score' => $is_https ? 100 : 0,
            'severity' => $is_https ? 'low' : 'high',
            'explanation' => $is_https ? 'The website uses HTTPS' : 'The website does not use HTTPS',
            'recommendation' => $is_https ? 'Good job!' : 'Implement HTTPS to secure your website'
        ];

        // Check for robots meta tag
        $has_robots_tag = strpos($page_content, '<meta name="robots"') !== false;
        $factors[] = [
            'name' => 'Robots Meta Tag',
            'score' => $has_robots_tag ? 100 : 50,
            'severity' => $has_robots_tag ? 'low' : 'medium',
            'explanation' => $has_robots_tag ? 'Robots meta tag found' : 'No robots meta tag found',
            'recommendation' => $has_robots_tag ? 'Ensure the robots meta tag is correctly set' : 'Consider adding a robots meta tag to control search engine crawling and indexing'
        ];

        // Check for XML sitemap
        $has_sitemap_link = strpos($page_content, '/sitemap.xml') !== false;
        $factors[] = [
            'name' => 'XML Sitemap',
            'score' => $has_sitemap_link ? 100 : 50,
            'severity' => $has_sitemap_link ? 'low' : 'medium',
            'explanation' => $has_sitemap_link ? 'XML sitemap link found' : 'No XML sitemap link found',
            'recommendation' => $has_sitemap_link ? 'Ensure your sitemap is up to date' : 'Consider adding an XML sitemap to help search engines discover your content'
        ];

        // Add LLM crawler permissions check
        $llm_factors = $this->analyze_llm_crawler_permissions($url);
        $factors = array_merge($factors, $llm_factors);
        
        // Calculate category score
        $category_score = array_reduce($factors, function($sum, $factor) {
            return $sum + $factor['score'];
        }, 0) / count($factors);
        
        $message = implode(' ', array_map(function($factor) {
            return $factor['explanation'] . ' ' . $factor['recommendation'];
        }, $factors));
        
        return [
            'score' => round($category_score),
            'message' => $message,
            'factors' => $factors
        ];
    }

    private function analyze_llm_crawler_permissions($url) {
        $factors = [];
        
        // Get the robots.txt URL
        $url_parts = parse_url($url);
        $robots_url = $url_parts['scheme'] . '://' . $url_parts['host'] . '/robots.txt';
        
        // Fetch robots.txt content
        $response = wp_remote_get($robots_url);
        if (is_wp_error($response)) {
            $factors[] = [
                'name' => 'LLM Crawler Access',
                'score' => 50,
                'severity' => 'medium',
                'explanation' => 'Could not access robots.txt file',
                'recommendation' => 'Ensure your robots.txt file is accessible'
            ];
            return $factors;
        }
        
        $robots_content = wp_remote_retrieve_body($response);
        
        // Define LLM crawler user agents to check
        $llm_crawlers = [
            'GPTBot' => false,
            'ChatGPT-User' => false,
            'CCBot' => false,
            'anthropic-ai' => false,
            'Claude-Web' => false,
            'Cohere-ai' => false,
            'Google-Extended' => false
        ];
        
        // Parse robots.txt content
        $current_agent = '';
        $lines = explode("\n", $robots_content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Check for User-agent lines
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $current_agent = trim($matches[1]);
                foreach ($llm_crawlers as $crawler => $status) {
                    if ($current_agent === $crawler || $current_agent === '*') {
                        $llm_crawlers[$crawler] = true;
                    }
                }
                continue;
            }
            
            // Check for Disallow lines for current agent
            if ($current_agent && preg_match('/^Disallow:\s*(.+)$/i', $line, $matches)) {
                $path = trim($matches[1]);
                if ($path === '/' || $path === '*') {
                    foreach ($llm_crawlers as $crawler => $status) {
                        if ($current_agent === $crawler || $current_agent === '*') {
                            $llm_crawlers[$crawler] = false;
                        }
                    }
                }
            }
        }
        
        // Calculate the percentage of allowed LLM crawlers
        $allowed_count = count(array_filter($llm_crawlers));
        $total_crawlers = count($llm_crawlers);
        $permission_score = ($allowed_count / $total_crawlers) * 100;
        
        // Generate explanation and recommendation based on findings
        $allowed_crawlers = array_keys(array_filter($llm_crawlers));
        $blocked_crawlers = array_keys(array_filter($llm_crawlers, function($v) { return !$v; }));
        
        $explanation = sprintf(
            '%d out of %d LLM crawlers are allowed. ',
            $allowed_count,
            $total_crawlers
        );
        if (!empty($allowed_crawlers)) {
            $explanation .= 'Allowed: ' . implode(', ', $allowed_crawlers) . '. ';
        }
        if (!empty($blocked_crawlers)) {
            $explanation .= 'Blocked: ' . implode(', ', $blocked_crawlers) . '.';
        }
        
        $recommendation = $permission_score === 0 ? 
            'Consider allowing some LLM crawlers if you want your content to be accessible to AI tools.' :
            ($permission_score === 100 ? 
                'Ensure this aligns with your content strategy as all LLM crawlers are currently allowed.' :
                'Review your LLM crawler permissions to ensure they align with your content strategy.');
        
        $factors[] = [
            'name' => 'LLM Crawler Access',
            'score' => $permission_score,
            'severity' => 'medium',
            'explanation' => $explanation,
            'recommendation' => $recommendation
        ];
        
        return $factors;
    }

    private function analyze_llm_search_compatibility($url) {
        $factors = [];
        
        // Get the robots.txt URL
        $url_parts = parse_url($url);
        $robots_url = $url_parts['scheme'] . '://' . $url_parts['host'] . '/robots.txt';
        
        // Fetch robots.txt content
        $response = wp_remote_get($robots_url);
        if (is_wp_error($response)) {
            $factors[] = [
                'name' => 'LLM Crawler Access',
                'score' => 50,
                'severity' => 'medium',
                'explanation' => 'Could not access robots.txt file',
                'recommendation' => 'Ensure your robots.txt file is accessible'
            ];
            return [
                'score' => 50,
                'factors' => $factors
            ];
        }
        
        $robots_content = wp_remote_retrieve_body($response);
        
        // Define LLM crawler user agents to check
        $llm_crawlers = [
            'GPTBot' => false,
            'ChatGPT-User' => false,
            'CCBot' => false,
            'anthropic-ai' => false,
            'Claude-Web' => false,
            'Cohere-ai' => false,
            'Google-Extended' => false
        ];
        
        // Parse robots.txt content
        $current_agent = '';
        $lines = explode("\n", $robots_content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Check for User-agent lines
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $current_agent = trim($matches[1]);
                foreach ($llm_crawlers as $crawler => $status) {
                    if ($current_agent === $crawler || $current_agent === '*') {
                        $llm_crawlers[$crawler] = true;
                    }
                }
                continue;
            }
            
            // Check for Disallow lines for current agent
            if ($current_agent && preg_match('/^Disallow:\s*(.+)$/i', $line, $matches)) {
                $path = trim($matches[1]);
                if ($path === '/' || $path === '*') {
                    foreach ($llm_crawlers as $crawler => $status) {
                        if ($current_agent === $crawler || $current_agent === '*') {
                            $llm_crawlers[$crawler] = false;
                        }
                    }
                }
            }
        }
        
        // Calculate the percentage of allowed LLM crawlers
        $allowed_count = count(array_filter($llm_crawlers));
        $total_crawlers = count($llm_crawlers);
        $permission_score = ($allowed_count / $total_crawlers) * 100;
        
        // Generate explanation and recommendation based on findings
        $allowed_crawlers = array_keys(array_filter($llm_crawlers));
        $blocked_crawlers = array_keys(array_filter($llm_crawlers, function($v) { return !$v; }));
        
        $explanation = sprintf(
            '%d out of %d LLM crawlers are allowed. ',
            $allowed_count,
            $total_crawlers
        );
        if (!empty($allowed_crawlers)) {
            $explanation .= 'Allowed: ' . implode(', ', $allowed_crawlers) . '. ';
        }
        if (!empty($blocked_crawlers)) {
            $explanation .= 'Blocked: ' . implode(', ', $blocked_crawlers) . '.';
        }
        
        $recommendation = $permission_score === 0 ? 
            'Consider allowing some LLM crawlers if you want your content to be accessible to AI tools.' :
            ($permission_score === 100 ? 
                'Ensure this aligns with your content strategy as all LLM crawlers are currently allowed.' :
                'Review your LLM crawler permissions to ensure they align with your content strategy.');
        
        $factors[] = [
            'name' => 'LLM Crawler Access',
            'score' => $permission_score,
            'severity' => 'medium',
            'explanation' => $explanation,
            'recommendation' => $recommendation
        ];
        
        // Add meta robots tag check for LLM crawlers
        $meta_robots_response = wp_remote_get($url);
        $meta_robots_content = wp_remote_retrieve_body($meta_robots_response);
        $has_llm_meta_robots = strpos($meta_robots_content, 'noai') !== false || 
                              strpos($meta_robots_content, 'nollm') !== false;
        
        $meta_robots_score = $has_llm_meta_robots ? 0 : 100;
        $factors[] = [
            'name' => 'LLM Meta Robots',
            'score' => $meta_robots_score,
            'severity' => 'medium',
            'explanation' => $has_llm_meta_robots ? 
                'Meta robots tag blocking LLM/AI crawlers found' : 
                'No LLM/AI-specific meta robots restrictions found',
            'recommendation' => $has_llm_meta_robots ? 
                'Review if blocking LLM/AI crawlers aligns with your content strategy' : 
                'Current setup allows LLM/AI indexing. Ensure this aligns with your content goals'
        ];
        
        // Calculate overall category score
        $category_score = array_reduce($factors, function($sum, $factor) {
            return $sum + $factor['score'];
        }, 0) / count($factors);
        
        return [
            'score' => round($category_score),
            'factors' => $factors
        ];
    }

    public function download_pdf_report() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');

        $results = json_decode(stripslashes($_POST['results']), true);
        $site_name = sanitize_text_field($_POST['site_name']);
        $date = sanitize_text_field($_POST['date']);
        $results_html = wp_kses_post($_POST['results_html']);

        // Create new mPDF instance
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
        ]);

        // Add custom CSS
        $stylesheet = file_get_contents(SEO_ANALYZER_PLUGIN_DIR . 'assets/css/seo-analyzer-pdf.css');
        $mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);

        // Generate HTML content for the PDF
        $html = $this->generate_pdf_html($results, $site_name, $date, $results_html);

        // Write HTML content to PDF
        $mpdf->WriteHTML($html);

        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="SEO_Analysis_Report.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        $mpdf->Output('SEO_Analysis_Report.pdf', 'I');

        wp_die();
    }

    private function generate_pdf_html($results, $site_name, $date, $results_html) {
        // Create a DOMDocument to manipulate the HTML
        $dom = new DOMDocument();
        // Load the HTML, using UTF-8 encoding
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $results_html);

        // Debug information
        $logoPath = plugin_dir_path(__FILE__) . 'images/logo.png';
        error_log('Attempting to load logo from: ' . $logoPath);
        error_log('File exists: ' . (file_exists($logoPath) ? 'Yes' : 'No'));
        error_log('File permissions: ' . substr(sprintf('%o', fileperms($logoPath)), -4));

        // Add the image logo at the top
        if (file_exists($logoPath)) {
            // Create a container div for logo and divider
            $containerDiv = $dom->createElement('div');
            
            // Add the logo
            $logoImg = $dom->createElement('img');
            $logoImg->setAttribute('src', $logoPath);
            $logoImg->setAttribute('style', 'display: block; margin: 0 auto 20px; width: 150px;');
            $containerDiv->appendChild($logoImg);
            
            // Add the divider with margin-bottom
            $divider = $dom->createElement('hr');
            $divider->setAttribute('style', 'border: none; height: 1px; background-color: black; width: 100%; margin: 0 0 40px 0;');
            $containerDiv->appendChild($divider);

            // Insert the container at the top of the body
            $body = $dom->getElementsByTagName('body')->item(0);
            $body->insertBefore($containerDiv, $body->firstChild);
        } else {
            error_log('Logo file not found at: ' . $logoPath);
        }

        // Replace "Analysis Results" with "SEO Analysis Report" and add date
        $xpath = new DOMXPath($dom);
        $headings = $xpath->query("//h1[contains(text(), 'Analysis Results')] | //h2[contains(text(), 'Analysis Results')] | //h3[contains(text(), 'Analysis Results')]");
        foreach ($headings as $heading) {
            // Create a container div for date and title
            $container = $dom->createElement('div');
            $container->setAttribute('style', 'text-align: left; margin-bottom: 30px;');
            
            // Add the date
            $dateElement = $dom->createElement('p', date('F j, Y'));
            $dateElement->setAttribute('style', 'margin: 0 0 10px 0; color: #666; font-size: 14px; text-align: left;');
            $container->appendChild($dateElement);
            
            // Add the title
            $titleElement = $dom->createElement('h2', 'SEO Analysis Report for ' . $site_name);
            $titleElement->setAttribute('style', 'margin: 0; color: #333; font-size: 24px; font-weight: bold; text-align: left;');
            $container->appendChild($titleElement);
            
            // Replace the original heading with our new container
            $heading->parentNode->replaceChild($container, $heading);
        }

        // Remove the detailed_download_btn div
        $buttons = $xpath->query("//div[contains(@class, 'detailed_download_btn')]");
        foreach ($buttons as $button) {
            $button->parentNode->removeChild($button);
        }

        // Remove the first NLP result in the detailed-results section
        $detailedResults = $xpath->query("//div[contains(@class, 'detailed-results')]//div[contains(@class, 'content-analysis')]//div[contains(@class, 'nlp-result')]");
        if ($detailedResults->length > 0) {
            $firstNlpResult = $detailedResults->item(0);
            $firstNlpResult->parentNode->removeChild($firstNlpResult);
        }

        // Update progress bars and colors
        $this->updateProgressBar($xpath, 'overall-score', $dom);
        $this->updateProgressBar($xpath, 'page-performance', $dom);
        $this->updateProgressBar($xpath, 'competitor-overall-score', $dom);
        $this->updateProgressBar($xpath, 'competitor-page-performance', $dom);

        // Find and modify the SEO Analysis section
        $seoAnalysisDivs = $xpath->query("//div[contains(@class, 'openai-summary')]");
        foreach ($seoAnalysisDivs as $div) {
            $div->setAttribute('style', 'margin: 0;');
            
            $headings = $xpath->query(".//h3", $div);
            foreach ($headings as $heading) {
                $heading->setAttribute('style', 'margin: 0 0 10px 0; color: #333; font-size: 18px;');
            }
            
            $paragraphs = $xpath->query(".//p", $div);
            foreach ($paragraphs as $paragraph) {
                $paragraph->setAttribute('style', 'margin: 0; color: #333; font-size: 14px; line-height: 1.5;');
            }
        }

        // Add contact text at the bottom
        $body = $dom->getElementsByTagName('body')->item(0);
        
        $contactContainer = $dom->createElement('div');
        $contactContainer->setAttribute('style', 'margin-top: 40px; text-align: center; padding: 20px;');
        
        $contactText = $dom->createElement('p');
        $contactText->setAttribute('style', 'margin-top: 40px; font-size: 14px; color: #333;');
        
        $textNode = $dom->createTextNode('Talk to us if you need help in implementing these recommendations: ');
        $contactText->appendChild($textNode);
        
        $emailLink = $dom->createElement('a', 'hello@thrivemattic.com');
        $emailLink->setAttribute('href', 'mailto:hello@thrivemattic.com');
        $emailLink->setAttribute('style', 'color: #3095ff; text-decoration: underline;');
        $contactText->appendChild($emailLink);
        
        $contactContainer->appendChild($contactText);
        $body->appendChild($contactContainer);

        return $dom->saveHTML();
    }

    private function getProgressBarColor($score) {
        if ($score >= 80) return '#28a745'; // green
        if ($score >= 60) return '#ffc107'; // yellow
        if ($score >= 40) return '#fd7e14'; // orange
        return '#dc3545'; // red
    }

    private function updateProgressBar($xpath, $className, $dom) {
        $containers = $xpath->query("//div[contains(@class, '$className')]");
        foreach ($containers as $container) {
            $scoreElement = $xpath->query(".//h3", $container)->item(0);
            if ($scoreElement) {
                $score = (float)str_replace('%', '', $scoreElement->nodeValue);
                
                // Find the progress bar container and the progress bar itself
                $progressBarContainer = $xpath->query(".//div[contains(@class, 'progress-bar-container')]", $container)->item(0);
                $progressBar = $xpath->query(".//div[contains(@class, 'progress-bar')]", $container)->item(0);
                
                if ($progressBarContainer && $progressBar) {
                    // Set container styles
                    $progressBarContainer->setAttribute('style', 'width: 100%; background-color: #e9ecef; border-radius: 5px; margin: 10px 0; overflow: hidden;');
                    
                    // Set progress bar styles with explicit width and color
                    $color = $this->getProgressBarColor($score);
                    $progressBar->setAttribute('style', sprintf(
                        'width: %s%%; height: 20px; background-color: %s; border-radius: 5px; transition: width 0.3s ease;',
                        $score,
                        $color
                    ));
                }
            }
        }
    }

    public function test_wp_mail() {
        $to = 'your-email@example.com'; // Replace with your email
        $subject = 'Test email from SEO Analyzer';
        $message = 'This is a test email from the SEO Analyzer plugin.';
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        $sent = wp_mail($to, $subject, $message, $headers);

        if ($sent) {
            echo 'Test email sent successfully.';
        } else {
            global $phpmailer;
            if (!empty($phpmailer->ErrorInfo)) {
                echo 'Failed to send test email: ' . $phpmailer->ErrorInfo;
            } else {
                echo 'Failed to send test email: Unknown error';
            }
        }
        die();
    }

    public function save_competitor_results() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');

        $report_id = intval($_POST['report_id']);
        $competitor_results = json_decode(stripslashes($_POST['competitor_results']), true);

        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $updated = $wpdb->update(
            $table_name,
            array('competitor_results' => json_encode($competitor_results)),
            array('id' => $report_id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Competitor results saved successfully');
        } else {
            wp_send_json_error('Failed to save competitor results');
        }
    }

    public function save_competitor_overall_score() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');

        $report_id = intval($_POST['report_id']);
        $competitor_overall_score = floatval($_POST['competitor_overall_score']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'seo_analyzer_results';

        $updated = $wpdb->update(
            $table_name,
            array('competitor_overall_score' => $competitor_overall_score),
            array('id' => $report_id),
            array('%f'),
            array('%d')
        );

        if ($updated !== false) {
            wp_send_json_success('Competitor overall score saved successfully');
        } else {
            wp_send_json_error('Failed to save competitor overall score');
        }
    }

    public function display_analysis_results() {
        // Separate the content types
        $seo_analysis = $this->get_seo_analysis_content();
        $content_analysis = $this->get_content_analysis_results();

        echo '<div class="seo-section">
            <h3>SEO Analysis</h3>
            <div class="seo-results">' . $seo_analysis . '</div>
        </div>';

        echo '<div class="content-section">
            <h3>Content Analysis</h3>
            <div class="content-results">' . $content_analysis . '</div>
        </div>';
    }

    // Add new methods to separate the analysis types
    private function get_seo_analysis_content() {
        // Return SEO-specific analysis (meta tags, keywords, etc.)
    }

    private function get_content_analysis_results() {
        // Return content-specific analysis (readability, word count, etc.)
    }

    public function get_analysis_results() {
        return array(
            'seoAnalysis' => $this->get_seo_analysis_content(),
            'contentAnalysis' => $this->get_content_analysis_results()
        );
    }

    public function log_outgoing_requests($preempt, $args, $url) {
        if (strpos($url, 'pagespeed') !== false || strpos($url, 'your-analysis-endpoint') !== false) {
            error_log('Outgoing request to: ' . $url);
            error_log('Request args: ' . print_r($args, true));
        }
        return $preempt;
    }

    public function fetch_page_title() {
        check_ajax_referer('seo_analyzer_nonce', 'nonce');
        
        $url = sanitize_url($_POST['url']);
        if (empty($url)) {
            wp_send_json_error('Invalid URL');
            return;
        }

        // Add protocol if missing
        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "https://" . $url;
        }

        // Fetch the page content with a browser User-Agent to avoid bot blocking
        $response = wp_remote_get($url, array(
            'timeout' => 20,
            'sslverify' => false,
            'redirection' => 5,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ),
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 400) {
            wp_send_json_success(array('title' => ''));
            return;
        }

        $body = wp_remote_retrieve_body($response);

        // Use DOTALL (s) flag so . matches newlines - titles often span multiple lines
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $title = html_entity_decode(preg_replace('/\s+/', ' ', trim($matches[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($title !== '') {
                wp_send_json_success(array('title' => $title));
                return;
            }
        }

        // Fallback: try Open Graph title
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $og)) {
            $title = html_entity_decode(trim($og[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($title !== '') {
                wp_send_json_success(array('title' => $title));
                return;
            }
        }

        // Return empty string instead of error so the analysis can still proceed
        wp_send_json_success(array('title' => ''));
    }
}

