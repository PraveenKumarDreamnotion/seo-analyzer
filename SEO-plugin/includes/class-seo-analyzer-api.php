<?php

class Seo_Analyzer_API {
    private function get_cache_key($params) {
        return 'seo_analysis_' . md5(serialize($params));
    }

    public function modify_api_params($params) {
        // Check cache first
        $cache_key = $this->get_cache_key($params);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            error_log('Returning cached analysis result');
            return $cached_result;
        }

        error_log('Modifying API params: ' . print_r($params, true));
        
        // For PageSpeed API
        if (isset($params['pagespeed'])) {
            // Only request essential metrics
            $params['pagespeed']['strategy'] = 'mobile';
            $params['pagespeed']['category'] = ['performance', 'accessibility', 'best-practices'];
            // Remove detailed audits request
            unset($params['pagespeed']['fields']);
        }

        // For other API endpoints
        if (isset($params['analysis'])) {
            // Only request category scores without detailed factors
            $params['analysis']['fields'] = 'category_scores.*.score,overall_score';
            // Skip detailed factor analysis
            $params['analysis']['skip_factors'] = true;
        }

        error_log('Modified API params: ' . print_r($params, true));

        // Cache the result for 1 hour
        set_transient($cache_key, $params, HOUR_IN_SECONDS);
        
        return $params;
    }
} 