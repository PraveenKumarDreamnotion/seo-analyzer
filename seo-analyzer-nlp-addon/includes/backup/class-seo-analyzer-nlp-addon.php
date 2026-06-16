<?php

use Google\Cloud\Language\LanguageClient;

class SEO_Analyzer_NLP_Addon {

    protected $loader;
    private $languageClient;

    public function __construct() {
        $this->load_dependencies();
        $this->define_public_hooks();
        $this->languageClient = new LanguageClient([
            'keyFilePath' => __DIR__ . '/../content-analysis-436004-c1276b19348f.json'
        ]);
    }

    private function load_dependencies() {
        $this->loader = new SEO_Analyzer_NLP_Addon_Loader();
        require_once SEO_ANALYZER_NLP_ADDON_PLUGIN_DIR . 'public/class-seo-analyzer-nlp-addon-public.php';
    }

    private function define_admin_hooks() {
        // Add admin-specific hooks here
    }

    private function define_public_hooks() {
        $plugin_public = new SEO_Analyzer_NLP_Addon_Public($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('wp_ajax_nlp_analyze', $plugin_public, 'nlp_analyze');
        $this->loader->add_action('wp_ajax_nopriv_nlp_analyze', $plugin_public, 'nlp_analyze');
        $this->loader->add_action('wp_ajax_analyze_text', $plugin_public, 'analyze_text');
        $this->loader->add_action('wp_ajax_nopriv_analyze_text', $plugin_public, 'analyze_text');
    }

    public function run() {
        $this->loader->run();
        // Example usage
        $text = 'Google Cloud Natural Language API is amazing!';
        $result = $this->analyze_text($text);
        error_log('Sentiment Score: ' . $result['score']);
        error_log('Sentiment Magnitude: ' . $result['magnitude']);
    }

    public function analyze_text($text) {
        $annotation = $this->languageClient->analyzeSentiment($text);
        $sentiment = $annotation->sentiment();

        return [
            'score' => $sentiment['score'],
            'magnitude' => $sentiment['magnitude']
        ];
    }

    public function analyze_entities($text) {
        $annotation = $this->languageClient->analyzeEntities($text);
        $entities = $annotation->entities();

        $result = [];
        foreach ($entities as $entity) {
            $result[] = [
                'name' => $entity['name'],
                'type' => $entity['type'],
                'salience' => $entity['salience']
            ];
        }

        return $result;
    }

    public function analyze_moderation($text) {
        // Implement moderation analysis using the appropriate API
        // This is a placeholder implementation
        return [
            ['name' => 'Moderation Example', 'confidence' => 0.95]
        ];
    }

    public function analyze_categories($text) {
        $annotation = $this->languageClient->classifyText($text);
        $categories = $annotation->categories();

        $result = [];
        foreach ($categories as $category) {
            $result[] = [
                'name' => $category['name'],
                'confidence' => $category['confidence']
            ];
        }

        return $result;
    }

    public function get_plugin_name() {
        return 'seo-analyzer-nlp-addon';
    }

    public function get_version() {
        return '1.0.0';
    }
}