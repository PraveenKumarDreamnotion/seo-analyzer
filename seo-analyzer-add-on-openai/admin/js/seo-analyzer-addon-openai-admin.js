(function($) {
    'use strict';

    $(function() {
        $('#validate-api-key').on('click', function(e) {
            e.preventDefault();
            var apiKey = $('input[name="seo_analyzer_addon_openai_api_key"]').val();
            var statusSpan = $('#api-key-status');

            $.ajax({
                url: seoAnalyzerAddonOpenAI.ajax_url,
                type: 'POST',
                data: {
                    action: 'validate_api_key',
                    nonce: seoAnalyzerAddonOpenAI.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success && response.data.is_valid) {
                        statusSpan.text('API key is valid').css('color', 'green');
                    } else {
                        statusSpan.text('API key is invalid').css('color', 'red');
                    }
                },
                error: function() {
                    statusSpan.text('Error validating API key').css('color', 'red');
                }
            });
        });
    });
})(jQuery);