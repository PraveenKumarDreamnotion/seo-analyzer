<?php
class Halo_Shortcode_Test extends WP_UnitTestCase
{
    public function test_shortcode_renders_the_form_and_enqueues_assets(): void
    {
        $html = do_shortcode('[seo_analyzer title="Halo <Audit>" comparison="true" popup="true" nlp="true" competitor="true"]');

        $this->assertStringContainsString('class="halo-audit"', $html);
        $this->assertStringContainsString('Halo &lt;Audit&gt;', $html);
        $this->assertStringContainsString('name="url"', $html);
        $this->assertStringContainsString('name="keyword"', $html);
        $this->assertStringNotContainsString('competitor_url', $html);
        $this->assertTrue(wp_script_is('halo-audit', 'enqueued'));
        $this->assertTrue(wp_style_is('halo-audit', 'enqueued'));
        // Older WordPress escapes the slashes in localised data; newer versions do not.
        $data = str_replace('\/', '/', (string) wp_scripts()->get_data('halo-audit', 'data'));
        $this->assertStringContainsString('halo-audit/v1/', $data);
    }

    public function test_script_has_no_inner_html_assignment(): void
    {
        $js = file_get_contents(SEO_ANALYZER_PLUGIN_DIR . 'public/js/halo-audit.js');
        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringNotContainsString('insertAdjacentHTML', $js);
        $this->assertStringNotContainsString('document.write', $js);
        $this->assertStringNotContainsString('console.log', $js);
    }
}
