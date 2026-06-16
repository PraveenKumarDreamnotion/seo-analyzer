<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <p>Welcome to the SEO Analyzer plugin. Use the menu on the left to navigate to different sections.</p>

    <!-- Add Shortcode Information Section -->
    <div class="seo-analyzer-shortcode-info">
        <h2><?php esc_html_e('Shortcode Usage', 'seo-analyzer'); ?></h2>
        <p><?php esc_html_e('Use the following shortcode to display the SEO Analyzer tool on any page or post:', 'seo-analyzer'); ?></p>
        
        <div class="shortcode-example">
            <code>[seo_analyzer]</code>
            <button class="button copy-shortcode" data-shortcode="[seo_analyzer]">
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e('Copy', 'seo-analyzer'); ?>
            </button>
        </div>

        <h3><?php esc_html_e('Available Options', 'seo-analyzer'); ?></h3>
        <p><?php esc_html_e('You can customize the analyzer using these attributes:', 'seo-analyzer'); ?></p>
        
        <div class="shortcode-example">
            <code>[seo_analyzer title="My SEO Tool" popup="true" comparison="true" nlp="true"]</code>
            <button class="button copy-shortcode" data-shortcode='[seo_analyzer title="My SEO Tool" popup="true" comparison="true" nlp="true"]'>
                <span class="dashicons dashicons-clipboard"></span>
                <?php esc_html_e('Copy', 'seo-analyzer'); ?>
            </button>
        </div>

        <table class="shortcode-attributes-table">
            <tr>
                <th>title</th>
                <td><?php esc_html_e('Custom title for the analyzer (default: "SEO Analyzer")', 'seo-analyzer'); ?></td>
            </tr>
            <tr>
                <th>popup</th>
                <td><?php esc_html_e('Show results in a popup (true/false, default: false)', 'seo-analyzer'); ?></td>
            </tr>
            <tr>
                <th>comparison</th>
                <td><?php esc_html_e('Enable competitor comparison (true/false, default: false)', 'seo-analyzer'); ?></td>
            </tr>
            <tr>
                <th>nlp</th>
                <td><?php esc_html_e('Enable NLP analysis (requires OpenAI addon) (true/false, default: false)', 'seo-analyzer'); ?></td>
            </tr>
        </table>
    </div>
</div>

<style>
.seo-analyzer-shortcode-info {
    background: #fff;
    padding: 25px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
    max-width: 900px;
}

.seo-analyzer-shortcode-info h2 {
    margin-top: 0;
    color: #1d2327;
}

.shortcode-example {
    background: #f0f0f1;
    padding: 15px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 15px;
    margin: 15px 0;
}

.shortcode-example code {
    background: none;
    padding: 0;
    font-size: 14px;
}

.copy-shortcode {
    display: flex !important;
    align-items: center;
    gap: 5px;
}

.copy-shortcode .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.shortcode-attributes-table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 20px;
}

.shortcode-attributes-table th,
.shortcode-attributes-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e4e7;
}

.shortcode-attributes-table th {
    width: 120px;
    color: #1d2327;
    font-weight: 600;
}

.shortcode-attributes-table td {
    color: #50575e;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.copy-shortcode').on('click', function() {
        const shortcode = $(this).data('shortcode');
        navigator.clipboard.writeText(shortcode).then(function() {
            const $button = $(this);
            const originalText = $button.text();
            $button.text('Copied!');
            setTimeout(function() {
                $button.html('<span class="dashicons dashicons-clipboard"></span> Copy');
            }, 2000);
        }.bind(this));
    });
});
</script>