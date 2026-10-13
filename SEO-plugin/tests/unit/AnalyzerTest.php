<?php
namespace HaloAudit\Tests;

use HaloAudit\Analyzer;
use HaloAudit\Fetcher;
use HaloAudit\ReportView;
use HaloAudit\Response;
use HaloAudit\Tests\Support\FakeHttp;
use HaloAudit\Tests\Support\FixedCert;
use HaloAudit\Tests\Support\Make;
use HaloAudit\UrlGuard;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    private FakeHttp $http;
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->http = new FakeHttp();
        $guard = new UrlGuard(static fn(string $host): array => $host === 'intranet.example' ? ['10.0.0.9'] : ['93.184.216.34']);
        $this->analyzer = new Analyzer(new Fetcher($this->http, $guard), new FixedCert(90), static fn(): int => Make::NOW);
    }

    private function html(string $extraHead = ''): string
    {
        return '<!doctype html><html lang="en"><head><title>MBA Programme at Example Business School India</title>' . $extraHead
            . '</head><body><main><h1>MBA Programme</h1>' . Make::words(320) . '<a href="/a">a</a><a href="/b">b</a></main></body></html>';
    }

    private function servePage(string $url = 'https://example.com/courses/mba/', string $extraHead = ''): void
    {
        $this->http->routes[$url] = Response::make(200, $this->html($extraHead), ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function test_successful_report_shape(): void
    {
        $this->servePage();
        $out = $this->analyzer->analyze('example.com/courses/mba/', 'MBA');
        $this->assertTrue($out['ok']);
        $report = $out['report'];
        $this->assertSame('2.0.0', $report['engine_version']);
        $this->assertSame('https://example.com/courses/mba/', $report['url']);
        $this->assertSame('MBA', $report['keyword']);
        $this->assertSame(Make::NOW, $report['created_at']);
        $this->assertFalse($report['speed_done']);
        $this->assertFalse($report['is_education']);
        $this->assertCount(33, $report['checks']); // 37 minus 4 education extras
        $this->assertSame(['id', 'group', 'severity', 'title', 'why', 'status', 'evidence', 'message'], array_keys($report['checks'][0]));
        $this->assertIsInt($report['summary']['overall']);
        $this->assertNull($report['summary']['groups']['speed']['score']);
    }

    public function test_side_file_failures_are_not_measured_not_failed(): void
    {
        $this->servePage(); // robots, sitemap, llms all have no route: transport error
        $rows = array_column($this->analyzer->analyze('https://example.com/courses/mba/', 'mba')['report']['checks'], 'status', 'id');
        $this->assertSame('not_measured', $rows['ai_crawlers']);
        $this->assertSame('not_measured', $rows['robots_reachable']);
        $this->assertSame('not_measured', $rows['sitemap']);
        $this->assertSame('not_measured', $rows['llms_txt']);
    }

    public function test_education_site_gets_extras_and_same_score(): void
    {
        $this->servePage();
        $plain = $this->analyzer->analyze('https://example.com/courses/mba/', 'mba')['report'];

        $this->servePage('https://example.edu.in/courses/mba/');
        $edu = $this->analyzer->analyze('https://example.edu.in/courses/mba/', 'mba')['report'];

        $this->assertTrue($edu['is_education']);
        $this->assertCount(37, $edu['checks']);
        $this->assertSame($plain['summary']['overall'], $edu['summary']['overall']);
    }

    /** @dataProvider failures */
    public function test_unreadable_pages_give_no_score(string $url, ?Response $response, string $code): void
    {
        if ($response !== null) {
            $this->http->routes[$url] = $response;
        }
        $out = $this->analyzer->analyze($url, 'mba');
        $this->assertFalse($out['ok']);
        $this->assertSame($code, $out['code']);
        $this->assertNotSame('', $out['message']);
        $this->assertArrayNotHasKey('report', $out);
    }

    public function failures(): array
    {
        return [
            'empty' => ['', null, 'invalid'],
            'private host' => ['https://intranet.example/', null, 'blocked'],
            'timeout' => ['https://example.com/down', null, 'unreachable'],
            '404' => ['https://example.com/missing', Response::make(404, 'nope', ['Content-Type' => 'text/html']), 'http_error'],
            'bot wall' => ['https://example.com/wall', Response::make(403, 'denied', ['Content-Type' => 'text/html']), 'http_error'],
            'pdf' => ['https://example.com/file.pdf', Response::make(200, '%PDF-1.7', ['Content-Type' => 'application/pdf']), 'not_html'],
        ];
    }

    public function test_http_error_message_names_the_status(): void
    {
        $this->http->routes['https://example.com/wall'] = Response::make(403, 'denied', ['Content-Type' => 'text/html']);
        $this->assertStringContainsString('403', $this->analyzer->analyze('https://example.com/wall', 'mba')['message']);
    }

    public function test_apply_speed_fills_the_group_and_rescales(): void
    {
        $this->servePage();
        $report = $this->analyzer->analyze('https://example.com/courses/mba/', 'mba')['report'];

        $done = Analyzer::applySpeed($report, ['score' => 95, 'lcp_ms' => 1800.0, 'cls' => 0.01]);
        $this->assertTrue($done['speed_done']);
        $this->assertSame(100, $done['summary']['groups']['speed']['score']);
        $this->assertCount(33, $done['checks']);

        $failed = Analyzer::applySpeed($report, null);
        $this->assertTrue($failed['speed_done']);
        $this->assertNull($failed['summary']['groups']['speed']['score']);
        $this->assertSame($report['summary']['overall'], $failed['summary']['overall']);
    }

    public function test_free_view_hides_findings_and_full_view_shows_them(): void
    {
        $this->servePage();
        $report = $this->analyzer->analyze('https://example.com/courses/mba/', 'mba')['report'];
        $free = ReportView::free($report);
        $this->assertArrayNotHasKey('checks', $free);
        $this->assertSame($report['summary'], $free['summary']);
        $this->assertSame('AI readiness', $free['labels']['ai']);
        $this->assertStringNotContainsString('evidence', json_encode($free));
        $this->assertCount(33, ReportView::full($report)['checks']);
    }
}
