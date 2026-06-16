<?php

class SEO_Analyzer_Addon_OpenAI_Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        add_filter('seo_analyzer_results', array($this, 'generate_openai_summary'));
        
        // Add new AJAX handler for factor recommendations
        add_action('wp_ajax_generate_factor_recommendation', array($this, 'generate_factor_recommendation'));
        add_action('wp_ajax_nopriv_generate_factor_recommendation', array($this, 'generate_factor_recommendation'));

        // Add the AJAX action for recommendations
        add_action('wp_ajax_get_recommendations', array($this, 'get_recommendations'));
        add_action('wp_ajax_nopriv_get_recommendations', array($this, 'get_recommendations'));

        // Add AJAX action for Brevo submission
        add_action('wp_ajax_handle_brevo_submission', array($this, 'handle_brevo_submission'));
        add_action('wp_ajax_nopriv_handle_brevo_submission', array($this, 'handle_brevo_submission'));
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/seo-analyzer-addon-openai-public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/seo-analyzer-addon-openai-public.js', array('jquery'), $this->version, true);
        wp_localize_script($this->plugin_name, 'seoAnalyzerAddonOpenAI', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_analyzer_addon_openai_nonce'),
            'brevo_nonce' => wp_create_nonce('seo_analyzer_brevo_nonce')
        ));
    }

    public function enhance_seo_analysis($results, $url) {
        $api_key = seo_analyzer_addon_openai_get_api_key();
        if (!$api_key) {
            error_log('SEO Analyzer Add-on OpenAI: API key not set');
            return $results;
        }

        error_log('SEO Analyzer Add-on OpenAI: Starting analysis enhancement');

        // Generate summary only, skip recommendations
        $summary_prompt = $this->prepare_summary_prompt($results);
        $openai_response = $this->generate_openai_content($api_key, $summary_prompt);
        $results['openai_summary'] = $openai_response['content'];
        $results['openai_api_request'] = $openai_response['request'];

        error_log('SEO Analyzer Add-on OpenAI: Analysis enhancement completed');
        return $results;
    }

    private function generate_openai_content($api_key, $prompt) {
        error_log('SEO Analyzer Add-on OpenAI: Generating content with prompt: ' . substr($prompt, 0, 100) . '...');

        $request_body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => 'You are a helpful assistant that provides SEO recommendations.'),
                array('role' => 'user', 'content' => $prompt),
            ),
            'max_tokens' => 250,
            'temperature' => 0.7,
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
        ));

        if (is_wp_error($response)) {
            error_log('SEO Analyzer Add-on OpenAI: API call failed - ' . $response->get_error_message());
            return array(
                'content' => 'Unable to generate content.',
                'request' => $request_body
            );
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            $content = trim($body['choices'][0]['message']['content']);
            error_log('SEO Analyzer Add-on OpenAI: Content generated successfully');
            return array(
                'content' => $content,
                'request' => $request_body
            );
        } else {
            error_log('SEO Analyzer Add-on OpenAI: Failed to get content from OpenAI - ' . json_encode($body));
            return array(
                'content' => 'Unable to generate content.',
                'request' => $request_body
            );
        }
    }

    public function generate_openai_summary($results) {
        error_log('SEO Analyzer Add-on OpenAI: generate_openai_summary called');
        $api_key = seo_analyzer_addon_openai_get_api_key();
        if (!$api_key) {
            error_log('SEO Analyzer Add-on OpenAI: API key not set');
            return $results;
        }

        // Prepare the prompt for OpenAI
        $prompt = $this->prepare_summary_prompt($results);

        error_log('SEO Analyzer Add-on OpenAI: Making API call for summary');

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-3.5-turbo',
                'messages' => array(
                    array('role' => 'system', 'content' => 'You are a helpful assistant that summarizes SEO analysis results.'),
                    array('role' => 'user', 'content' => $prompt),
                ),
                'max_tokens' => 250,
                'temperature' => 0.7,
            )),
        ));

        if (is_wp_error($response)) {
            error_log('SEO Analyzer Add-on OpenAI: API call failed - ' . $response->get_error_message());
            return $results;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            $results['openai_summary'] = trim($body['choices'][0]['message']['content']);
            error_log('SEO Analyzer Add-on OpenAI: Summary generated successfully: ' . $results['openai_summary']);
        } else {
            error_log('SEO Analyzer Add-on OpenAI: Failed to get a summary from OpenAI');
        }

        return $results;
    }

    private function prepare_summary_prompt($results) {
        // Validate input first
        if (!is_array($results) || !isset($results['category_scores']) || !is_array($results['category_scores'])) {
            error_log('SEO Analyzer Add-on OpenAI: Invalid results structure provided to prepare_summary_prompt');
            return 'Error: Invalid analysis results structure';
        }

        // Sort categories by score
        $categories = [];
        foreach ($results['category_scores'] as $category => $data) {
            if (isset($data['score'])) {
                $categories[] = [
                    'name' => $category,
                    'score' => $data['score']
                ];
            }
        }
        
        if (empty($categories)) {
            error_log('SEO Analyzer Add-on OpenAI: No valid category scores found');
            return 'Error: No valid category scores found';
        }
        
        usort($categories, function($a, $b) {
            return $a['score'] <=> $b['score'];
        });
        
        $lowest_categories = array_slice($categories, 0, 4);
        
        // Simplified prompt structure to avoid markdown confusion
        $prompt = "As an SEO specialist, analyze these category scores and provide a clear analysis with the following structure:\n\n";
        $prompt .= "Summary:\n[Provide a brief 2-3 sentence overview of the overall SEO performance based on the scores]\n\n";
        $prompt .= "Recommendations:\n";
        $prompt .= "[Provide exactly 4 specific, actionable recommendations based on the lowest scoring categories. Format as a numbered list.]\n\n";
        $prompt .= "Note: Provide the response in plain text without any markdown formatting or special characters.";
        
        return $prompt;
    }

    // Add new method for generating factor-specific recommendations
    public function generate_factor_recommendation() {
        // Use the OpenAI addon specific nonce
        check_ajax_referer('seo_analyzer_addon_openai_nonce', 'nonce');
        
        $category = sanitize_text_field($_POST['category']);
        $factor = sanitize_text_field($_POST['factor']);
        $score = floatval($_POST['score']);
        $category_score = floatval($_POST['category_score']);
        
        $api_key = seo_analyzer_addon_openai_get_api_key();
        if (!$api_key) {
            wp_send_json_error('API key not set');
            return;
        }
        
        $prompt = $this->prepare_factor_recommendation_prompt($category, $factor, $score, $category_score);
        $response = $this->generate_openai_content($api_key, $prompt);
        
        if ($response['content'] === 'Unable to generate content.') {
            wp_send_json_error('Failed to generate recommendations');
            return;
        }
        
        wp_send_json_success(['recommendations' => $response['content']]);
    }

    private function prepare_factor_recommendation_prompt($category, $factor, $score, $category_score) {
        $prompt = "As an SEO expert, provide exactly 3 specific, actionable recommendations to improve the following factor:\n\n";
        $prompt .= "Category: " . ucwords(str_replace('_', ' ', $category)) . " (Score: $category_score)\n";
        $prompt .= "Factor: " . ucwords(str_replace('_', ' ', $factor)) . " (Score: $score)\n\n";
        $prompt .= "Format your response as an unordered list. Each recommendation must be wrapped in <li> tags. Do not include numbers or bullet points at the start. Example format:\n";
        $prompt .= "<ul>\n";
        $prompt .= "<li>First actionable recommendation here</li>\n";
        $prompt .= "<li>Second actionable recommendation here</li>\n";
        $prompt .= "<li>Third actionable recommendation here</li>\n";
        $prompt .= "</ul>\n\n";
        $prompt .= "Your response should follow this exact format with 3 recommendations.\n\n";
        
        return $prompt;
    }

    public function get_recommendations() {
        check_ajax_referer('seo_analyzer_addon_openai_nonce', 'nonce');
        
        $widget_id = sanitize_text_field($_POST['widget_id']);
        if (!isset($widget_id)) {
            wp_send_json_error('Widget ID not provided');
            return;
        }

        $api_key = seo_analyzer_addon_openai_get_api_key();
        if (!$api_key) {
            wp_send_json_error('OpenAI API key not set');
            return;
        }

        // Get the stored analysis results for this widget
        $results = get_transient('seo_analysis_' . $widget_id);
        if (!$results) {
            wp_send_json_error('Analysis results not found');
            return;
        }

        // Prepare prompt for recommendations
        $prompt = $this->prepare_recommendations_prompt($results);
        
        $request_body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system', 
                    'content' => 'You are an expert SEO consultant providing detailed, actionable recommendations.'
                ),
                array('role' => 'user', 'content' => $prompt)
            ),
            'max_tokens' => 500,
            'temperature' => 0.7,
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_body),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Failed to get recommendations: ' . $response->get_error_message());
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['choices'][0]['message']['content'])) {
            wp_send_json_success(array(
                'recommendations' => $body['choices'][0]['message']['content']
            ));
        } else {
            wp_send_json_error('Invalid response from OpenAI');
        }
    }

    private function prepare_recommendations_prompt($results) {
        $prompt = "Based on the following SEO analysis results, provide specific, actionable recommendations:\n\n";
        
        foreach ($results['category_scores'] as $category => $data) {
            $prompt .= ucfirst(str_replace('_', ' ', $category)) . " Score: " . $data['score'] . "\n";
            if (!empty($data['factors'])) {
                foreach ($data['factors'] as $factor) {
                    $prompt .= "- " . $factor['name'] . ": " . $factor['score'] . "\n";
                }
            }
            $prompt .= "\n";
        }
        
        $prompt .= "\nProvide detailed recommendations in this format:\n";
        $prompt .= "1. Priority improvements\n";
        $prompt .= "2. Technical optimizations\n";
        $prompt .= "3. Content enhancements\n";
        $prompt .= "4. User experience improvements\n";
        
        return $prompt;
    }

    public function handle_brevo_submission() {
        try {
            // Verify the Brevo-specific nonce
            if (!check_ajax_referer('seo_analyzer_brevo_nonce', 'nonce', false)) {
                wp_send_json_error('Invalid security token');
                return;
            }

            // Validate required email field
            if (!isset($_POST['email'])) {
                wp_send_json_error('Email is required');
                return;
            }

            $email = sanitize_email($_POST['email']);
            if (!is_email($email)) {
                wp_send_json_error('Invalid email address');
                return;
            }

            // Debug log the incoming POST data
            error_log('SEO Analyzer Brevo Submission POST data: ' . print_r($_POST, true));

            // Get optional fields with default values - Modified to handle both report types
            $url = '';
            $keyword = '';
            
            // Check if data exists in form fields
            if (isset($_POST['url']) && !empty($_POST['url'])) {
                $url = esc_url_raw($_POST['url']);
            } elseif (isset($_POST['form_data']['url']) && !empty($_POST['form_data']['url'])) {
                // Try to get from form_data if direct POST is empty
                $url = esc_url_raw($_POST['form_data']['url']);
            }

            if (isset($_POST['keyword']) && !empty($_POST['keyword'])) {
                $keyword = sanitize_text_field($_POST['keyword']);
            } elseif (isset($_POST['form_data']['keyword']) && !empty($_POST['form_data']['keyword'])) {
                // Try to get from form_data if direct POST is empty
                $keyword = sanitize_text_field($_POST['form_data']['keyword']);
            }

            $competitor_url = isset($_POST['competitor_url']) && !empty($_POST['competitor_url']) ? esc_url_raw($_POST['competitor_url']) : '';
            $choice = isset($_POST['choice']) ? sanitize_text_field($_POST['choice']) : 'report';
            $tool = isset($_POST['tool']) ? sanitize_text_field($_POST['tool']) : 'SEO Analyzer';

            // Debug log the processed data
            error_log('SEO Analyzer Brevo Processed data - URL: ' . $url . ', Keyword: ' . $keyword);

            // Get Brevo API key from settings
            $api_key = get_option('seo_analyzer_brevo_api_key');
            if (!$api_key) {
                wp_send_json_error('Brevo API key not configured');
                return;
            }

            // Add list ID to contact data
            $contact_data = array(
                'email' => $email,
                'listIds' => array(14),
                'updateEnabled' => true,
                'attributes' => array(
                    'URL' => $url,
                    'COMPETITOR_URL' => $competitor_url,
                    'KEYWORD' => $keyword,
                    'CHOICE' => $choice,
                    'TOOL' => $tool
                )
            );

            // Debug log the contact data being sent to Brevo
            error_log('SEO Analyzer Brevo Contact data: ' . print_r($contact_data, true));

            // Brevo API endpoint for adding/updating contact
            $endpoint = 'https://api.brevo.com/v3/contacts';

            $response = wp_remote_post($endpoint, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    'api-key' => $api_key
                ),
                'body' => json_encode($contact_data),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                error_log('SEO Analyzer Brevo API Error: ' . $response->get_error_message());
                wp_send_json_error('Failed to submit data: ' . $response->get_error_message());
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code === 201 || $response_code === 204) {
                wp_send_json_success('Contact successfully added/updated in Brevo');
            } else {
                error_log('SEO Analyzer Brevo API Error: ' . $response_body);
                wp_send_json_error('Failed to submit data to Brevo. Response code: ' . $response_code);
            }

        } catch (Exception $e) {
            error_log('SEO Analyzer Brevo Submission Error: ' . $e->getMessage());
            wp_send_json_error('An unexpected error occurred');
        }
    }
}