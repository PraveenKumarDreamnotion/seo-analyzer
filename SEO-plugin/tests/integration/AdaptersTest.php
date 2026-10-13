<?php
use HaloAudit\Wp\Plugin;
use HaloAudit\Wp\WpHttpClient;

class Halo_Adapters_Test extends WP_UnitTestCase
{
    public function test_http_client_maps_a_response_and_does_not_follow_redirects(): void
    {
        $seen = [];
        add_filter('pre_http_request', static function ($pre, $args, $url) use (&$seen) {
            $seen = $args;
            return ['headers' => ['Location' => 'https://example.com/next', 'X-A' => ['1', '2']], 'body' => 'hello', 'response' => ['code' => 301, 'message' => 'Moved'], 'cookies' => []];
        }, 10, 3);

        $response = (new WpHttpClient())->request('GET', 'https://example.com/', 7, 1234);

        $this->assertSame(301, $response->status);
        $this->assertSame('hello', $response->body);
        $this->assertSame('https://example.com/next', $response->header('location'));
        $this->assertSame('1, 2', $response->header('x-a'));
        $this->assertSame(0, $seen['redirection']);
        $this->assertSame(7, $seen['timeout']);
        $this->assertSame(1234, $seen['limit_response_size']);
        $this->assertTrue($seen['sslverify']);
        $this->assertTrue($seen['reject_unsafe_urls']);
        $this->assertStringContainsString('HaloAudit', $seen['user-agent']);
    }

    public function test_user_agent_names_this_site_and_can_be_filtered(): void
    {
        $this->assertSame('Mozilla/5.0 (compatible; HaloAudit/2.0; +' . home_url('/') . ')', WpHttpClient::userAgent());
        add_filter('halo_audit_user_agent', static fn() => 'CustomBot/1.0');
        $this->assertSame('CustomBot/1.0', WpHttpClient::userAgent());
    }

    public function test_http_client_turns_wp_error_into_error_response(): void
    {
        add_filter('pre_http_request', static fn() => new WP_Error('http_request_failed', 'cURL error 28'));
        $response = (new WpHttpClient())->request('GET', 'https://example.com/', 5, 1000);
        $this->assertSame('cURL error 28', $response->error);
    }

    public function test_plugin_builds_an_analyzer_that_blocks_private_hosts(): void
    {
        $out = Plugin::analyzer()->analyze('http://127.0.0.1/', 'x');
        $this->assertFalse($out['ok']);
        $this->assertSame('blocked', $out['code']);
    }

    public function test_old_public_ajax_actions_are_gone(): void
    {
        foreach (['perform_seo_analysis', 'fetch_page_title', 'send_otp', 'verify_otp_and_save_data', 'download_pdf_report', 'test_wp_mail'] as $action) {
            $this->assertFalse(has_action('wp_ajax_nopriv_' . $action), $action);
        }
        $this->assertSame('2.0.0', SEO_ANALYZER_VERSION);
    }
}
