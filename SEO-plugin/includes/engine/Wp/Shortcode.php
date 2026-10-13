<?php
namespace HaloAudit\Wp;

final class Shortcode
{
    public static function register(): void
    {
        add_shortcode('seo_analyzer', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'registerAssets']);
    }

    public static function registerAssets(): void
    {
        wp_register_style('halo-audit', SEO_ANALYZER_PLUGIN_URL . 'public/css/halo-audit.css', [], SEO_ANALYZER_VERSION);
        wp_register_script('halo-audit', SEO_ANALYZER_PLUGIN_URL . 'public/js/halo-audit.js', [], SEO_ANALYZER_VERSION, true);
        wp_localize_script('halo-audit', 'HaloAudit', ['root' => esc_url_raw(rest_url(RestController::NS . '/'))]);
    }

    public static function render($atts): string
    {
        // popup, comparison, nlp and competitor are accepted so that existing pages do not break. They do nothing.
        $atts = shortcode_atts(['title' => 'Halo Audit', 'popup' => '', 'comparison' => '', 'nlp' => '', 'competitor' => ''], $atts, 'seo_analyzer');

        if (!wp_script_is('halo-audit', 'registered')) {
            self::registerAssets();
        }
        wp_enqueue_style('halo-audit');
        wp_enqueue_script('halo-audit');

        ob_start();
        ?>
        <div class="halo-audit">
            <h2 class="halo-audit__title"><?php echo esc_html($atts['title']); ?></h2>
            <form class="halo-audit__form" novalidate>
                <label class="halo-audit__field">
                    <span>Page address</span>
                    <input type="text" name="url" inputmode="url" autocomplete="url" placeholder="Enter your URL" required>
                </label>
                <label class="halo-audit__field">
                    <span>Primary keyword</span>
                    <input type="text" name="keyword" placeholder="Enter primary keyword" maxlength="255" required>
                </label>
                <button type="submit" class="halo-audit__button">Analyse</button>
            </form>
            <p class="halo-audit__status" role="status" aria-live="polite"></p>
            <div class="halo-audit__results" hidden></div>
            <noscript><p>This tool needs JavaScript.</p></noscript>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
