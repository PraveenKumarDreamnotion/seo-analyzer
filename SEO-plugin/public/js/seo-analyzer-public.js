function formatCategoryName(category) {
    if (category === 'technical_seo') {
        return 'Technical SEO';
    }
    if (category === 'llm_search_compatibility') {
        return 'LLM Search Compatibility';
    }
    return category.split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function formatOpenAISummary(summary) {
    if (!summary) return '';
    
    // Clean up any potential HTML or markdown artifacts
    summary = summary.replace(/```[a-z]*|```/g, ''); // Remove code block markers
    summary = summary.replace(/<\/?[^>]+(>|$)/g, ''); // Remove any HTML tags
    
    // Split into lines and filter out empty ones
    const lines = summary.split('\n').map(line => line.trim()).filter(line => line);
    
    let html = '<div class="ai-summary">';
    let inList = false;
    
    lines.forEach(line => {
        // Check if line is a heading (Summary: or Recommendations:)
        if (line.toLowerCase().includes('summary:') || line.toLowerCase().includes('recommendations:')) {
            if (inList) {
                html += '</ul>';
                inList = false;
            }
            html += `<p class="summary-heading">${line}</p>`;
        }
        // Check if line starts with a number followed by period or dash
        else if (/^\d+[\.)]\s/.test(line) || line.startsWith('-')) {
            if (!inList) {
                html += '<ul>';
                inList = true;
            }
            // Remove number/bullet and trim
            const content = line.replace(/^\d+[\.)]\s|-\s*/, '').trim();
            if (content) {
                html += `<li>${content}</li>`;
            }
        }
        // Regular paragraph
        else if (line.trim()) {
            if (inList) {
                html += '</ul>';
                inList = false;
            }
            html += `<p>${line}</p>`;
        }
    });

    // Close any open list
    if (inList) {
        html += '</ul>';
    }

    html += '</div>';
    return html;
}

