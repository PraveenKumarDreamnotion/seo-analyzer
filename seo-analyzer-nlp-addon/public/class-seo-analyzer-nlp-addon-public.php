<?php

class SEO_Analyzer_NLP_Addon_Public {

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action('wp_ajax_compare_nlp_results', array($this, 'compare_nlp_results'));
        add_action('wp_ajax_nopriv_compare_nlp_results', array($this, 'compare_nlp_results'));
    }

    public function enqueue_scripts() {
        // Always enqueue the scripts, but they will only be activated if NLP is enabled
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/seo-analyzer-nlp-addon-public.js', array('jquery'), $this->version, true);
        wp_localize_script($this->plugin_name, 'seo_analyzer_nlp_addon_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('seo_analyzer_nlp_addon_nonce')
        ));
    }

    public function nlp_analyze() {
        check_ajax_referer('seo_analyzer_nlp_addon_nonce', 'nonce');

        $url = esc_url_raw($_POST['url']);
        if (empty($url)) {
            wp_send_json_error('URL is empty');
            return;
        }

        $args = array(
            'timeout' => 30,  // Increase timeout to 30 seconds
            'sslverify' => false,  // Disable SSL verification if needed
        );

        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Failed to retrieve URL content: " . $error_message);
            wp_send_json_error("Failed to retrieve URL content: " . $error_message);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log("Failed to retrieve URL content. HTTP response code: " . $response_code);
            wp_send_json_error("Failed to retrieve URL content. HTTP response code: " . $response_code);
            return;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            error_log("Retrieved empty content from URL");
            wp_send_json_error("Retrieved empty content from URL");
            return;
        }

        // Load the HTML content into a DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($body);

        // Remove script and style elements
        while (($script = $dom->getElementsByTagName('script'))->length > 0) {
            $script->item(0)->parentNode->removeChild($script->item(0));
        }
        while (($style = $dom->getElementsByTagName('style'))->length > 0) {
            $style->item(0)->parentNode->removeChild($style->item(0));
        }

        // Use DOMXPath to extract text nodes
        $xpath = new DOMXPath($dom);
        $textNodes = $xpath->query('//text()[normalize-space()]');

        $textContent = '';
        foreach ($textNodes as $textNode) {
            $textContent .= ' ' . trim($textNode->nodeValue);
        }

        // Clean up excessive whitespace
        $textContent = preg_replace('/\s+/', ' ', $textContent);

        wp_send_json_success($textContent);
    }

    public function analyze_text() {
        check_ajax_referer('seo_analyzer_nlp_addon_nonce', 'nonce');

        $text = sanitize_textarea_field($_POST['text']);
        $type = sanitize_text_field($_POST['type']);

        if (empty($text)) {
            wp_send_json_error('No text provided for analysis');
            return;
        }

        try {
            $seo_analyzer_nlp_addon = new SEO_Analyzer_NLP_Addon();
            $result = $seo_analyzer_nlp_addon->analyze_text($text);

            // Extract additional NLP data
            $entities = $seo_analyzer_nlp_addon->analyze_entities($text);
            $moderation = $seo_analyzer_nlp_addon->analyze_moderation($text);
            $categories = $seo_analyzer_nlp_addon->analyze_categories($text);

            $result['entities'] = $entities;
            $result['moderation'] = $moderation;
            $result['categories'] = $categories;

            // Prepare the summary for OpenAI only for the main analysis
            if ($type === 'main') {
                $summary = $this->prepare_summary_for_openai($result);

                // Make OpenAI API call
                $openai_result = $this->get_openai_summary($summary);

                $result['openai_summary'] = $openai_result['summary'];
                // Remove these lines to not send API request details to frontend
                // $result['openai_api_request'] = $openai_result['request'];
                // $result['openai_api_response'] = $openai_result['response'];
            }

            // When sending the response, include the type
            wp_send_json_success(array_merge($result, ['type' => $type]));
        } catch (Exception $e) {
            error_log('SEO Analyzer NLP Addon Error: ' . $e->getMessage());
            wp_send_json_error('An error occurred during analysis: ' . $e->getMessage());
        }
    }

    private function prepare_summary_for_openai($result) {
        $summary = "Sentiment score: {$result['score']}, Magnitude: {$result['magnitude']}\n";
        $summary .= "Entities: " . implode(', ', array_column($result['entities'], 'name')) . "\n";
        $summary .= "Categories: " . implode(', ', array_column($result['categories'], 'name')) . "\n";
        $summary .= "Moderation: " . implode(', ', array_column($result['moderation'], 'name'));
        return $summary;
    }

    private function get_openai_summary($summary) {
        $api_key = get_option('seo_analyzer_openai_api_key');
        if (!$api_key) {
            error_log('OpenAI API key not set');
            return [
                'summary' => 'Error: OpenAI API key is not set. Please configure it in the plugin settings.',
                'request' => null,
                'response' => null
            ];
        }

        $prompt = "You are a Google NLP expert who understands what the scores mean. Now interpret the scores and explain what this means to the website owner in non-technical terms. Also highlight issues that needs attention. Output should be formatted in <ul> numbered points.";

        $request_body = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO expert providing concise NLP analysis.'],
                ['role' => 'user', 'content' => $prompt . "\n\nMain URL ('Actual URL'):\n" . $summary]
            ],
            'max_tokens' => 500,
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
        ]);

        if (is_wp_error($response)) {
            error_log('OpenAI API request failed: ' . $response->get_error_message());
            return [
                'summary' => 'Error: OpenAI API request failed. Please check your internet connection and try again.',
                'request' => $request_body,
                'response' => null
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('Unexpected OpenAI API response: ' . print_r($data, true));
            return [
                'summary' => 'Error: Unexpected response from OpenAI API. Please try again later.',
                'request' => $request_body,
                'response' => $data
            ];
        }

        return [
            'summary' => trim($data['choices'][0]['message']['content']),
            'request' => $request_body,
            'response' => $data
        ];
    }

    public function compare_nlp_results() {
        check_ajax_referer('seo_analyzer_nlp_addon_nonce', 'nonce');

        $main_results = json_decode(stripslashes($_POST['main_results']), true);
        $competitor_results = json_decode(stripslashes($_POST['competitor_results']), true);

        if (empty($main_results) || empty($competitor_results)) {
            wp_send_json_error('Invalid NLP results provided');
            return;
        }

        $api_key = get_option('seo_analyzer_openai_api_key');
        if (!$api_key) {
            wp_send_json_error('OpenAI API key not set');
            return;
        }

        $prompt = $this->prepare_comparison_prompt($main_results, $competitor_results);

        $request_body = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'You are an SEO expert providing concise NLP analysis comparisons.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 1000
        ];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($request_body),
            'timeout' => 60, // Increase timeout to 60 seconds
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('OpenAI API request failed: ' . $error_message);
            wp_send_json_error('OpenAI API request failed: ' . $error_message);
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($response_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            error_log('OpenAI API error: ' . $error_message);
            wp_send_json_error('OpenAI API error: ' . $error_message);
            return;
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('Unexpected OpenAI API response: ' . print_r($data, true));
            wp_send_json_error('Unexpected OpenAI API response');
            return;
        }

        wp_send_json_success([
            'comparison' => $data['choices'][0]['message']['content'],
            'request' => $request_body  // Include the request in the response
        ]);
    }

    private function prepare_comparison_prompt($main_results, $competitor_results) {
        $prompt = "As a Google NLP expert, compare the NLP analysis results of the main website and its competitor. Then explain the scores in easy-to-understand language for the website owner, and point out any problems that need fixing. Output should be formatted in <ul> numbered points:

<ul>
<li>Main Website NLP Analysis:
  <ul>
    <li>Sentiment: [Interpret the sentiment score {$main_results['score']} and magnitude {$main_results['magnitude']}]</li>
    <li>Entities: [List and interpret significance of top 5 entities: " . implode(', ', array_slice(array_column($main_results['entities'], 'name'), 0, 5)) . "]</li>
    <li>Categories: [List and explain relevance of categories: " . implode(', ', array_column($main_results['categories'], 'name')) . "]</li>
    <li>Moderation: [Discuss any moderation issues: " . implode(', ', array_column($main_results['moderation'], 'name')) . "]</li>
    <li>Key Issues: [Highlight 1-2 main issues based on the above analysis]</li>
  </ul>
</li>

<li>Competitor Website NLP Analysis:
  <ul>
    <li>Sentiment: [Interpret the sentiment score {$competitor_results['score']} and magnitude {$competitor_results['magnitude']}]</li>
    <li>Entities: [List and interpret significance of top 5 entities: " . implode(', ', array_slice(array_column($competitor_results['entities'], 'name'), 0, 5)) . "]</li>
    <li>Categories: [List and explain relevance of categories: " . implode(', ', array_column($competitor_results['categories'], 'name')) . "]</li>
    <li>Moderation: [Discuss any moderation issues: " . implode(', ', array_column($competitor_results['moderation'], 'name')) . "]</li>
    <li>Key Issues: [Highlight 1-2 main issues based on the above analysis]</li>
  </ul>
</li>

<li>Comparison and Actionable Insights:
  <ul>
    <li>[Provide 2-3 key comparisons between main and competitor websites]</li>
    <li>[Offer 2-3 actionable recommendations for improvement]</li>
  </ul>
</li>
</ul>

Ensure all sections are complete, properly formatted, and contain meaningful analysis. Do not leave any placeholders or incomplete sections. If you reach the token limit, prioritize completing the analysis for the main website and provide a brief comparison.";

        return $prompt;
    }

    private function format_nlp_results($results) {
        $formatted = "Sentiment: Score {$results['score']}, Magnitude {$results['magnitude']}\n";
        $formatted .= "Entities: " . implode(', ', array_column($results['entities'], 'name')) . "\n";
        $formatted .= "Categories: " . implode(', ', array_column($results['categories'], 'name')) . "\n";
        $formatted .= "Moderation: " . implode(', ', array_column($results['moderation'], 'name')) . "\n";
        return $formatted;
    }
}
