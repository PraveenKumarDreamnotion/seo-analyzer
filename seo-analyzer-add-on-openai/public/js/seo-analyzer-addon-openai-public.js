jQuery(document).ready(function($) {
    // Initialize session request counter
    if (!sessionStorage.getItem('recommendationRequests')) {
        sessionStorage.setItem('recommendationRequests', '0');
    }

    // Add this function to show the recommendations limit popup
    function showRecommendationsLimitPopup() {
        // Remove any existing popup
        $('.recommendations-limit-popup').remove();

        // Capture form values when popup is shown using correct selectors
        const $form = $('.seo-analyzer-form');
        const formData = {
            url: $form.find('input.seo-analyzer-url').val() || '',
            competitorUrl: $form.find('input.seo-analyzer-competitor-url').val() || '',
            keyword: $form.find('input.seo-analyzer-keyword').val() || ''
        };
        
        console.log('Captured form data:', formData);

        // Validate required fields
        if (!formData.url || !formData.keyword) {
            console.log('Required fields missing:', { url: formData.url, keyword: formData.keyword });
            return;
        }

        // Create popup HTML with hidden fields
        const popupHtml = `
            <div class="seo-analyzer-popup recommendations-limit-popup" style="display: none;">
                <div class="seo-analyzer-popup-content">
                    <span class="seo-analyzer-close">&times;</span>
                    <h3>You've reached the limit of free recommendations</h3>
                    <p>Would you like to:</p>
                    <div class="recommendations-buttons">
                        <button class="get-report-btn">Get Complete Report with AI Suggestions</button>
                        <button class="get-assistance-btn">Get Expert Assistance</button>
                    </div>
                    <div class="email-input-container" style="margin-top: 20px;">
                        <input type="email" class="user-email" placeholder="Enter your email" required>
                    </div>
                    <input type="hidden" name="url" value="${encodeURIComponent(formData.url)}">
                    <input type="hidden" name="competitor_url" value="${encodeURIComponent(formData.competitorUrl)}">
                    <input type="hidden" name="keyword" value="${encodeURIComponent(formData.keyword)}">
                    <div class="popup-message" style="display: none; margin-top: 15px;"></div>
                </div>
            </div>
        `;

        // Append popup to body
        $('body').append(popupHtml);

        // Show popup
        $('.recommendations-limit-popup').show();

        // Handle close button
        $('.recommendations-limit-popup .seo-analyzer-close').on('click', function() {
            $('.recommendations-limit-popup').remove();
        });

        function showMessage(message, isSuccess) {
            const $messageDiv = $('.popup-message');
            $messageDiv.removeClass('success-message error-message show')
                .addClass(isSuccess ? 'success-message' : 'error-message')
                .html(message)
                .show();
            // Force reflow
            $messageDiv[0].offsetHeight;
            // Add show class for animation
            $messageDiv.addClass('show');
        }

        function handleBrevoSubmission(choice) {
            const email = $('.recommendations-limit-popup .user-email').val();
            
            // Get values from hidden fields
            const url = $('.recommendations-limit-popup input[name="url"]').val();
            const competitorUrl = $('.recommendations-limit-popup input[name="competitor_url"]').val();
            const keyword = $('.recommendations-limit-popup input[name="keyword"]').val();

            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address.', false);
                return;
            }

            // Disable buttons during submission
            $('.recommendations-buttons button').prop('disabled', true);

            // Prepare data for Brevo
            const data = {
                action: 'handle_brevo_submission',
                nonce: seoAnalyzerAddonOpenAI.brevo_nonce,
                email: email,
                url: decodeURIComponent(url),
                competitor_url: decodeURIComponent(competitorUrl),
                keyword: decodeURIComponent(keyword),
                choice: choice,
                tool: 'SEO Analyzer'
            };

            console.log('Data being sent to Brevo:', data);

            $.ajax({
                url: seoAnalyzerAddonOpenAI.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        showMessage('Thank you! We will contact you shortly.', true);
                        setTimeout(() => {
                            $('.recommendations-limit-popup').remove();
                        }, 2000);
                    } else {
                        showMessage(response.data || 'An error occurred. Please try again.', false);
                    }
                },
                error: function() {
                    showMessage('An error occurred. Please try again.', false);
                },
                complete: function() {
                    $('.recommendations-buttons button').prop('disabled', false);
                }
            });
        }

        // Email validation helper function
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Handle button clicks
        $('.get-report-btn').on('click', function() {
            handleBrevoSubmission('report');
        });

        $('.get-assistance-btn').on('click', function() {
            handleBrevoSubmission('assistance');
        });
    }

    $(document).on('click', '.add-recommendation-btn', function() {
        const currentRequests = parseInt(sessionStorage.getItem('recommendationRequests'));
        
        if (currentRequests >= 3) {
            // Show popup message instead of alert
            showRecommendationsLimitPopup();
            return;
        }

        const $button = $(this);
        const category = $button.data('category');
        const factor = $button.data('factor');
        const score = $button.data('score');
        const categoryScore = $button.data('category-score');
        
        // Create and show loading spinner
        const $spinner = $('<div class="recommendation-loader"><div class="spinner"></div><div class="loading-text">Generating recommendations...</div></div>');
        $button.replaceWith($spinner);
        
        // Make AJAX call to generate recommendations
        $.ajax({
            url: seoAnalyzerAddonOpenAI.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_factor_recommendation',
                nonce: seoAnalyzerAddonOpenAI.nonce,
                category: category,
                factor: factor,
                score: score,
                category_score: categoryScore
            },
            success: function(response) {
                if (response.success) {
                    // Increment request counter on successful response
                    sessionStorage.setItem('recommendationRequests', (currentRequests + 1).toString());
                    
                    // Create recommendations container and ensure proper HTML structure
                    const recommendations = response.data.recommendations;
                    const $recommendations = $('<div class="factor-recommendations"></div>');
                    
                    // Check if recommendations already has ul tags
                    if (!recommendations.includes('<ul>')) {
                        // Wrap the content in ul tags if not already present
                        $recommendations.html('<ul>' + recommendations + '</ul>');
                    } else {
                        $recommendations.html(recommendations);
                    }
                    
                    // Replace loader with recommendations
                    $spinner.replaceWith($recommendations);
                } else {
                    // Show error and restore button
                    $spinner.replaceWith($button);
                    alert('Failed to generate recommendations: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                // Show error and restore button
                $spinner.replaceWith($button);
                console.error('Error generating recommendations:', error);
                alert('Failed to generate recommendations. Please try again.');
            }
        });
    });
});