jQuery(document).ready(function($) {
    'use strict';

    // Initialize the global objects
    window.seoAnalysisResults = window.seoAnalysisResults || {};
    
    // Initialize seoAnalyzerSubmittedUsers from localStorage or as empty object
    window.seoAnalyzerSubmittedUsers = (function() {
        try {
            const stored = localStorage.getItem('seoAnalyzerSubmittedUsers');
            return stored ? JSON.parse(stored) : {};
        } catch (e) {
            console.warn('Error reading from localStorage:', e);
            return {};
        }
    })();

    // Add this function at the beginning of your file
    function getPerformanceTier(score) {
        if (score >= 90) return 'Excellent';
        if (score >= 80) return 'Very Good';
        if (score >= 70) return 'Good';
        if (score >= 60) return 'Above Average';
        if (score >= 50) return 'Average';
        if (score >= 40) return 'Below Average';
        if (score >= 30) return 'Poor';
        return 'Very Poor';
    }

    // Add this function to simulate progress
    function simulateProgress($widget) {
        let progress = 0;
        const $progressBar = $widget.find('.progress-bar');
        const $progressText = $widget.find('.progress-text');
        let $progressMessage = $widget.find('.progress-message');
        const startTime = Date.now();
        let timeoutPopupShown = false;

        // Create timeout popup if it doesn't exist
        if (!$('#timeout-popup').length) {
            $('body').append(`
                <div id="timeout-popup" class="seo-analyzer-popup" style="display: none;">
                    <div class="seo-analyzer-popup-content">
                        <h3>We're still crunching the information. Would you like to...</h3>
                        <div class="timeout-buttons">
                            <button class="wait-results-btn">Wait for the results</button>
                            <button class="explore-tool-btn">Explore another tool</button>
                        </div>
                    </div>
                </div>
            `);

            // Add event listeners for the popup buttons
            $('#timeout-popup .wait-results-btn').on('click', function() {
                $('#timeout-popup').hide();
            });

            $('#timeout-popup .explore-tool-btn').on('click', function() {
                const redirectUrl = window.seoAnalyzerRedirectUrl || '';
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                }
            });
        }

        // Add progress message div if it doesn't exist
        if (!$progressMessage.length) {
            $progressBar.closest('.progress-bar-container').after('<div class="progress-message"></div>');
            $progressMessage = $widget.find('.progress-message');
        }

        return setInterval(() => {
            const elapsedTime = Date.now() - startTime;
            const elapsedSeconds = elapsedTime / 1000;

            // First 40 seconds: progress up to 90%
            if (elapsedSeconds <= 40) {
                progress = (elapsedSeconds / 40) * 90;
                $progressMessage.text('');
            }
            // Next 20 seconds (40-60): show message and maintain progress at 90%
            else if (elapsedSeconds <= 60) {
                progress = 90;
                $progressMessage.text('Hang tight... we\'re analyzing the data');
            }
            // After 60 seconds: show timeout popup if not shown already
            else if (!timeoutPopupShown) {
                timeoutPopupShown = true;
                $('#timeout-popup').show();
            }

            progress = Math.min(progress, 90);
            updateProgress($progressBar, $progressText, progress);
        }, 100);
    }

    function updateProgress($progressBar, $progressText, progress) {
        $progressBar.css('width', `${progress}%`);
        $progressText.text(`${Math.round(progress)}%`);
    }

    function getProgressBarClass(score) {
        if (score >= 80) return 'progress-bar-green';
        if (score >= 60) return 'progress-bar-yellow';
        if (score >= 40) return 'progress-bar-orange';
        return 'progress-bar-red';
    }

    // --- Defensive helpers for missing / unavailable analysis data -----------------
    // Returns the numeric score for a category object, or null when the data is
    // unavailable (a temporary external failure marks it available:false / score:null).
    // A GENUINE score of 0 is preserved (returns 0, not null), so real zeros still show.
    function seoaCategoryScore(catObj) {
        if (!catObj || typeof catObj !== 'object') return null;
        if (catObj.available === false) return null;
        var s = catObj.score;
        if (s === null || s === undefined) return null;
        var n = Number(s);
        return isNaN(n) ? null : n;
    }

    // True only when a comparison competitor has real, renderable analysis data.
    function seoaCompetitorAvailable(results) {
        return !!(results && results.competitor
            && !results.competitor.unavailable
            && results.competitor.category_scores
            && typeof results.competitor.category_scores === 'object');
    }

    // Safe, human-readable reason suffix for an unavailable competitor (e.g. " (HTTP 429...)").
    // NOTE: uses an inline escape (not the inner-scope esc_html) so it is safe to call
    // from these outer-closure helpers.
    function seoaUnavailableReason(results) {
        var r = results && results.competitor ? results.competitor.error : '';
        if (typeof r === 'string' && r.trim()) {
            var safe = r.trim().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return ' (' + safe + ')';
        }
        return '';
    }

    // Renders a Page Performance score block, or a clear "unavailable" block instead
    // of a misleading 0% / Very Poor when the score could not be measured.
    function seoaRenderPerfBlock(catObj) {
        var s = seoaCategoryScore(catObj);
        if (s === null) {
            return '<h3>Data unavailable</h3>' +
                   '<p class="performance-tier">Performance data is temporarily unavailable. Please try again later.</p>' +
                   '<div class="progress-bar-container"><div class="progress-bar" style="width: 0%;"></div></div>';
        }
        return '<h3>' + Math.round(s) + '%</h3>' +
               '<p class="performance-tier">' + getPerformanceTier(s) + '</p>' +
               '<div class="progress-bar-container"><div class="progress-bar ' + getProgressBarClass(s) + '" style="width: ' + Math.round(s) + '%;"></div></div>';
    }

    // Safe "score%" cell text for the category table (real 0 shows "0%", missing shows "N/A").
    function seoaScoreCellText(catObj) {
        var s = seoaCategoryScore(catObj);
        return (s === null) ? 'N/A' : (Math.round(s) + '%');
    }
    function seoaScoreCellClass(catObj) {
        var s = seoaCategoryScore(catObj);
        return (s === null) ? 'score-na' : getScoreClass(s);
    }
    // Safe overall-score rounding (falls back to "N/A" rather than "NaN%").
    function seoaOverall(val) {
        var n = Number(val);
        return isNaN(n) ? 'N/A' : (Math.round(n) + '%');
    }

    // --- AJAX failure tracking -----------------------------------------------------
    // Logs a detailed, trackable console error for any AJAX failure and, for the
    // common "security check failed / HTTP 403" case, prints an explicit diagnosis:
    // the page nonce is invalid or expired — almost always a full-page-cached page
    // serving a stale nonce (frequent on Kinsta staging / WP Rocket / CDN).
    function seoaLogAjaxFailure(context, xhr, textStatus, err) {
        var httpStatus = (xhr && typeof xhr.status !== 'undefined') ? xhr.status : 'n/a';
        var responseText = (xhr && xhr.responseText) ? String(xhr.responseText).slice(0, 300) : '';
        console.error('SEO Analyzer: AJAX failure [' + context + ']', {
            httpStatus: httpStatus,
            textStatus: textStatus,
            error: (err && err.message) ? err.message : err,
            responseText: responseText
        });
        // 403, blocked (0), or a bare "-1" body are the WordPress signatures of a
        // failed nonce (check_ajax_referer). Surface a clear, actionable message.
        if (httpStatus === 403 || httpStatus === 0 || /(^|[^\d])-1([^\d]|$)/.test(responseText.trim())) {
            console.error('SEO Analyzer: SECURITY / NONCE CHECK FAILED (HTTP ' + httpStatus + ') for "' + context +
                '". The page nonce is invalid or expired — usually a full-page-cached page serving a stale nonce ' +
                '(Kinsta/WP Rocket/CDN). Fix: clear the site + CDN cache and hard-refresh, or exclude this tool page from full-page caching.');
        }
    }

    // True when a backend response is the "Security check failed" nonce error
    // (perform_seo_analysis returns this via wp_send_json_error on a bad nonce).
    function seoaIsSecurityFailure(data) {
        return typeof data === 'string' && /security check failed/i.test(data);
    }

    // Emits a detailed, trackable console diagnosis for a BACKEND analysis failure
    // (perform_seo_analysis returned success:false — i.e. the target URL could not be
    // analysed). This is the key hook for identifying an HTTP 429 (and other causes),
    // because a 429 arrives as a *successful* AJAX call with success:false in the body,
    // so it never reaches the jQuery `error` handler.
    function seoaLogBackendFailure(context, url, data) {
        var msg = (typeof data === 'string') ? data : (data && data.message ? String(data.message) : '');
        console.error('SEO Analyzer: analysis FAILED [' + context + '] for ' + url + ' → ' + (msg || 'unknown reason'));

        var httpMatch = msg.match(/HTTP\s*(\d{3})/i);
        var httpCode = httpMatch ? parseInt(httpMatch[1], 10) : null;

        if (/\b429\b/.test(msg) || /too many requests/i.test(msg)) {
            console.error('SEO Analyzer: DIAGNOSIS → TARGET RATE-LIMITED (HTTP 429). "' + url +
                '" is refusing repeated requests from this server\'s IP — common when the target and this site are on the same host (e.g. both on Kinsta). ' +
                'It is intermittent: wait a few minutes and retry, avoid rapid repeat scans, or analyse a different URL. This is the target site\'s rate limit, NOT a plugin error.');
        } else if (httpCode === 403 || /blocking our analysis tool/i.test(msg)) {
            console.error('SEO Analyzer: DIAGNOSIS → TARGET BLOCKED THE REQUEST (HTTP 403 / bot protection / WAF) for "' + url + '". The site is refusing automated access.');
        } else if (httpCode && httpCode >= 500) {
            console.error('SEO Analyzer: DIAGNOSIS → TARGET SERVER ERROR (HTTP ' + httpCode + ') for "' + url + '". The site returned a server error; try again later.');
        } else if (/c?url error|resolve host|timed out|timeout|SSL|Could not access the URL:/i.test(msg)) {
            console.error('SEO Analyzer: DIAGNOSIS → COULD NOT REACH "' + url + '" (DNS / SSL / timeout, or the site is down or unreachable from this server).');
        } else if (/empty content/i.test(msg)) {
            console.error('SEO Analyzer: DIAGNOSIS → "' + url + '" returned EMPTY content (the page may require JavaScript to render, or blocks bots).');
        }
    }

    $(function() {
        $('.seo-analyzer-form').on('submit', function(event) {
            event.preventDefault();
            var $form = $(this);
            var $widget = $form.closest('.seo-analyzer-widget');
            
            // Log shortcode attributes
            var shortcodeAtts = $widget.data('shortcode-atts');
            // console.log('SEO Analyzer Shortcode Attributes:', shortcodeAtts);
            
            // Log NLP attribute separately
            var nlpEnabled = $widget.data('nlp');
            // console.log('NLP Enabled:', nlpEnabled);
            
            performAnalysis($widget);
        });

        $(document).on('click', '.show-detailed-results', function() {
            var $widget = $(this).closest('.seo-analyzer-widget, .seo-analyzer-popup');
            var widgetId = $widget.attr('id');
            
            if (!widgetId) {
                console.error('Widget ID not found');
                return;
            }
            
            // Clean the widget ID (remove -popup suffix if present)
            widgetId = widgetId.replace('-popup', '');
            
            if (!window.seoAnalysisResults || !window.seoAnalysisResults[widgetId]) {
                alert('Please perform an analysis before viewing detailed results.');
                return;
            }

            // Check submission status
            let isSubmitted = checkUserSubmissionStatus(widgetId);

            if (isSubmitted) {
                // User has already submitted details - handle the detailed results display
                let $detailedResults = $widget.find('.detailed-results');
                
                // Toggle the detailed results
                $detailedResults.slideToggle(400, function() {
                    // Update button text based on visibility state after animation completes
                    let isVisible = $detailedResults.is(':visible');
                    let buttonText = isVisible ? 'Hide Detailed Results' : 'Show Detailed Results';
                    $widget.find('.show-detailed-results').text(buttonText);
                });
            } else {
                // User hasn't submitted details - show the form with 'show_details' action
                showUserDataForm($widget, 'show_details');
            }
        });

        $(document).on('click', '.download-report', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if ($(this).prop('disabled')) {
                return;
            }
            
            var $widget = $(this).closest('.seo-analyzer-widget, .seo-analyzer-popup');
            var widgetId = $widget.length > 0 ? $widget.attr('id').replace('-popup', '') : 
                          Object.keys(window.seoAnalysisResults)[0];
            
            if (!window.seoAnalysisResults || !window.seoAnalysisResults[widgetId]) {
                alert('No analysis results available. Please perform an analysis before downloading the report.');
                return;
            }

            // Check submission status
            let isSubmitted = checkUserSubmissionStatus(widgetId);

            if (isSubmitted) {
                // User has already submitted details - proceed with download
                downloadPDFReport(widgetId);
            } else {
                // User hasn't submitted details - show the form with 'download' action
                showUserDataForm($widget, 'download');
            }
        });

        // Add this new event listener for the reanalyze button
        $(document).on('click', '.reanalyze-button', function(e) {
            e.preventDefault();
            console.log('Reanalyze button clicked');
            var $widget = $(this).closest('.seo-analyzer-widget, .seo-analyzer-popup');
            console.log('Widget found:', $widget.length > 0);
            
            // Check if we're in a popup
            var isPopup = $widget.hasClass('seo-analyzer-popup');
            if (isPopup) {
                $widget = $('#' + $widget.attr('id').replace('-popup', ''));
                console.log('Popup detected, using widget:', $widget.attr('id'));
            }
            
            performAnalysis($widget);
        });

        function performAnalysis($widget, retryCount = 0) {
            const analysisStartTime = performance.now();
            console.log('Starting analysis process...');
            console.log('Performing analysis for widget:', $widget.attr('id'));
            var url = $widget.find('.seo-analyzer-url').val();
            var keyword = $widget.find('.seo-analyzer-keyword').val();
            var competitorUrl = $widget.find('.seo-analyzer-competitor-url').val();
            var isPopup = $widget.data('popup') === true;
            var isComparison = $widget.data('comparison') === true;

            console.log('Analysis parameters:', { url, keyword, competitorUrl, isPopup, isComparison });

            // Trim the values if they exist
            url = url ? url.trim() : '';
            keyword = keyword ? keyword.trim() : '';
            competitorUrl = competitorUrl ? competitorUrl.trim() : '';

            if (!url) {
                displayError('Please enter a valid URL.', $widget);
                return;
            }

            if (!keyword) {
                displayError('Please enter a keyword.', $widget);
                return;
            }

            if (isPopup) {
                showLoadingPopup($widget);
            } else {
                $widget.find('.seo-analyzer-loading').show();
            }
            
            $widget.find('.seo-analyzer-results, .seo-analyzer-error').hide();

            const progressInterval = simulateProgress($widget);

            // PERFORMANCE: run the main and competitor analyses CONCURRENTLY instead of
            // sequentially (main was previously awaited before competitor started). Each
            // analysis is dominated by a ~30s PageSpeed call, so parallelising roughly
            // halves wall-clock time for a comparison report.
            //
            // NOTE: because both requests start upfront, the competitor site is now
            // contacted even if the main URL ends up failing (the old code short-circuited
            // that). This is an accepted, minor increase in external calls in exchange for
            // the speed-up; the main URL remains authoritative for the report.
            console.log('Sending request to analyze main URL:', url);
            const mainAnalysisStartTime = performance.now();
            const mainPromise = analyzeUrl(url, keyword, false);

            let competitorPromise = Promise.resolve(null);
            if (isComparison && competitorUrl) {
                console.log('Sending request to analyze competitor URL:', competitorUrl);
                // A competitor TRANSPORT error must never reject the whole chain (that would
                // kill an otherwise-good main report). Normalise it into a resolved failure.
                competitorPromise = analyzeUrl(competitorUrl, keyword, true)
                    .catch(err => ({
                        success: false,
                        data: (err && err.message) ? err.message : 'Competitor request failed'
                    }));
            }

            Promise.all([mainPromise, competitorPromise])
                .then(([mainResults, competitorResults]) => {
                    const mainAnalysisEndTime = performance.now();
                    console.log(`Main+competitor analysis settled in ${((mainAnalysisEndTime - mainAnalysisStartTime) / 1000).toFixed(2)} seconds`);
                    console.log('Main URL analysis response:', mainResults);
                    console.log('Competitor URL analysis response:', competitorResults);

                    // If the main URL could not be analyzed, surface its error message
                    // directly instead of spreading the error string into the results object.
                    if (!mainResults || !mainResults.success) {
                        console.error('SEO Analyzer: report cannot be generated — the PRIMARY URL failed: ' + url +
                            ' → ' + ((mainResults && mainResults.data) || 'unknown reason') + '. See the SEO Analyzer diagnosis above.');
                        return mainResults || { success: false, data: 'Analysis failed' };
                    }

                    const data = { ...mainResults.data, url: url };

                    if (isComparison && competitorUrl) {
                        // Only merge competitor data when it genuinely succeeded AND carries a
                        // competitor object. Otherwise attach a structured "unavailable" marker
                        // so the report still renders and never dereferences missing scores.
                        if (competitorResults && competitorResults.success
                            && competitorResults.data && competitorResults.data.competitor) {
                            data.competitor = {
                                ...competitorResults.data.competitor,
                                url: competitorUrl
                            };
                        } else {
                            const reason = (competitorResults && typeof competitorResults.data === 'string')
                                ? competitorResults.data : '';
                            console.warn('SEO Analyzer: COMPETITOR analysis is unavailable for ' + competitorUrl +
                                ' → ' + (reason || 'unknown reason') + '. The main report will still render; the competitor column shows "Unavailable". See the SEO Analyzer diagnosis above for the specific cause.');
                            data.competitor = {
                                url: competitorUrl,
                                unavailable: true,
                                available: false,
                                error: reason
                            };
                        }
                    }

                    return { success: true, data: data };
                })
                .then(finalResults => {
                    clearInterval(progressInterval);
                    hideLoadingPopup($widget);
                    $widget.find('.seo-analyzer-loading').hide();
                    updateProgress($widget.find('.progress-bar'), $widget.find('.progress-percentage'), 100);
                    
                    if (finalResults.success) {
                        displayResults(finalResults.data, $widget, isComparison);
                        const analysisEndTime = performance.now();
                        console.log(`Total analysis process completed in ${((analysisEndTime - analysisStartTime) / 1000).toFixed(2)} seconds`);
                    } else {
                        displayError(finalResults.data || 'Analysis failed', $widget);
                    }
                })
                .catch(error => {
                    const analysisEndTime = performance.now();
                    console.log(`Analysis process failed after ${((analysisEndTime - analysisStartTime) / 1000).toFixed(2)} seconds`);
                    clearInterval(progressInterval);
                    hideLoadingPopup($widget);
                    $widget.find('.seo-analyzer-loading').hide();
                    handleAnalysisError(error, $widget, retryCount);
                });
        }

        // Add this helper function to fetch page title
        function fetchPageTitle(url) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: seo_analyzer_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'fetch_page_title',
                        nonce: seo_analyzer_ajax.nonce,
                        url: url
                    },
                    success: function(response) {
                        if (response.success) {
                            resolve(response.data.title);
                        } else {
                            if (seoaIsSecurityFailure(response.data)) {
                                console.error('SEO Analyzer: fetch_page_title returned "Security check failed" (nonce invalid/expired) for ' + url + '. Clear cache & hard-refresh.');
                            }
                            reject(new Error(response.data || 'Failed to fetch page title'));
                        }
                    },
                    error: function(xhr, status, error) {
                        seoaLogAjaxFailure('fetch_page_title (' + url + ')', xhr, status, error);
                        reject(error);
                    }
                });
            });
        }

        // analyzeUrl: fetches page title first, then runs the full SEO analysis
        function analyzeUrl(url, keyword, isCompetitor) {
            return new Promise((resolve, reject) => {
                fetchPageTitle(url)
                    .then(pageTitle => {
                        $.ajax({
                            url: seo_analyzer_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'perform_seo_analysis',
                                nonce: seo_analyzer_ajax.nonce,
                                url: url,
                                keyword: keyword,
                                is_competitor: isCompetitor,
                                page_title: pageTitle
                            },
                            success: function(response) {
                                // When the backend reports failure, response.data is an
                                // error STRING. Preserve it as-is (do NOT spread, which
                                // would turn the string into a char-indexed object and
                                // render as "[object Object]").
                                if (!response.success) {
                                    if (seoaIsSecurityFailure(response.data)) {
                                        console.error('SEO Analyzer: perform_seo_analysis returned "Security check failed" (nonce invalid/expired) for ' + url + '. The page nonce is stale — clear the site + CDN cache and hard-refresh, or exclude this tool page from full-page caching.');
                                    } else {
                                        seoaLogBackendFailure(isCompetitor ? 'competitor URL' : 'main URL', url, response.data);
                                    }
                                    resolve({ success: false, data: response.data });
                                    return;
                                }
                                if (isCompetitor) {
                                    resolve({
                                        success: true,
                                        data: {
                                            competitor: {
                                                ...response.data,
                                                page_title: pageTitle
                                            }
                                        }
                                    });
                                } else {
                                    resolve({
                                        success: true,
                                        data: {
                                            ...response.data,
                                            page_title: pageTitle
                                        }
                                    });
                                }
                            },
                            error: function(xhr, status, err) {
                                seoaLogAjaxFailure('perform_seo_analysis (' + url + ')', xhr, status, err);
                                reject(err);
                            }
                        });
                    })
                    .catch(error => {
                        console.warn('Failed to fetch page title:', error);
                        // Fall through: run the analysis without a title so it does not hang
                        $.ajax({
                            url: seo_analyzer_ajax.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'perform_seo_analysis',
                                nonce: seo_analyzer_ajax.nonce,
                                url: url,
                                keyword: keyword,
                                is_competitor: isCompetitor,
                                page_title: ''
                            },
                            success: function(response) {
                                if (!response.success) {
                                    if (seoaIsSecurityFailure(response.data)) {
                                        console.error('SEO Analyzer: perform_seo_analysis returned "Security check failed" (nonce invalid/expired) for ' + url + '. The page nonce is stale — clear the site + CDN cache and hard-refresh, or exclude this tool page from full-page caching.');
                                    } else {
                                        seoaLogBackendFailure(isCompetitor ? 'competitor URL' : 'main URL', url, response.data);
                                    }
                                    resolve({ success: false, data: response.data });
                                    return;
                                }
                                if (isCompetitor) {
                                    resolve({
                                        success: true,
                                        data: { competitor: { ...response.data, page_title: '' } }
                                    });
                                } else {
                                    resolve({
                                        success: true,
                                        data: { ...response.data, page_title: '' }
                                    });
                                }
                            },
                            error: function(xhr, status, err) {
                                seoaLogAjaxFailure('perform_seo_analysis (' + url + ')', xhr, status, err);
                                reject(err);
                            }
                        });
                    });
            });
        }

        // Update the handleAnalysisError function to show errors in popup
        function handleAnalysisError(error, $widget, retryCount) {
            console.log('AJAX error:', error);
            
            // Hide the loading popup if it's visible
            hideLoadingPopup($widget);
            
            // Create error popup HTML
            const errorPopupHtml = `
                <div id="error-popup" class="seo-analyzer-popup">
                    <div class="seo-analyzer-popup-content error-content">
                        <span class="close-popup">&times;</span>
                        <h3>Analysis Error</h3>
                        <p class="error-message"></p>
                    </div>
                </div>
            `;
            
            // Remove any existing error popup
            $('#error-popup').remove();
            
            // Add new error popup
            $('body').append(errorPopupHtml);
            
            let errorMessage = '';
            
            // Check if this is our custom 95-second timeout
            if (error.status === 'timeout' && error.textStatus === 'timeout' && error.errorThrown === 'Request exceeded 95 seconds') {
                let urlToDisplay = error.url;
                if (!urlToDisplay.startsWith('http://') && !urlToDisplay.startsWith('https://')) {
                    urlToDisplay = 'https://' + urlToDisplay;
                }
                errorMessage = `Analysis for ${new URL(urlToDisplay).hostname} could not be completed due to time out issues. Request an expert human analysis.`;
            } else if (error.status === 504 && retryCount < maxRetries) {
                errorMessage = `Analysis timeout occurred. Retrying in ${retryDelay/1000} seconds... (Attempt ${retryCount + 1}/${maxRetries})`;
                
                setTimeout(() => {
                    $('#error-popup').remove();
                    performAnalysis($widget, retryCount + 1);
                }, retryDelay);
            } else {
                errorMessage = 'An error occurred during analysis. Please try again or contact support.';
                if (error.responseText) {
                    try {
                        const response = JSON.parse(error.responseText);
                        if (response.message) {
                            errorMessage = response.message;
                        }
                    } catch (e) {
                        // If response is not JSON, use a generic message
                        errorMessage = 'The analysis could not be completed. Please try again later.';
                    }
                }
            }
            
            // Show error message in popup
            $('#error-popup .error-message').text(errorMessage);
            $('#error-popup').show();
            
            // Add close button functionality
            $('.close-popup').click(function() {
                $('#error-popup').remove();
            });
        }

        function showLoadingPopup($widget) {
            // Remove any existing popup
            $('#' + $widget.attr('id') + '-loading-popup').remove();

            // Create popup HTML
            var popupHtml = `
                <div id="${$widget.attr('id')}-loading-popup" class="seo-analyzer-popup">
                    <div class="seo-analyzer-popup-content progress-bar_popup">
                        <div class="progress-bar-container">
                            <div class="progress-bar"></div>
                        </div>
                        <div class="progress-text">Analyzing: <span class="progress-percentage">0%</span></div>
                    </div>
                </div>
            `;

            // Append popup to body
            $('body').append(popupHtml);

            // Show popup
            $('#' + $widget.attr('id') + '-loading-popup').show();

            // Start simulating progress
            simulateProgress($('#' + $widget.attr('id') + '-loading-popup'));
        }

        function hideLoadingPopup($widget) {
            $('#' + $widget.attr('id') + '-loading-popup').remove();
        }

        // Add this helper function for escaping HTML
        function esc_html(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Update the displayResults function
        function displayResults(results, $widget, isComparison) {
            // Hide the timeout popup if it's visible
            $('#timeout-popup').hide();
            
            console.log('Starting to display results');
            
            console.log('Displaying results for widget:', $widget.attr('id'));
            var $results = $widget.find('.seo-analyzer-results');
            $results.empty();

            // Get domain names with validation
            let mainDomain = 'Website 1';
            let competitorDomain = 'Website 2';
            
            try {
                if (results.url && results.url.includes('://')) {
                    mainDomain = new URL(results.url).hostname;
                } else if (results.url) {
                    // Try adding protocol if missing
                    mainDomain = new URL('https://' + results.url).hostname;
                }
            } catch (e) {
                console.warn('Invalid main URL:', results.url);
                mainDomain = results.url || 'Website 1';
            }

            try {
                if (isComparison && results.competitor && results.competitor.url) {
                    if (results.competitor.url.includes('://')) {
                        competitorDomain = new URL(results.competitor.url).hostname;
                    } else {
                        competitorDomain = new URL('https://' + results.competitor.url).hostname;
                    }
                }
            } catch (e) {
                console.warn('Invalid competitor URL:', results.competitor?.url);
                competitorDomain = results.competitor?.url || 'Website 2';
            }

            let html = `
                <div class="seo-analyzer-results-inner">
                    <h3>SEO Analysis Report for ${results.url}</h3>
                    <p>Page Title: ${results.page_title || 'N/A'}</p>
                    ${isComparison && results.competitor ? 
                        `<p>Competitor's Page URL: ${results.competitor.url}</p>` : 
                        ''}
                    <div class="score-cards">
                        <div class="score-card main-score">
                            <h5>Overall Score:</h5>
                            <h3>${Math.round(results.overall_score)}%</h3>
                            <p class="performance-tier">${getPerformanceTier(results.overall_score)}</p>
                            <div class="progress-bar-container">
                                <div class="progress-bar ${getProgressBarClass(results.overall_score)}" style="width: ${Math.round(results.overall_score)}%;"></div>
                            </div>
                            <h5>Page Performance:</h5>
                            ${seoaRenderPerfBlock(results.category_scores && results.category_scores.page_performance)}
                        </div>
                        ${isComparison && results.competitor ? `
                        <div class="score-card competitor-score">
                            <h5>Competitor's Overall Score:</h5>
                            ${seoaCompetitorAvailable(results) ? `
                            <h3>${Math.round(results.competitor.overall_score)}%</h3>
                            <p class="performance-tier">${getPerformanceTier(results.competitor.overall_score)}</p>
                            <div class="progress-bar-container">
                                <div class="progress-bar ${getProgressBarClass(results.competitor.overall_score)}" style="width: ${Math.round(results.competitor.overall_score)}%;"></div>
                            </div>
                            <h5>Page Performance:</h5>
                            ${seoaRenderPerfBlock(results.competitor.category_scores.page_performance)}
                            ` : `
                            <h3>Unavailable</h3>
                            <p class="performance-tier">Competitor analysis is temporarily unavailable${seoaUnavailableReason(results)}.</p>
                            <div class="progress-bar-container"><div class="progress-bar" style="width: 0%;"></div></div>
                            `}
                        </div>
                        ` : ''}
                    </div>`;

            // Update the OpenAI summary and category scores display
            if (results.openai_summary || results.openai_error) {
                // Format the OpenAI summary as a list if it's not already
                const formattedSummary = results.openai_error ? 
                    `<div class="openai-error">${results.openai_error}</div>` :
                    formatOpenAISummary(results.openai_summary);
                
                html += `
                    <div class="openai-summary-category-scores">
                        <div class="openai-summary-category-scores-inner">
                            <div class="openai-summary">
                                <h3>SEO Analysis</h3>
                                ${formattedSummary}
                            </div>
                            ${isComparison ? `
                                <div class="nlp-comparison-container" data-widget-id="${$widget.attr('id')}">
                                    <!-- Comparison results will be injected here -->
                                </div>
                            ` : ''}
                            <div class="category-scores">
                                <h3>Category Scores</h3>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>${mainDomain}</th>
                                            ${isComparison ? `<th>${competitorDomain}</th>` : ''}
                                        </tr>
                                    </thead>
                                    <tbody>`;

                // Generate empty tables for each category
                Object.entries(results.category_scores).forEach(([category, data]) => {
                    // Guard the competitor cell: when the competitor is unavailable the
                    // whole category_scores object is missing, so never index into it.
                    const compCat = seoaCompetitorAvailable(results)
                        ? results.competitor.category_scores[category]
                        : null;
                    html += `
                        <tr>
                            <td>${formatCategoryName(category)}</td>
                            <td class="${seoaScoreCellClass(data)}">${seoaScoreCellText(data)}</td>
                            ${isComparison ? `
                                <td class="${seoaScoreCellClass(compCat)}">
                                    ${seoaScoreCellText(compCat)}
                                </td>
                            ` : ''}
                        </tr>`;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>`;
            }

            html += `
                <div class="detailed_download_btn">
                    <div class="detailed_download_btn_inner">
                        <button class="show-detailed-results">Show Detailed Results</button>
                        <button class="download-report">Download Report</button>
                    </div>
                </div>
                <div class="detailed-results" style="display:none;"></div>
            `;

            $results.html(html).show();

            // Generate the detailed results HTML but don't show it yet
            let detailedResultsHtml = generateDetailedResultsHtml(results, isComparison);

            // Add the detailed results to the widget but keep it hidden
            $widget.find('.detailed-results').html(detailedResultsHtml);

            // Check if user has already submitted details
            let isSubmitted = checkUserSubmissionStatus($widget.attr('id'));
            if (!isSubmitted) {
                // Hide the detailed results section if user hasn't submitted details
                $widget.find('.detailed-results').hide();
            }

            // Add NLP Response Summary (Content Analysis) only once
            if (results.openai_api_response && results.openai_api_response.choices && results.openai_api_response.choices.length > 0) {
                console.log('OpenAI NLP of main response:', results.openai_api_response.choices[0].message.content);
                let nlpSummary = results.openai_api_response.choices[0].message.content;
                let nlpHtml = `
                    <div class="nlp-response-summary">
                        <h4>Content Analysis:</h4>
                        <p>${nlpSummary}</p>
                    </div>
                `;
                var $nlpResult = $results.find('.nlp-result');
                if ($nlpResult.is(':empty')) {
                    $nlpResult.html(nlpHtml);
                }
            }

            console.log('NLP results added to DOM');

            if (isComparison && results.competitor && results.competitor.openai_api_response) {
                console.log('OpenAI NLP comparison response:', results.competitor.openai_api_response.choices[0].message.content);
                // ... existing code to display comparison results ...
            }

            console.log('Comparison results processed (if applicable)');

            // Add new console messages here
            console.log('SEO Analyzer: Preparing to display results');
            console.log('SEO Analyzer: Generating HTML for results display');
            console.log('SEO Analyzer: Adding category scores to results');
            console.log('SEO Analyzer: Processing detailed results');
            console.log('SEO Analyzer: Preparing NLP summary display');
            console.log('SEO Analyzer: Finalizing results layout');

            console.log('SEO Analyzer: Analysis results - ', results);

            // Check if popup is enabled
            if ($widget.data('popup') === true) {
                console.log('Showing results popup');
                showResultsPopup($widget, html, detailedResultsHtml);
            }

            // Attach event listeners for the new buttons
            console.log('Attaching result action listeners');
            attachResultActionListeners($widget);

            // Store the results for later use (e.g., CSV download)
            console.log('Storing analysis results');
            window.seoAnalysisResults = window.seoAnalysisResults || {};
            window.seoAnalysisResults[$widget.attr('id')] = results;

            // Update the reanalyze button event listener within the results
            console.log('Updating reanalyze button event listener');
            $widget.find('.reanalyze-button').off('click').on('click', function(e) {
                e.preventDefault();
                performAnalysis($widget);
            });

            console.log('Results display completed');
        }

        // Update the generateDetailedResultsHtml function
        function generateDetailedResultsHtml(results, isComparison) {
            let html = '<h2>Detailed Results</h2>';
            
            // Add page details section
            html += `
                <div class="page-details">
                    <p><strong>Page Title:</strong> ${esc_html(results.page_title || 'N/A')}</p>
                    <p><strong>Page URL:</strong> ${esc_html(results.url || 'N/A')}</p>
                    ${isComparison && results.competitor ? 
                        `<p><strong>Competitor's Page URL:</strong> ${esc_html(results.competitor.url || 'N/A')}</p>` : 
                        ''}
                </div>
            `;
            
            Object.entries(results.category_scores).forEach(([category, data]) => {
                const capitalizedCategory = formatCategoryName(category);
                // Only read competitor category data when the competitor is actually available.
                const competitorData = seoaCompetitorAvailable(results)
                    ? results.competitor.category_scores[category] : null;
                const mainScoreTxt = seoaScoreCellText(data);

                html += `
                    <h3>${capitalizedCategory} ${
                        competitorData ?
                        `(Score: ${mainScoreTxt}, Competitor's Score: ${seoaScoreCellText(competitorData)})` :
                        `(Score: ${mainScoreTxt})`
                    }</h3>
                    <table class="detailed-results-table${isComparison ? ' with-competitor' : ''}">
                        <thead>
                            <tr>
                                <th>Factor</th>
                                <th>Your Score</th>
                                ${isComparison ? `<th>Competitor's Score</th>` : ''}
                                <th>Recommendations</th>
                            </tr>
                        </thead>
                        <tbody>`;

                if (data.factors && Array.isArray(data.factors)) {
                    data.factors.forEach(factor => {
                        const competitorFactor = competitorData && competitorData.factors ? 
                            competitorData.factors.find(f => f.name === factor.name) : null;

                        html += `
                            <tr>
                                <td>${factor.name}</td>
                                <td class="${getScoreClass(factor.score)}">
                                    ${Math.round(factor.score)}%
                                    <br>
                                    <span>${factor.explanation || 'N/A'}</span>
                                </td>
                                ${isComparison ? `
                                    <td class="${competitorFactor ? getScoreClass(competitorFactor.score) : ''}">
                                        ${competitorFactor ? `
                                            ${Math.round(competitorFactor.score)}%
                                            <br>
                                            <span>${competitorFactor.explanation || 'N/A'}</span>
                                        ` : 'N/A'}
                                    </td>
                                ` : ''}
                                <td>
                                    <button class="add-recommendation-btn" 
                                        data-category="${category}" 
                                        data-factor="${factor.name}"
                                        data-score="${Math.round(factor.score)}">
                                        Get Recommendations
                                    </button>
                                </td>
                            </tr>`;
                    });
                } else {
                    html += `
                        <tr>
                            <td colspan="${isComparison ? '4' : '3'}" style="text-align: center;">
                                No detailed factors available
                            </td>
                        </tr>`;
                }

                html += `
                        </tbody>
                    </table>`;
            });

            return html;
        }
        function truncateUrl(url, maxLength = 30) {
            if (url.length <= maxLength) return url;
            return url.substr(0, maxLength - 3) + '...';
        }

        function showResultsPopup($widget, resultsHtml, detailedResultsHtml) {
            // Remove any existing popup
            $('#' + $widget.attr('id') + '-popup').remove();

            // Create popup HTML
            var popupHtml = `
                <div id="${$widget.attr('id')}-popup" class="seo-analyzer-popup">
                    <div class="seo-analyzer-popup-content">
                        <span class="seo-analyzer-close">&times;</span>
                        <div id="${$widget.attr('id')}-popup-results">
                            ${resultsHtml}
                        </div>
                    </div>
                </div>
            `;

            // Append popup to body
            $('body').append(popupHtml);

            // Show popup
            $('#' + $widget.attr('id') + '-popup').show();

            // Add detailed results to the popup
            $('#' + $widget.attr('id') + '-popup .detailed-results').html(detailedResultsHtml);

            // Copy NLP results to the popup's detailed results section
            let $nlpResult = $widget.find('.nlp-result').clone();
            let $popupDetailedResults = $('#' + $widget.attr('id') + '-popup .detailed-results');
            
            // Remove any existing NLP results first
            $popupDetailedResults.find('.nlp-result').remove();
            
            // Insert the NLP results after the "Detailed Results" heading
            let $detailedHeading = $popupDetailedResults.find('h2:contains("Detailed Results")');
            if ($detailedHeading.length > 0) {
                $detailedHeading.after($nlpResult);
            } else {
                $popupDetailedResults.prepend($nlpResult);
            }

            // Attach close event to the popup
            $('#' + $widget.attr('id') + '-popup').on('click', '.seo-analyzer-close', function() {
                $('#' + $widget.attr('id') + '-popup').remove();
            });

            // Reattach event listeners for buttons inside the popup
            attachResultActionListeners($('#' + $widget.attr('id') + '-popup'));
        }

        function attachResultActionListeners($container) {
            // Remove the show-detailed-results click handler since we have it elsewhere
            $container.find('.download-report').off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var widgetId = $container.attr('id').replace('-popup', '');
                if (window.seoAnalysisResults && window.seoAnalysisResults[widgetId]) {
                    // Check if user has already submitted details
                    if (checkUserSubmissionStatus(widgetId)) {
                        // If submitted, download directly
                        downloadPDFReport(widgetId);
                    } else {
                        // If not submitted, show the form
                        showUserDataForm($container, 'download');
                    }
                } else {
                    alert('Please perform an analysis before downloading the report.');
                }
            });
        }

        // Call this function after the document is ready
        $(function() {
            attachResultActionListeners($(document));
        });

        function displayError(message, $widget) {
            // Coerce whatever we receive into a readable string so the user sees the
            // real reason instead of "[object Object]".
            let text = message;
            if (text && typeof text === 'object') {
                if (typeof text.message === 'string') {
                    text = text.message;
                } else if (typeof text.data === 'string') {
                    text = text.data;
                } else {
                    // A spread error string becomes a char-indexed object {0:'C',1:'o',...}
                    const keys = Object.keys(text);
                    if (keys.length && keys.every(k => /^\d+$/.test(k))) {
                        text = keys.sort((a, b) => a - b).map(k => text[k]).join('');
                    }
                }
            }
            if (typeof text !== 'string' || !text.trim()) {
                text = 'Analysis failed. Please check the URL is correct and publicly accessible, then try again.';
            }
            // Map the cryptic nonce error to an actionable message (the user remedy for a
            // stale/expired nonce, usually from a cached page, is to reload the page).
            if (seoaIsSecurityFailure(text)) {
                console.error('SEO Analyzer: displaying security/nonce failure to user (invalid or expired nonce — likely a cached page).');
                text = 'Your session has expired or this page was served from cache. Please refresh the page (Ctrl/Cmd + Shift + R) and try again.';
            }
            $widget.find('.seo-analyzer-error').text(text).show();
            $widget.find('.seo-analyzer-results').hide();
        }

        function showUserDataForm($widget, action = 'both') {
            let widgetId = $widget.attr('id').replace('-popup', '');
            
            // Store the action type for later use
            $widget.data('form-action', action);
            
            // Determine the purpose text based on the action
            let purpose = action === 'download' ? 'download the report' : 
                         action === 'show_details' ? 'view detailed results' : 
                         'view detailed results and download the report';
            
            let formHtml = `
                <div id="${widgetId}-user-form-popup" class="seo-analyzer-popup">
                    <div class="seo-analyzer-popup-content user_detail_form">
                        <span class="seo-analyzer-close">&times;</span>
                        <h4>Please provide your details to ${purpose}</h4>
                        <form class="seo-analyzer-form" data-widget-id="${widgetId}">
                            <input type="text" class="user-name" placeholder="Your Name" required>
                            <input type="email" class="user-email" placeholder="Your Email" required>
                            <button type="button" class="send-otp" data-widget-id="${widgetId}">Send OTP</button>
                            <button type="button" class="resend-otp" style="display:none;" data-widget-id="${widgetId}">Resend OTP</button>
                            <input type="text" class="user-otp" placeholder="Enter OTP" required>
                            <button type="submit" class="submit-otp">Submit</button>
                            <span class="seo-analyzer-loader" style="display:none;"></span>
                        </form>
                    </div>
                </div>
            `;

            // Remove any existing popup
            $('#' + widgetId + '-user-form-popup').remove();

            // Append the new popup to the body
            $('body').append(formHtml);

            // Show the popup
            $('#' + widgetId + '-user-form-popup').show();

            // Close popup when clicking on the close button or outside the popup
            $('#' + widgetId + '-user-form-popup').on('click', function(event) {
                if (event.target === this || $(event.target).hasClass('seo-analyzer-close')) {
                    $(this).remove();
                }
            });

            // Attach event handlers
            let $form = $('#' + widgetId + '-user-form-popup .seo-analyzer-form');
            
            $form.off('click', '.send-otp, .resend-otp').on('click', '.send-otp, .resend-otp', function() {
                let email = $form.find('.user-email').val();
                if (isValidEmail(email)) {
                    sendOTP(email, widgetId);
                } else {
                    alert('Please enter a valid email address.');
                }
            });

            $form.on('submit', function(e) {
                e.preventDefault();
                let name = $form.find('.user-name').val();
                let email = $form.find('.user-email').val();
                let otp = $form.find('.user-otp').val();
                
                if (!name || !email || !otp) {
                    alert('Please fill out all fields.');
                    return;
                }
                
                // Show loader
                $form.find('.submit-otp').prop('disabled', true);
                $form.find('.seo-analyzer-loader').show();
                
                // Call the appropriate function based on the action
                const formAction = $widget.data('form-action');
                if (formAction === 'download') {
                    verifyOTPAndDownloadPDF(name, email, otp, widgetId);
                } else if (formAction === 'show_details') {
                    verifyOTPAndShowDetailedResults(name, email, otp, widgetId);
                } else {
                    // For 'both' action, show details first then download
                    verifyOTPAndShowDetailedResults(name, email, otp, widgetId);
                    setTimeout(() => downloadPDFReport(widgetId), 500);
                }
            });
        }

        function sendOTP(email, widgetId) {
            if (!widgetId) {
                console.error('Widget ID is missing');
                return;
            }

            widgetId = String(widgetId).replace('-popup', '');

            let $formPopup = $(`#${widgetId}-user-form-popup`);
            if (!$formPopup.length) {
                console.error('Form popup not found for widget ID:', widgetId);
                return;
            }

            let $sendOTPButton   = $formPopup.find('.send-otp');
            let $resendOTPButton = $formPopup.find('.resend-otp');

            // Use whichever button is currently visible so the loader always appears
            // next to the button the user just clicked (resend after first OTP sent)
            let $activeButton = $sendOTPButton.is(':visible') ? $sendOTPButton : $resendOTPButton;

            $activeButton.prop('disabled', true);
            let $loaderContainer = $('<div class="loader-container"><div class="seo-analyzer-loader"></div><div class="loading-text">Sending OTP...</div></div>');
            $activeButton.after($loaderContainer);

            $.ajax({
                url: seo_analyzer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'send_otp',
                    nonce: seo_analyzer_ajax.nonce,
                    email: email,
                    widget_id: widgetId
                },
                success: function(response) {
                    $loaderContainer.remove();
                    $activeButton.prop('disabled', false);

                    if (response.success) {
                        let $form = $formPopup.find('.seo-analyzer-form');
                        $form.find('.user-otp, .submit-otp').show();
                        $form.find('.send-otp').hide();
                        startOTPTimer($form);
                        alert('OTP sent to your email. Please check and enter the OTP.');
                    } else {
                        alert('Error sending OTP: ' + response.data);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    $loaderContainer.remove();
                    $activeButton.prop('disabled', false);

                    let errorMessage = 'An error occurred. ';
                    if (jqXHR.status === 403) {
                        errorMessage += 'Permission denied. Please refresh the page and try again.';
                    } else {
                        errorMessage += 'Please try again.';
                    }
                    alert(errorMessage);
                    console.error('AJAX error:', {
                        status: jqXHR.status,
                        textStatus: textStatus,
                        errorThrown: errorThrown,
                        responseText: jqXHR.responseText
                    });
                }
            });
        }

        function startOTPTimer($form) {
            let seconds = 60;
            let $sendOTPButton   = $form.find('.send-otp');
            let $resendOTPButton = $form.find('.resend-otp');
            let $submitButton    = $form.find('.submit-otp');
            let widgetId         = $form.data('widget-id');

            // Clear any previously running timer so we never have two countdowns
            let existingTimer = $form.data('otp-timer-id');
            if (existingTimer) {
                clearInterval(existingTimer);
                $form.data('otp-timer-id', null);
            }

            $form.find('.otp-timer').remove();

            let $timerSpan = $('<span class="otp-timer">Resend OTP in 60s</span>');
            $submitButton.before($timerSpan);

            $sendOTPButton.hide();
            $resendOTPButton.hide();

            let timer = setInterval(function() {
                seconds--;
                $timerSpan.text('Resend OTP in ' + seconds + 's');

                if (seconds <= 0) {
                    clearInterval(timer);
                    $form.data('otp-timer-id', null);
                    $timerSpan.remove();
                    $resendOTPButton.show();
                }
            }, 1000);

            $form.data('otp-timer-id', timer);

            $resendOTPButton.off('click').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                let email = $form.find('.user-email').val();
                if (isValidEmail(email)) {
                    sendOTP(email, widgetId);
                } else {
                    alert('Please enter a valid email address.');
                }
            });
        }

        function verifyOTPAndShowDetailedResults(name, email, otp, widgetId) {
            let results = window.seoAnalysisResults[widgetId];
            let $widget = $('#' + widgetId);
            let isPopup = $widget.data('popup') === true;
            let isComparison = $widget.data('comparison') === true;

            // Prepare the data object with all required fields
            let data = {
                action: 'verify_otp_and_save_data',
                nonce: seo_analyzer_ajax.nonce,
                name: name,
                email: email,
                otp: otp,
                widget_id: widgetId,
                url: $widget.find('.seo-analyzer-url').val(),
                keyword: $widget.find('.seo-analyzer-keyword').val(),
                competitor_url: $widget.find('.seo-analyzer-competitor-url').val() || '',
                results: JSON.stringify(results),
                competitor_results: results.competitor ? JSON.stringify(results.competitor) : '',
                competitor_overall_score: results.competitor ? results.competitor.overall_score : null
            };

            // Show loading state
            $('#' + widgetId + '-user-form-popup .seo-analyzer-loader').show();

            $.ajax({
                url: seo_analyzer_ajax.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        // Store submission status in localStorage
                        try {
                            const storedUsers = JSON.parse(localStorage.getItem('seoAnalyzerSubmittedUsers')) || {};
                            storedUsers[widgetId] = true;
                            localStorage.setItem('seoAnalyzerSubmittedUsers', JSON.stringify(storedUsers));
                        } catch (e) {
                            console.warn('Error saving to localStorage:', e);
                        }

                        // Remove the form popup
                        $('#' + widgetId + '-user-form-popup').remove();
                        
                        // Show detailed results for both main widget and popup if exists
                        let $widgets = isPopup ? 
                            $('#' + widgetId + ', #' + widgetId + '-popup') :
                            $('#' + widgetId);
                        
                        $widgets.each(function() {
                            let $currentWidget = $(this);
                            let $detailedResults = $currentWidget.find('.detailed-results');
                            let $showDetailedButton = $currentWidget.find('.show-detailed-results');
                            
                            // Remove any inline display style and show the detailed results
                            $detailedResults.removeAttr('style').show();
                            
                            // Update button text to "Hide Detailed Results" since we're showing the results
                            $showDetailedButton.text('Hide Detailed Results');
                            
                            // Add a data attribute to track visibility state
                            $detailedResults.data('visible', true);
                        });

                        // Log the submission
                        logUserSubmission(name, email, widgetId);
                    } else {
                        alert('Error: ' + (response.data || 'Invalid OTP'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // Hide loader and show error
                    $('#' + widgetId + '-user-form-popup .seo-analyzer-loader').hide();
                    $('#' + widgetId + '-user-form-popup .submit-otp').prop('disabled', false);
                    
                    let errorMessage = 'An error occurred. ';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                        errorMessage += jqXHR.responseJSON.data;
                    } else if (errorThrown) {
                        errorMessage += errorThrown;
                    } else {
                        errorMessage += 'Please try again.';
                    }
                    
                    alert(errorMessage);
                    console.error('AJAX error:', {
                        status: jqXHR.status,
                        textStatus: textStatus,
                        errorThrown: errorThrown,
                        responseText: jqXHR.responseText
                    });
                }
            });
        }

        // Add this function to handle the user form for download
        function showUserDataFormForDownload($widget) {
            let widgetId = $widget.attr('id').replace('-popup', '');
            let formHtml = `
                <div id="${widgetId}-user-form-popup" class="seo-analyzer-popup">
                    <div class="seo-analyzer-popup-content user_detail_form">
                        <span class="seo-analyzer-close">&times;</span>
                        <h4>Please provide your details to download the report</h4>
                        <form class="seo-analyzer-form" data-widget-id="${widgetId}">
                            <input type="text" class="user-name" placeholder="Your Name" required>
                            <input type="email" class="user-email" placeholder="Your Email" required>
                            <button type="button" class="send-otp" data-widget-id="${widgetId}">Send OTP</button>
                            <button type="button" class="resend-otp" style="display:none;" data-widget-id="${widgetId}">Resend OTP</button>
                            <input type="text" class="user-otp" placeholder="Enter OTP" required>
                            <button type="submit" class="submit-otp">Submit</button>
                            <span class="seo-analyzer-loader" style="display:none;"></span>
                        </form>
                    </div>
                </div>
            `;

            // Remove any existing popup
            $('#' + widgetId + '-user-form-popup').remove();

            // Append the new popup to the body
            $('body').append(formHtml);

            // Show the popup
            $('#' + widgetId + '-user-form-popup').show();

            // Close popup when clicking on the close button or outside the popup
            $('#' + widgetId + '-user-form-popup').on('click', function(event) {
                if (event.target === this || $(event.target).hasClass('seo-analyzer-close')) {
                    $(this).remove();
                }
            });

            // Attach event handlers
            let $form = $('#' + widgetId + '-user-form-popup .seo-analyzer-form');
            
            $form.off('click', '.send-otp, .resend-otp').on('click', '.send-otp, .resend-otp', function() {
                let email = $form.find('.user-email').val();
                if (isValidEmail(email)) {
                    sendOTP(email, widgetId);
                } else {
                    alert('Please enter a valid email address.');
                }
            });

            $form.on('submit', function(e) {
                e.preventDefault();
                let name = $form.find('.user-name').val();
                let email = $form.find('.user-email').val();
                let otp = $form.find('.user-otp').val();
                
                if (!name || !email || !otp) {
                    alert('Please fill out all fields.');
                    return;
                }
                
                // Show loader
                $form.find('.submit-otp').prop('disabled', true);
                $form.find('.seo-analyzer-loader').show();
                
                verifyOTPAndDownloadPDF(name, email, otp, widgetId);
            });
        }

        // Update the verifyOTPAndDownloadPDF function
        function verifyOTPAndDownloadPDF(name, email, otp, widgetId) {
            let results = window.seoAnalysisResults[widgetId];
            let $widget = $('#' + widgetId);
            
            $.ajax({
                url: seo_analyzer_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'verify_otp_and_save_data',
                    nonce: seo_analyzer_ajax.nonce,
                    name: name,
                    email: email,
                    otp: otp,
                    widget_id: widgetId,
                    url: $widget.find('.seo-analyzer-url').val(),
                    keyword: $widget.find('.seo-analyzer-keyword').val(),
                    competitor_url: $widget.find('.seo-analyzer-competitor-url').val() || '',
                    results: JSON.stringify(results),
                    competitor_results: results.competitor ? JSON.stringify(results.competitor) : '',
                    competitor_overall_score: results.competitor ? results.competitor.overall_score : null
                },
                success: function(response) {
                    // Hide loader
                    $('#' + widgetId + '-user-form-popup .seo-analyzer-loader').hide();
                    $('#' + widgetId + '-user-form-popup .submit-otp').prop('disabled', false);
                    
                    if (response.success) {
                        // Store submission status in both memory and localStorage
                        window.seoAnalyzerSubmittedUsers = window.seoAnalyzerSubmittedUsers || {};
                        window.seoAnalyzerSubmittedUsers[widgetId] = true;
                        
                        try {
                            const storedUsers = JSON.parse(localStorage.getItem('seoAnalyzerSubmittedUsers')) || {};
                            storedUsers[widgetId] = true;
                            localStorage.setItem('seoAnalyzerSubmittedUsers', JSON.stringify(storedUsers));
                        } catch (e) {
                            console.warn('Error saving to localStorage:', e);
                        }

                        // Remove the form popup
                        $('#' + widgetId + '-user-form-popup').remove();
                        
                        // Download the PDF report
                        downloadPDFReport(widgetId);
                        
                        // Log the submission
                        logUserSubmission(name, email, widgetId);
                    } else {
                        let errorMessage = response.data || 'Invalid OTP. Please try again.';
                        alert(errorMessage);
                        console.error('OTP verification failed:', errorMessage);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // Hide loader and show error
                    $('#' + widgetId + '-user-form-popup .seo-analyzer-loader').hide();
                    $('#' + widgetId + '-user-form-popup .submit-otp').prop('disabled', false);
                    
                    let errorMessage = 'An error occurred. ';
                    if (jqXHR.responseJSON && jqXHR.responseJSON.data) {
                        errorMessage += jqXHR.responseJSON.data;
                    } else if (errorThrown) {
                        errorMessage += errorThrown;
                    } else {
                        errorMessage += 'Please try again.';
                    }
                    
                    alert(errorMessage);
                    console.error('AJAX Error:', {
                        status: textStatus,
                        error: errorThrown,
                        responseText: jqXHR.responseText
                    });
                }
            });
        }

        // Remove the existing click event listener for detailed results content handling
        // and replace it with this updated version
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('show-detailed-results')) {
                const seoWidget = event.target.closest('[id^="seo-analyzer-widget-"]');
                
                if (seoWidget) {
                    const widgetId = seoWidget.id;
                    const isSubmitted = checkUserSubmissionStatus(widgetId);
                    
                    if (!isSubmitted) {
                        // Prevent showing detailed results if user hasn't submitted details
                        return;
                    }
                    
                    // Get the detailed results container
                    const detailedResults = seoWidget.querySelector('.detailed-results');
                    
                    // Show the detailed results
                    if (detailedResults) {
                        detailedResults.style.display = 'block';
                        
                        // Clone and insert NLP results if they exist
                        const nlpResult = seoWidget.querySelector('.nlp-result');
                        if (nlpResult) {
                            const nlpClone = nlpResult.cloneNode(true);
                            const detailedHeading = detailedResults.querySelector('h2');
                            
                            // Remove any existing NLP results first
                            detailedResults.querySelectorAll('.nlp-result').forEach(el => el.remove());
                            
                            // Insert after the heading
                            if (detailedHeading) {
                                detailedHeading.after(nlpClone);
                            } else {
                                detailedResults.prepend(nlpClone);
                            }
                        }
                    }
                }
            }
        });
    });

    // Move the downloadPDFReport function inside the IIFE
    function downloadPDFReport(widgetId) {
        // Ensure widgetId is a string and clean it
        widgetId = String(widgetId).replace('-popup', '');
        
        // Get the base widget (non-popup version)
        let $widget = $('#' + widgetId);
        let results = window.seoAnalysisResults[widgetId];
        
        if (!$widget.length || !results) {
            console.error('Widget or results not found:', widgetId);
            return;
        }

        let $downloadButton = $('.download-report');
        let $loaderContainer = $('<div class="loader-container"><div class="seo-analyzer-loader"></div><div class="loading-text">Generating PDF report...</div></div>');
        
        // Add loader container after the download button
        $downloadButton.prop('disabled', true).after($loaderContainer);
        
        // Get the current date in dd-mon-yy format
        let currentDate = new Date();
        let dateString = currentDate.toLocaleDateString('en-US', {
            day: '2-digit',
            month: 'short',
            year: '2-digit'
        }).split(' '); // Split into parts

        // Rearrange to dd-mmm-yy format
        dateString = `${dateString[1]}-${dateString[0]}-${dateString[2]}`;

        // Get the site name from the analyzed URL with validation
        let siteName = 'website';
        try {
            if (results.url) {
                // Remove any whitespace and ensure URL has protocol
                const cleanUrl = results.url.trim();
                const urlWithProtocol = cleanUrl.startsWith('http') ? cleanUrl : `https://${cleanUrl}`;
                const urlObj = new URL(urlWithProtocol);
                siteName = urlObj.hostname || 'website';
            }
        } catch (e) {
            console.warn('Invalid URL, using default site name:', e);
            // Try to extract domain-like string from the URL
            if (results.url) {
                siteName = results.url.replace(/^https?:\/\/|www\.|\/.*$/g, '') || 'website';
            }
        }

        let $resultsContent;
        let isPopup = $widget.data('popup') === true;

        // Check if it's a popup or non-popup version
        if (isPopup) {
            // For popup version, get content from popup results
            $resultsContent = $(`#${widgetId}-popup-results`).clone();
            if (!$resultsContent.length) {
                // Fallback to popup content
                $resultsContent = $(`#${widgetId}-popup .seo-analyzer-results`).clone();
            }
        } else {
            // Non-popup version
            $resultsContent = $widget.find('.seo-analyzer-results').clone();
        }

        // Check if NLP is enabled for this specific widget instance
        const nlpEnabled = $widget.data('nlp') === true;

        // Only include NLP results if NLP is enabled for this widget
        if (nlpEnabled) {
            // For popup version, we need to look in both popup and original widget
            let $nlpResult;
            if (isPopup) {
                $nlpResult = $(`#${widgetId}-popup .nlp-result`).first();
                if (!$nlpResult.length) {
                    $nlpResult = $widget.find('.nlp-result').first();
                }
            } else {
                $nlpResult = $widget.find('.nlp-result').first();
            }

            if ($nlpResult.length > 0) {
                // Remove any existing NLP results in detailed results
                $resultsContent.find('.detailed-results .nlp-result').remove();
                
                // Clone the NLP result
                let $nlpClone = $nlpResult.clone();
                
                // Find the "Detailed Results" heading in the cloned content
                let $detailedHeading = $resultsContent.find('.detailed-results h2:contains("Detailed Results")');
                
                // Insert the cloned NLP result after the heading
                if ($detailedHeading.length > 0) {
                    $detailedHeading.after($nlpClone);
                } else {
                    $resultsContent.find('.detailed-results').prepend($nlpClone);
                }
            }
        } else {
            // Remove any NLP results if NLP is disabled for this widget
            $resultsContent.find('.nlp-result').remove();
            
            // Also remove any Content Analysis sections from the results
            $resultsContent.find('.nlp-accordion').remove();
            $resultsContent.find('.content-analysis-section').remove();
            $resultsContent.find('.ai-summary').remove();
        }

        // Modify the HTML structure for PDF
        $resultsContent.find('.result-summary').replaceWith(generatePDFResultSummary(results));

        // Remove the duplicate 'SEO Analysis' and 'Category Scores' sections
        $resultsContent.find('.openai-summary-category-scores').remove();

        // Include the detailed results
        let $detailedResults = $resultsContent.find('.detailed-results');
        if ($detailedResults.length === 0) {
            if (isPopup) {
                $detailedResults = $(`#${widgetId}-popup .detailed-results`).clone();
                if (!$detailedResults.length) {
                    $detailedResults = $widget.find('.detailed-results').clone();
                }
            } else {
                $detailedResults = $widget.find('.detailed-results').clone();
            }
            $resultsContent.append($detailedResults);
        }

        // Make sure the detailed results are visible
        $detailedResults.show();

        // Get the HTML content as a string
        let resultsHtml = $resultsContent.html();

        // Rest of the AJAX call remains the same
        $.ajax({
            url: seo_analyzer_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'download_pdf_report',
                nonce: seo_analyzer_ajax.nonce,
                results: JSON.stringify(results),
                site_name: siteName,
                date: dateString,
                results_html: resultsHtml
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function(response) {
                // Create a download link
                const url = window.URL.createObjectURL(response);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = generateReportFilename(results.url, dateString);
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                
                // Reset the download button state
                $downloadButton.prop('disabled', false);
                $loaderContainer.remove();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error generating PDF:', textStatus, errorThrown);
                alert('Error generating PDF report. Please try again.');
                
                // Reset the download button state
                $downloadButton.prop('disabled', false);
                $loaderContainer.remove();
            }
        });
    }

    function generatePDFResultSummary(results) {
        const isComparison = results.competitor !== undefined;
        // Competitor scores are only safe to read when the competitor actually succeeded.
        const competitorAvailable = seoaCompetitorAvailable(results);
        // Page Performance may be unavailable (null) after a failed PageSpeed retry.
        const mainPP = seoaCategoryScore(results.category_scores && results.category_scores.page_performance);
        const compPP = competitorAvailable ? seoaCategoryScore(results.competitor.category_scores.page_performance) : null;
        const ppNum = (v) => (v === null ? 0 : Math.round(v));
        const ppTxt = (v) => (v === null ? 'Data unavailable' : (Math.round(v) + '%'));
        const ppTier = (v) => (v === null ? 'Unavailable' : getPerformanceTier(v));
        const ppColor = (v) => (v === null ? '#e9ecef' : getProgressBarColor(v));

        // Get domain names with validation
        let mainDomain = 'Website 1';
        let competitorDomain = 'Website 2';
        
        try {
            if (results.url && results.url.includes('://')) {
                mainDomain = new URL(results.url).hostname;
            } else if (results.url) {
                mainDomain = new URL('https://' + results.url).hostname;
            }
        } catch (e) {
            console.warn('Invalid main URL in PDF summary:', results.url);
            mainDomain = results.url || 'Website 1';
        }

        try {
            if (isComparison && results.competitor && results.competitor.url) {
                if (results.competitor.url.includes('://')) {
                    competitorDomain = new URL(results.competitor.url).hostname;
                } else {
                    competitorDomain = new URL('https://' + results.competitor.url).hostname;
                }
            }
        } catch (e) {
            console.warn('Invalid competitor URL in PDF summary:', results.competitor?.url);
            competitorDomain = results.competitor?.url || 'Website 2';
        }

        let html = `
            <table style="width: 100%; border-collapse: separate; border-spacing: 20px 0;">
                <tr>
                    <td style="width: 48%; padding: 20px; background: linear-gradient(84.18deg, #DFEFFF 15.54%, #FFFFFF 97.88%); border-radius: 10px; vertical-align: top;">
                        <h5 style="margin: 0; padding-bottom: 10px; font-size: 16px; color: #333;">${mainDomain} Overall Score:</h5>
                        <br>
                        <h3 style="margin: 0; padding-bottom: 10px; font-size: 24px; color: #333;">${Math.round(results.overall_score)}%</h3>
                        <p style="margin: 5px 0; font-size: 14px; font-weight: bold; color: #333;">${getPerformanceTier(results.overall_score)}</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                            <tr>
                                <td style="width: ${Math.round(results.overall_score)}%; background-color: ${getProgressBarColor(results.overall_score)}; height: 20px; border-radius: 5px 0 0 5px;"></td>
                                <td style="width: ${100 - Math.round(results.overall_score)}%; background-color: #e9ecef; height: 20px; border-radius: 0 5px 5px 0;"></td>
                            </tr>
                        </table>
                        <h5 style="margin: 20px 0 10px 0; font-size: 16px; color: #333;">Page Performance:</h5>
                        <br>
                        <h3 style="margin: 0; padding-bottom: 10px; font-size: 24px; color: #333;">${ppTxt(mainPP)}</h3>
                        <p style="margin: 5px 0; font-size: 14px; font-weight: bold; color: #333;">${ppTier(mainPP)}</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                            <tr>
                                <td style="width: ${ppNum(mainPP)}%; background-color: ${ppColor(mainPP)}; height: 20px; border-radius: 5px 0 0 5px;"></td>
                                <td style="width: ${100 - ppNum(mainPP)}%; background-color: #e9ecef; height: 20px; border-radius: 0 5px 5px 0;"></td>
                            </tr>
                        </table>
                    </td>
                    ${isComparison ? `
                    <td style="width: 48%; padding: 20px; background: linear-gradient(84.18deg, #DFEFFF 15.54%, #FFFFFF 97.88%); border-radius: 10px; vertical-align: top;">
                        <h5 style="margin: 0; padding-bottom: 10px; font-size: 16px; color: #333;">${competitorDomain} Overall Score:</h5>
                        <br>
                        ${competitorAvailable ? `
                        <h3 style="margin: 0; padding-bottom: 10px; font-size: 24px; color: #333;">${Math.round(results.competitor.overall_score)}%</h3>
                        <p style="margin: 5px 0; font-size: 14px; font-weight: bold; color: #333;">${getPerformanceTier(results.competitor.overall_score)}</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                            <tr>
                                <td style="width: ${Math.round(results.competitor.overall_score)}%; background-color: ${getProgressBarColor(results.competitor.overall_score)}; height: 20px; border-radius: 5px 0 0 5px;"></td>
                                <td style="width: ${100 - Math.round(results.competitor.overall_score)}%; background-color: #e9ecef; height: 20px; border-radius: 0 5px 5px 0;"></td>
                            </tr>
                        </table>
                        <h5 style="margin: 20px 0 10px 0; font-size: 16px; color: #333;">Page Performance:</h5>
                        <br>
                        <h3 style="margin: 0; padding-bottom: 10px; font-size: 24px; color: #333;">${ppTxt(compPP)}</h3>
                        <p style="margin: 5px 0; font-size: 14px; font-weight: bold; color: #333;">${ppTier(compPP)}</p>
                        <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                            <tr>
                                <td style="width: ${ppNum(compPP)}%; background-color: ${ppColor(compPP)}; height: 20px; border-radius: 5px 0 0 5px;"></td>
                                <td style="width: ${100 - ppNum(compPP)}%; background-color: #e9ecef; height: 20px; border-radius: 0 5px 5px 0;"></td>
                            </tr>
                        </table>
                        ` : `
                        <p style="margin: 5px 0; font-size: 14px; font-weight: bold; color: #333;">Competitor analysis is temporarily unavailable.</p>
                        `}
                    </td>
                    ` : ''}
                </tr>
            </table>
            <table style="width: 100%; border-collapse: separate; border-spacing: 20px 0;">
                <tr>
                    <td style="width: 48%; vertical-align: top; padding-right: 15px;">
                        <br>
                        <h3 style="margin-top: 0; color: #333;">SEO Analysis:</h3>
                        <br>
                        <div style="font-size: 14px; line-height: 1.6;">
                            ${formatOpenAISummaryForPDF(results.openai_summary)}
                        </div>
                    </td>
                    <td style="width: 48%; vertical-align: top;">
                        <br>
                        <h3 style="margin-top: 0; color: #333;">Category Scores</h3>
                        <br>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr>
                                    <th style="border: 1px solid #3095ff; padding: 10px; text-align: left; background-color: #f2f2f2; color: #333;">Category</th>
                                    <th style="border: 1px solid #3095ff; padding: 10px; text-align: left; background-color: #f2f2f2; color: #333;">${mainDomain}</th>
                                    ${isComparison ? `<th style="border: 1px solid #3095ff; padding: 10px; text-align: left; background-color: #f2f2f2; color: #333;">${competitorDomain}</th>` : ''}
                                </tr>
                            </thead>
                            <tbody>
                                ${Object.entries(results.category_scores).map(([category, data]) => {
                                    const mScore = seoaCategoryScore(data);
                                    const cCat = competitorAvailable ? results.competitor.category_scores[category] : null;
                                    const cScore = seoaCategoryScore(cCat);
                                    return `
                                    <tr>
                                        <td style="border: 1px solid #3095ff; padding: 10px; color: #333;">${formatCategoryName(category)}</td>
                                        <td style="border: 1px solid #3095ff; padding: 10px; text-align: center; font-weight: bold; color: ${getScoreColor(mScore === null ? 0 : mScore)};">${mScore === null ? 'N/A' : data.score}</td>
                                        ${isComparison ? `<td style="border: 1px solid #3095ff; padding: 10px; text-align: center; font-weight: bold; color: ${getScoreColor(cScore === null ? 0 : cScore)};">${cScore === null ? 'N/A' : cCat.score}</td>` : ''}
                                    </tr>
                                `;
                                }).join('')}
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>
        `;
        return html;
    }

    function getProgressBarColor(score) {
        if (score >= 90) return '#28a745';
        if (score >= 70) return '#ffc107';
        if (score >= 50) return '#fd7e14';
        return '#dc3545';
    }

    function getScoreColor(score) {
        if (score >= 90) return '#3c763d';
        if (score >= 70) return '#8a6d3b';
        return '#a94442';
    }

    // Update the displayNLPResults function
    function displayNLPResults(data, $widget, type) {
        const { openai_summary } = data;

        let nlpHtml = `
            <div class="nlp-accordion ${type}-nlp-results">
                <h4>Content Analysis</h4>
                <div class="accordion-item">
                    <div class="accordion-header" data-accordion="summary-${type}">AI-Generated Summary</div>
                    <div class="accordion-content" id="summary-${type}">
                        <p>${openai_summary}</p>
                    </div>
                </div>
            </div>
        `;

        var $nlpResult = $widget.find('.nlp-result');
        $nlpResult.html(nlpHtml).show();

        // Add accordion functionality
        $nlpResult.find('.accordion-header').on('click', function() {
            var $content = $(this).next('.accordion-content');
            $content.slideToggle();
            $(this).toggleClass('active');
        });

        // ... rest of the function remains unchanged
    }

    function logUserSubmission(name, email, widgetId) {
        console.log('User Details Submitted:', {
            timestamp: new Date().toISOString(),
            name: name,
            email: email,
            widgetId: widgetId,
            analysisType: $('#' + widgetId).data('comparison') ? 'Comparison Analysis' : 'Single URL Analysis'
        });
    }

    // Add helper function to check submission status
    function checkUserSubmissionStatus(widgetId) {
        try {
            // First check memory
            if (window.seoAnalyzerSubmittedUsers && window.seoAnalyzerSubmittedUsers[widgetId] === true) {
                return true;
            }
            
            // Then check localStorage
            const storedUsers = JSON.parse(localStorage.getItem('seoAnalyzerSubmittedUsers')) || {};
            return storedUsers[widgetId] === true;
        } catch (e) {
            console.warn('Error checking submission status:', e);
            // Fallback to memory check only
            return window.seoAnalyzerSubmittedUsers && window.seoAnalyzerSubmittedUsers[widgetId] === true;
        }
    }

    function updateAnalysisResults(data) {
        // Update each section with its specific content
        $('.seo-results').html(data.seoAnalysis);
        $('.content-results').html(data.contentAnalysis);
    }

    $(document).on('click', '.add-recommendation-btn', function() {
        const category = $(this).data('category');
        const factor = $(this).data('factor');
        const score = $(this).data('score');
        
        // Here you can implement the logic to add recommendations
        // For example, open a modal or form to input recommendations
        console.log(`Add recommendation for ${category} - ${factor} (Score: ${score})`);
    });

    // Add this function to initialize the redirect URL
    function initializeRedirectUrl() {
        // This will be set by PHP in the page
        window.seoAnalyzerRedirectUrl = window.seoAnalyzerRedirectUrl || '';
    }

    $(document).ready(function() {
        initializeRedirectUrl();
        // ... rest of your document ready code ...
    });

    // Add this event handler for the recommendations button
    $(document).on('click', '.get-recommendations-btn', function(e) {
        e.preventDefault();
        const $widget = $(this).closest('.seo-analyzer-widget');
        const widgetId = $widget.attr('id');
        
        // Show loading state
        $(this).prop('disabled', true).text('Loading recommendations...');
        
        $.ajax({
            url: seo_analyzer_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_recommendations',
                nonce: seo_analyzer_ajax.nonce,
                widget_id: widgetId
            },
            success: function(response) {
                if (response.success) {
                    const formattedRecommendations = formatOpenAISummary(response.data.recommendations);
                    $widget.find('.recommendations-content').html(formattedRecommendations).show();
                } else {
                    alert('Failed to get recommendations: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error getting recommendations:', error);
                alert('Failed to get recommendations. Please try again.');
            },
            complete: function() {
                $('.get-recommendations-btn').prop('disabled', false).text('Get Recommendations');
            }
        });
    });
});

function isValidEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

function getScoreClass(score) {
    if (score === 'N/A') return 'score-na';
    score = parseInt(score);
    if (score >= 90) return 'score-good';
    if (score >= 50) return 'score-average';
    return 'score-poor';
}

// Add this helper function for PDF summary formatting
function formatOpenAISummaryForPDF(summary) {
    if (!summary) return '';
    
    // Remove markdown headers (###)
    summary = summary.replace(/###\s*/g, '');
    
    // Remove markdown bold (**) while keeping the text
    summary = summary.replace(/\*\*(.*?)\*\*/g, '$1');
    
    // Format the text with proper line breaks and indentation
    const lines = summary.split('\n').map(line => line.trim()).filter(line => line);
    let formattedHtml = '';
    let inList = false;
    
    lines.forEach(line => {
        // Check if line is a main section
        if (line.toLowerCase().includes('summary:')) {
            if (inList) {
                formattedHtml += '</ul>';
                inList = false;
            }
            formattedHtml += `<p style="font-weight: bold; margin: 15px 0 10px 0;">${line}</p><br>`;
        }
        // Check if line is recommendations section
        else if (line.toLowerCase().includes('recommendations:')) {
            if (inList) {
                formattedHtml += '</ul>';
                inList = false;
            }
            formattedHtml += `<br><p style="font-weight: bold; margin: 15px 0 10px 0;">${line}</p><br>`;
        }
        // Check if line is a numbered item
        else if (/^\d+\./.test(line)) {
            if (!inList) {
                formattedHtml += '<ul style="list-style-type: decimal; margin: 10px 0 10px 20px; padding-left: 20px;">';
                inList = true;
            }
            // Extract the text after the number
            const text = line.replace(/^\d+\.\s*/, '').trim();
            formattedHtml += `<li style="margin-bottom: 10px;">${text}</li>`;
        }
        // Check if line is a bullet point
        else if (line.startsWith('-')) {
            const text = line.replace(/^-\s*/, '').trim();
            formattedHtml += `<p style="margin: 5px 0 5px 20px;">• ${text}</p>`;
        }
        // Regular paragraph
        else if (line) {
            if (inList) {
                formattedHtml += '</ul>';
                inList = false;
            }
            formattedHtml += `<p style="margin: 10px 0;">${line}</p>`;
        }
    });

    if (inList) {
        formattedHtml += '</ul>';
    }
    
    return formattedHtml;
}

// Add this new helper function after the downloadPDFReport function
function generateReportFilename(url, dateString) {
    try {
        // Clean and parse the URL
        let cleanUrl = url.trim();
        if (!cleanUrl.startsWith('http')) {
            cleanUrl = 'https://' + cleanUrl;
        }
        
        const urlObj = new URL(cleanUrl);
        let domain = urlObj.hostname;
        
        // Get the path and remove leading/trailing slashes
        let path = urlObj.pathname.replace(/^\/|\/$/g, '');
        
        // If there's a path, append it to domain with underscores
        if (path) {
            path = path.replace(/\//g, '_');
            domain = `${domain}_${path}`;
        }
        
        // Remove any special characters and spaces
        domain = domain.replace(/[^a-zA-Z0-9._-]/g, '');
        
        return `SEO_Analysis_${domain}_${dateString}.pdf`;
    } catch (e) {
        console.warn('Error generating filename:', e);
        // Fallback to simple domain if URL parsing fails
        const fallbackDomain = url.replace(/^https?:\/\/|www\.|\/.*$/g, '') || 'website';
        return `SEO_Analysis_${fallbackDomain}_${dateString}.pdf`;
    }
}
















