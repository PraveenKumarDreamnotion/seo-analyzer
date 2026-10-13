<?php
namespace HaloAudit\Wp;

final class Shortcode
{
    private const HANDLE = 'halo-audit';
    private const DEFAULT_NAME = 'Halo Audit';

    public static function register(): void
    {
        add_shortcode('seo_analyzer', [self::class, 'render']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueueForPage']);
    }

    public static function registerAssets(): void
    {
        if (wp_script_is(self::HANDLE, 'registered')) {
            return;
        }
        wp_register_style(self::HANDLE, SEO_ANALYZER_PLUGIN_URL . 'public/css/halo-audit.css', [], self::version('public/css/halo-audit.css'));
        wp_register_script(self::HANDLE, SEO_ANALYZER_PLUGIN_URL . 'public/js/halo-audit.js', [], self::version('public/js/halo-audit.js'), true);
        // Only the REST address. The nonce is fetched at run time, so a cached page never holds a stale one.
        wp_localize_script(self::HANDLE, 'HaloAudit', ['root' => esc_url_raw(rest_url(RestController::NS . '/'))]);
    }

    /** Puts the stylesheet in the head when the page itself holds the shortcode. render() covers every other placement. */
    public static function enqueueForPage(): void
    {
        self::registerAssets();
        $post = is_singular() ? get_post() : null;
        if ($post instanceof \WP_Post && has_shortcode((string) $post->post_content, 'seo_analyzer')) {
            wp_enqueue_style(self::HANDLE);
        }
    }

    public static function render($atts): string
    {
        // popup, comparison, nlp and competitor are accepted so that existing pages do not break. They do nothing.
        $atts = shortcode_atts(
            ['title' => self::DEFAULT_NAME, 'heading_level' => '2', 'popup' => '', 'comparison' => '', 'nlp' => '', 'competitor' => ''],
            $atts,
            'seo_analyzer'
        );
        $title = trim((string) $atts['title']);
        $level = (int) $atts['heading_level'];
        if ($level < 2 || $level > 5) {
            $level = 2;
        }
        $id = wp_unique_id('halo-audit-');

        self::registerAssets();
        wp_enqueue_style(self::HANDLE);
        wp_enqueue_script(self::HANDLE);

        // With title="" the page supplies the heading, and the region is named by aria-label.
        $name = $title !== ''
            ? 'aria-labelledby="' . esc_attr($id) . '-title"'
            : 'aria-label="' . esc_attr(self::DEFAULT_NAME) . '"';

        ob_start();
        ?>
        <section class="halo-audit" id="<?php echo esc_attr($id); ?>" data-state="idle" data-heading-level="<?php echo esc_attr((string) $level); ?>" <?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts ?>>
            <?php if ($title !== '') : ?>
            <h<?php echo (int) $level; ?> class="halo-audit__title" id="<?php echo esc_attr($id); ?>-title"><?php echo esc_html($title); ?></h<?php echo (int) $level; ?>>
            <?php endif; ?>
            <form class="halo-audit__form" novalidate>
                <label class="halo-audit__field">
                    <span class="halo-audit__label">Page address</span>
                    <input type="text" name="url" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="https://example.com/page" required>
                </label>
                <label class="halo-audit__field">
                    <span class="halo-audit__label">Primary keyword</span>
                    <input type="text" name="keyword" placeholder="The keyword this page should rank for" maxlength="255" required>
                </label>
                <button type="submit" class="halo-audit__button">Analyse</button>
            </form>
            <p class="halo-audit__status" id="<?php echo esc_attr($id); ?>-status" role="status" aria-live="polite"></p>
            <div class="halo-audit__results" hidden></div>
            <noscript><p>This tool needs JavaScript.</p></noscript>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private static function version(string $file): string
    {
        $time = @filemtime(SEO_ANALYZER_PLUGIN_DIR . $file);
        return $time ? (string) $time : SEO_ANALYZER_VERSION;
    }
}
