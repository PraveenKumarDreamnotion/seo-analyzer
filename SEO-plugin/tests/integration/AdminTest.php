<?php
use HaloAudit\Wp\Admin\Admin;
use HaloAudit\Wp\Admin\ReportsTable;
use HaloAudit\Wp\Admin\Settings;
use HaloAudit\Wp\Installer;

class Halo_Admin_Test extends WP_UnitTestCase
{
    public static function set_up_before_class(): void
    {
        parent::set_up_before_class();
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        require_once ABSPATH . 'wp-admin/includes/template.php';
    }

    public function tear_down(): void
    {
        unset($_REQUEST['_wpnonce']);
        parent::tear_down();
    }

    private function seed(int $n): void
    {
        global $wpdb;
        for ($i = 1; $i <= $n; $i++) {
            $wpdb->insert(Installer::resultsTable(), [
                'report_key' => md5('k' . $i), 'name' => 'Lead ' . $i, 'email' => "lead$i@example.com", 'url' => "https://example.com/$i",
                'keyword' => 'k', 'results' => '{}', 'overall_score' => 50, 'grade' => 'C', 'lead_verified_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function test_export_is_refused_for_logged_out_visitors(): void
    {
        wp_set_current_user(0);
        $this->expectException(WPDieException::class);
        Admin::export();
    }

    public function test_export_is_refused_for_subscribers(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'subscriber']));
        $_REQUEST['_wpnonce'] = wp_create_nonce('halo_audit_export');
        $this->expectException(WPDieException::class);
        Admin::export();
    }

    public function test_export_is_refused_for_admin_without_nonce(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->expectException(WPDieException::class);
        Admin::export();
    }

    public function test_export_rows_hold_only_verified_leads(): void
    {
        global $wpdb;
        $this->seed(2);
        $wpdb->insert(Installer::resultsTable(), ['report_key' => md5('anon'), 'url' => 'https://anon.example/', 'keyword' => 'k', 'results' => '{}']);
        $rows = Admin::exportRows();
        $this->assertCount(2, $rows);
        $this->assertSame(['name', 'email', 'url', 'keyword', 'overall_score', 'grade', 'is_education', 'created_at'], array_keys($rows[0]));
    }

    public function test_csv_cells_cannot_start_a_formula(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\")", Admin::csvCell('=HYPERLINK("http://evil")'));
        $this->assertSame("'+1", Admin::csvCell('+1'));
        $this->assertSame("'-1", Admin::csvCell('-1'));
        $this->assertSame("'@x", Admin::csvCell('@x'));
        $this->assertSame('Asha', Admin::csvCell('Asha'));
    }

    public function test_reports_table_is_paginated(): void
    {
        $this->seed(25);
        set_current_screen('toplevel_page_seo-analyzer');
        $table = new ReportsTable();
        $table->prepare_items();
        $this->assertCount(20, $table->items);
        $this->assertSame(25, $table->get_pagination_arg('total_items'));
    }

    public function test_settings_are_registered_with_sanitisers(): void
    {
        Settings::register();
        $registered = get_registered_settings();
        foreach (['halo_audit_psi_key', 'seo_analyzer_brevo_api_key', 'seo_analyzer_sender_email', 'halo_audit_scan_url', 'halo_audit_scan_secret'] as $option) {
            $this->assertArrayHasKey($option, $registered, $option);
            $this->assertSame(Settings::GROUP, $registered[$option]['group']);
        }
        $this->assertSame('', sanitize_option('halo_audit_scan_url', 'javascript:alert(1)'));
        $this->assertSame('https://scan.example.com/', sanitize_option('halo_audit_scan_url', 'https://scan.example.com/'));
        $this->assertSame('', sanitize_option('seo_analyzer_sender_email', 'not an email'));
    }

    public function test_mail_failure_notice_shows_only_after_a_failed_send(): void
    {
        delete_option(\HaloAudit\Wp\Gate::MAIL_FAILURE_OPTION);
        $this->assertSame('', Admin::mailNotice());

        update_option(\HaloAudit\Wp\Gate::MAIL_FAILURE_OPTION, 1700000000, false);
        $html = Admin::mailNotice();
        $this->assertStringContainsString('notice-error', $html);
        $this->assertStringContainsString('could not send', $html);
        $this->assertStringContainsString(Settings::PAGE, $html);
    }

    public function test_settings_screen_says_what_is_optional_and_how_mail_is_sent(): void
    {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        Settings::register();
        ob_start();
        Settings::page();
        $html = ob_get_clean();

        $this->assertStringContainsString('Every setting is optional', $html);
        $this->assertStringContainsString('WordPress mail', $html);
        $this->assertStringContainsString('Not used in this version', $html);
        $this->assertStringNotContainsStringIgnoringCase('openai', $html);
    }

    public function test_old_admin_class_is_gone(): void
    {
        $this->assertFalse(class_exists('Seo_Analyzer_Admin'));
    }
}
