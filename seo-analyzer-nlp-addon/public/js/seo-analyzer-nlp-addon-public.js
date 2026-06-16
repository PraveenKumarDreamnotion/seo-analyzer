jQuery(document).ready(function($) {
    $('.seo-analyzer-form').on('submit', function(event) {
        var $form = $(this);
        var $widget = $form.closest('.seo-analyzer-widget');
        
        // Check if NLP is enabled
        var nlpEnabled = $widget.data('nlp') === true;
        var competitorEnabled = $widget.data('competitor') === true;
        
        if (!nlpEnabled) {
            // console.log('NLP analysis is disabled');
            return;
        }

        event.preventDefault();
        var url = $form.find('.seo-analyzer-url').val();
        var competitorUrl = $form.find('.seo-analyzer-competitor-url').val();

        // Remove these console.log statements
        // console.log('Sending request to retrieve full content from URL:', url);
        // console.log('Sending request to retrieve full content from Competitor URL:', competitorUrl);

        retrieveAndAnalyzeContent(url, $form, 'main');

        if (competitorEnabled && competitorUrl) {
            retrieveAndAnalyzeContent(competitorUrl, $form, 'competitor');
        }
        
        // Set the analyzing flag for this form
        $(this).data('analyzing', true);
    });

    function retrieveAndAnalyzeContent(url, $form, type) {
        $.ajax({
            url: seo_analyzer_nlp_addon_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'nlp_analyze',
                nonce: seo_analyzer_nlp_addon_ajax.nonce,
                url: url
            },
            timeout: 60000,  // Set timeout to 60 seconds (60000 ms)
            success: function(response) {
                if (response.success) {
                    analyzeText(response.data, $form, type);
                } else {
                    console.error(`Error retrieving content for ${type} URL:`, response.data);
                    var $widget = $form.closest('.seo-analyzer-widget');
                    displayNLPError($widget, type, `Failed to retrieve content: ${response.data}`);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error(`AJAX error for ${type} URL:`, textStatus, errorThrown);
                var $widget = $form.closest('.seo-analyzer-widget');
                let errorMessage = `AJAX error: ${textStatus}`;
                if (textStatus === "timeout") {
                    errorMessage = "The request timed out. The server might be slow or the content might be too large. Please try again or try a different URL.";
                }
                displayNLPError($widget, type, errorMessage);
            }
        });
    }

    function analyzeText(text, $form, type) {
        $.ajax({
            url: seo_analyzer_nlp_addon_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'analyze_text',
                nonce: seo_analyzer_nlp_addon_ajax.nonce,
                text: text,
                type: type
            },
            success: function(response) {
                if (response.success && response.data) {
                    // Store the NLP results
                    window.nlpResults = window.nlpResults || {};
                    window.nlpResults[type] = response.data;

                    // Display NLP results
                    var $widget = $form.closest('.seo-analyzer-widget');
                    displayNLPResults(response.data, $widget, type);

                    // If we have both main and competitor results, compare them
                    if (window.nlpResults.main && window.nlpResults.competitor) {
                        compareNLPResults(window.nlpResults.main, window.nlpResults.competitor);
                    }
                } else {
                    console.error(`Error analyzing ${type} text:`, response.data || 'Unknown error');
                    var $widget = $form.closest('.seo-analyzer-widget');
                    displayNLPError($widget, type, response.data || 'An error occurred during analysis');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error(`AJAX error for ${type} text analysis:`, textStatus, errorThrown);
                var $widget = $form.closest('.seo-analyzer-widget');
                displayNLPError($widget, type, 'Failed to communicate with the server');
            }
        });
    }

    function compareNLPResults(mainResults, competitorResults) {
        $.ajax({
            url: seo_analyzer_nlp_addon_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'compare_nlp_results',
                nonce: seo_analyzer_nlp_addon_ajax.nonce,
                main_results: JSON.stringify(mainResults),
                competitor_results: JSON.stringify(competitorResults)
            },
            success: function(response) {
                if (response.success) {
                    displayComparisonResults(response.data.comparison);
                } else {
                    console.error('Error preparing comparison:', response.data);
                    displayComparisonError(response.data);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX error in comparison preparation:', textStatus, errorThrown);
                displayComparisonError('Request failed: ' + textStatus);
            }
        });
    }

    function makeOpenAIComparison(prompt, apiKey) {
        console.log('Sending NLP results to OpenAI for comparison');
        // Log the OpenAI API Request
        console.log('OpenAI API Request:', {
            model: 'gpt-3.5-turbo',
            messages: [
                {role: 'system', content: 'You are an SEO expert providing concise NLP analysis comparisons.'},
                {role: 'user', content: prompt}
            ],
            max_tokens: 150
        });

        $.ajax({
            url: 'https://api.openai.com/v1/chat/completions',
            type: 'POST',
            headers: {
                'Authorization': 'Bearer ' + apiKey,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                model: 'gpt-3.5-turbo',
                messages: [
                    {role: 'system', content: 'You are an SEO expert providing concise NLP analysis comparisons.'},
                    {role: 'user', content: prompt}
                ],
                max_tokens: 150  // Reduced from 500 to get a more concise response
            }),
            success: function(response) {
                if (response.choices && response.choices[0] && response.choices[0].message) {
                    console.log('OpenAI comparison response:', response.choices[0].message.content);
                    displayComparisonResults(response.choices[0].message.content);
                } else {
                    console.error('Unexpected OpenAI API response:', response);
                    displayComparisonError('Unexpected response from OpenAI API');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error in OpenAI API request:', textStatus, errorThrown);
                displayComparisonError('OpenAI API request failed: ' + textStatus);
            }
        });
    }

    // Add this function at the beginning of the file
    function checkShortcodeAttributes($widget) {
        var shortcodeAtts = $widget.data('shortcode-atts');
        return shortcodeAtts && shortcodeAtts.competitor === 'true' && shortcodeAtts.nlp === 'true';
    }

    // Update the displayNLPResults function
    function displayNLPResults(data, $widget, type) {
        console.log(`NLP analysis result for ${type}`);
        // Check if data and openai_summary exist
        const openai_summary = data && data.openai_summary ? data.openai_summary : 'No summary available';

        // Log the OpenAI API request and response for non-comparison version
        if (data.openai_summary) {
            console.log('OpenAI NLP response:', openai_summary);
        }
        if (data.openai_api_request) {
            console.log('OpenAI API Request:', data.openai_api_request);
        }

        let nlpHtml = `
            <div class="nlp-results ${type}-nlp-results">
                <h4>Content Analysis</h4>
                <div class="ai-summary">
                    ${typeof openai_summary === 'string' && openai_summary.startsWith('Error:') 
                        ? `<p class="error-message">${openai_summary}</p>`
                        : `<p>${openai_summary}</p>`
                    }
                </div>
                <div class="openai-nlp-response">
                    ${openai_summary}
                </div>
            </div>
        `;

        var $nlpResult = $widget.find('.nlp-result');
        
        // Clear existing content before adding new content
        $nlpResult.empty();

        if (type === 'main') {
            $nlpResult.html(nlpHtml).show();
        } else {
            // For competitor results, append to the existing content
            $nlpResult.append(nlpHtml);
        }

        // Check if both competitor and NLP are enabled
        if (checkShortcodeAttributes($widget)) {
            // Set up a MutationObserver to watch for changes in .nlp-comparison-result
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        var $comparisonResult = $widget.find('.nlp-comparison-result');
                        if ($comparisonResult.length > 0 && $comparisonResult.html().trim() !== '') {
                            $nlpResult.html($comparisonResult.html());
                            observer.disconnect(); // Stop observing once we've made the replacement
                        }
                    }
                });
            });

            // Start observing the document with the configured parameters
            observer.observe($widget[0], { childList: true, subtree: true });
        }
    }

    function displayNLPError($widget, type, errorMessage) {
        var $nlpResult = $widget.find('.nlp-result');
        var errorHtml = `<div class="${type}-nlp-results"><p>Error analyzing ${type === 'main' ? 'your' : 'competitor'} content: ${errorMessage}</p></div>`;
        
        if (type === 'main') {
            $nlpResult.html(errorHtml).show();
        } else {
            $nlpResult.append(errorHtml);
        }
    }

    // Update the displayComparisonResults function
    function displayComparisonResults(comparisonData) {
        console.log('OpenAI NLP comparison response:', comparisonData);
        // Find the widget that triggered the analysis
        var $widget = $('.seo-analyzer-widget').filter(function() {
            return $(this).find('.seo-analyzer-form').data('analyzing') === true;
        });

        var $comparisonResult = $widget.find('.nlp-comparison-result');
        if ($comparisonResult.length === 0) {
            $comparisonResult = $('<div class="nlp-comparison-result"></div>');
            $widget.find('.nlp-result').after($comparisonResult);
        }
        
        // Get the main and competitor URLs
        var mainUrl = $widget.find('.seo-analyzer-url').val() || '';
        var competitorUrl = $widget.find('.seo-analyzer-competitor-url').val() || '';

        // Replace URLs with site names, handling potential invalid URLs
        var mainSiteName = mainUrl ? getSiteName(mainUrl) : 'Your Site';
        var competitorSiteName = competitorUrl ? getSiteName(competitorUrl) : 'Competitor Site';
        
        // Convert URLs to clickable links and replace with site names
        var linkedComparisonData = comparisonData;
        if (mainUrl) {
            linkedComparisonData = linkedComparisonData.replace(new RegExp(escapeRegExp(mainUrl), 'g'), mainSiteName);
        }
        if (competitorUrl) {
            linkedComparisonData = linkedComparisonData.replace(new RegExp(escapeRegExp(competitorUrl), 'g'), competitorSiteName);
        }
        linkedComparisonData = linkedComparisonData.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        
        // Log the OpenAI API request for comparison version
        if (comparisonData.request) {
            console.log('OpenAI API Request (Comparison):', comparisonData.request);
        }

        // Update HTML structure to include both the heading and the response in separate divs
        $comparisonResult.html(`
            <h3>Content Analysis</h3>
            <div class="openai-comparison-response">
                <p>${linkedComparisonData}</p>
            </div>
        `);

        // If both competitor and NLP are enabled, replace .nlp-result content
        if (checkShortcodeAttributes($widget)) {
            $widget.find('.nlp-result').html($comparisonResult.html());
        }

        // Reset the analyzing flag
        $widget.find('.seo-analyzer-form').data('analyzing', false);
    }

    function displayComparisonError(errorMessage) {
        // Find the widget that triggered the analysis
        var $widget = $('.seo-analyzer-widget').filter(function() {
            return $(this).find('.seo-analyzer-form').data('analyzing') === true;
        });

        var $comparisonResult = $widget.find('.nlp-comparison-result');
        if ($comparisonResult.length === 0) {
            $comparisonResult = $('<div class="nlp-comparison-result"></div>');
            $widget.find('.nlp-result').after($comparisonResult);
        }
        $comparisonResult.html('<h4>NLP Comparison Error</h4><p>Error: ' + errorMessage + '</p>');

        // Reset the analyzing flag
        $widget.find('.seo-analyzer-form').data('analyzing', false);
    }

    // Helper function to safely get site name from URL
    function getSiteName(url) {
        if (!url) {
            return 'Unknown Site';
        }
        try {
            return new URL(url).hostname;
        } catch (e) {
            console.warn('Invalid URL:', url);
            return url; // Return the original string if it's not a valid URL
        }
    }

    // Helper function to escape special characters in a string for use in a RegExp
    function escapeRegExp(string) {
        if (typeof string !== 'string') {
            console.warn('Invalid input to escapeRegExp:', string);
            return '';
        }
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Add this function to handle copying NLP results to detailed results
    $(document).on('click', '.show-detailed-results', function() {
        var $widget = $(this).closest('.seo-analyzer-widget');
        var $detailedResults = $widget.find('.detailed-results');
        var $nlpResult = $widget.find('.nlp-result').clone();

        // Only proceed if we have NLP results
        if ($nlpResult.length > 0) {
            // Find the h2 'Detailed Results' heading
            var $detailedHeading = $detailedResults.find('h2:contains("Detailed Results")');
            
            // Remove any existing nlp-result in detailed results to avoid duplication
            $detailedResults.find('.nlp-result').remove();
            
            // If heading exists, insert after it; otherwise insert at the beginning
            if ($detailedHeading.length > 0) {
                $detailedHeading.after($nlpResult);
            } else {
                $detailedResults.prepend($nlpResult);
            }
        }
    });
});
