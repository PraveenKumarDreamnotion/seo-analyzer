<?php
class Halo_Hotfix_Test extends WP_UnitTestCase
{
    public function tear_down(): void
    {
        unset($_GET['page'], $_GET['action'], $_GET['id'], $_GET['_wpnonce']);
        parent::tear_down();
    }

    public function test_logged_out_visitor_cannot_view_a_report(): void
    {
        wp_set_current_user(0);
        $_GET['page'] = 'seo-analyzer-reports';
        $_GET['action'] = 'view';
        $_GET['id'] = '1';

        $this->expectException(WPDieException::class);
        (new Seo_Analyzer_Admin('seo-analyzer', '1.0.6'))->handle_report_actions();
    }

    public function test_admin_without_nonce_cannot_download(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $_GET['page'] = 'seo-analyzer-reports';
        $_GET['action'] = 'download';
        $_GET['id'] = '1';

        $this->expectException(WPDieException::class);
        (new Seo_Analyzer_Admin('seo-analyzer', '1.0.6'))->handle_report_actions();
    }

    public function test_open_endpoints_are_gone(): void
    {
        foreach (['test_wp_mail', 'save_competitor_score', 'save_competitor_data', 'save_competitor_results', 'save_competitor_overall_score'] as $action) {
            $this->assertFalse(has_action('wp_ajax_nopriv_' . $action), $action);
        }
    }

    public function test_cleanup_hook_is_gone(): void
    {
        $this->assertFalse(has_action('admin_init', 'seo_analyzer_cleanup_database'));
    }
}
