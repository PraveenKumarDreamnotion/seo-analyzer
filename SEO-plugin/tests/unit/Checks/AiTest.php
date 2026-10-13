<?php
namespace HaloAudit\Tests\Checks;

use HaloAudit\Checks\Ai;
use HaloAudit\RobotsTxt;
use HaloAudit\Tests\Support\Make;
use PHPUnit\Framework\TestCase;

final class AiTest extends TestCase
{
    private function status(string $class, string $head = '', string $body = '', array $site = []): string
    {
        return (new $class())->run(Make::ctx(Make::page($head, $body), Make::site($site)))->status;
    }

    private static function ld(string $json): string
    {
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /** @dataProvider crawlers */
    public function test_ai_crawlers(?int $status, ?string $robots, string $expected): void
    {
        $site = ['robotsStatus' => $status, 'robots' => $robots === null ? null : RobotsTxt::parse($robots)];
        $this->assertSame($expected, $this->status(Ai\AiCrawlers::class, '', '', $site));
    }

    public function crawlers(): array
    {
        return [
            'fetch failed' => [null, null, 'not_measured'],
            'server error' => [503, null, 'not_measured'],
            'no robots file' => [404, null, 'pass'],
            'all allowed' => [200, "User-agent: *\nDisallow: /wp-admin/", 'pass'],
            'no star group' => [200, "User-agent: Bingbot\nDisallow: /", 'pass'],
            'one blocked' => [200, "User-agent: GPTBot\nDisallow: /", 'warn'],
            'two blocked' => [200, "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /", 'warn'],
            'three blocked' => [200, "User-agent: GPTBot\nUser-agent: CCBot\nUser-agent: ClaudeBot\nDisallow: /", 'fail'],
            'star blocks all' => [200, "User-agent: *\nDisallow: /", 'fail'],
            'only this path blocked' => [200, "User-agent: GPTBot\nDisallow: /courses/", 'warn'],
            'other path blocked' => [200, "User-agent: GPTBot\nDisallow: /private/", 'pass'],
        ];
    }

    /** @dataProvider llms */
    public function test_llms_txt(?int $status, string $body, string $expected): void
    {
        $this->assertSame($expected, $this->status(Ai\LlmsTxt::class, '', '', ['llmsStatus' => $status, 'llmsBody' => $body]));
    }

    public function llms(): array
    {
        return [
            'good' => [200, "# Example\n> Summary", 'pass'],
            'bom then heading' => [200, "\xEF\xBB\xBF# Example", 'pass'],
            'no heading' => [200, 'Just some text', 'warn'],
            'html soft 404' => [200, '<!DOCTYPE html><html><body>Not found</body></html>', 'fail'],
            'missing' => [404, '', 'fail'],
            'fetch failed' => [null, '', 'not_measured'],
        ];
    }

    public function test_json_ld(): void
    {
        $this->assertSame('pass', $this->status(Ai\JsonLd::class, self::ld('{"@type":"Organization"}')));
        $this->assertSame('warn', $this->status(Ai\JsonLd::class));
        $this->assertSame('fail', $this->status(Ai\JsonLd::class, self::ld('{"@type":"Organization"}') . self::ld('{oops')));
    }

    public function test_schema_types(): void
    {
        $this->assertSame('pass', $this->status(Ai\SchemaTypes::class, self::ld('{"@graph":[{"@type":"Organization"},{"@type":"Course"}]}')));
        $this->assertSame('warn', $this->status(Ai\SchemaTypes::class, self::ld('{"@type":"WebSite"}')));
        $this->assertSame('warn', $this->status(Ai\SchemaTypes::class, self::ld('{"@type":"Article"}')));
        $this->assertSame('fail', $this->status(Ai\SchemaTypes::class));
    }

    public function test_faq_schema(): void
    {
        $details = str_repeat('<details><summary>Q?</summary>A</details>', 5);
        $this->assertSame('pass', $this->status(Ai\FaqSchema::class, '', '<p>No questions here</p>'));
        $this->assertSame('pass', $this->status(Ai\FaqSchema::class, self::ld('{"@type":"FAQPage"}'), $details));
        $this->assertSame('fail', $this->status(Ai\FaqSchema::class, '', $details));
        $this->assertSame('warn', $this->status(Ai\FaqSchema::class, '', '<h2>Frequently Asked Questions</h2>'));
        $this->assertSame('warn', $this->status(Ai\FaqSchema::class, '', '<h2>FAQs</h2><details></details>'));
    }

    public function test_semantic_html(): void
    {
        $this->assertSame('pass', $this->status(Ai\SemanticHtml::class, '', '<header></header><nav></nav><main><article></article></main><footer></footer>'));
        $this->assertSame('pass', $this->status(Ai\SemanticHtml::class, '', '<header></header><nav></nav><main></main><footer></footer>'));
        $this->assertSame('warn', $this->status(Ai\SemanticHtml::class, '', '<header></header><footer></footer>'));
        $this->assertSame('fail', $this->status(Ai\SemanticHtml::class, '', '<div><nav></nav></div>'));
    }

    public function test_open_graph_and_twitter(): void
    {
        $og = '<meta property="og:title" content="t"><meta property="og:description" content="d"><meta property="og:image" content="/i.png"><meta property="og:url" content="https://example.com/">';
        $this->assertSame('pass', $this->status(Ai\OpenGraph::class, $og));
        $this->assertSame('fail', $this->status(Ai\OpenGraph::class, '<meta property="og:title" content="t">'));
        $this->assertSame('pass', $this->status(Ai\TwitterCard::class, '<meta name="twitter:card" content="summary_large_image">'));
        $this->assertSame('warn', $this->status(Ai\TwitterCard::class));
    }

    /** @dataProvider ogImages */
    public function test_og_image(bool $declared, ?array $probe, string $expected): void
    {
        $head = $declared ? '<meta property="og:image" content="/i.png">' : '';
        $this->assertSame($expected, $this->status(Ai\OgImage::class, $head, '', ['ogImage' => $probe]));
    }

    public function ogImages(): array
    {
        return [
            'not declared' => [false, null, 'fail'],
            'fetch failed' => [true, null, 'not_measured'],
            'broken' => [true, ['status' => 404, 'width' => null, 'height' => null], 'fail'],
            'right size' => [true, ['status' => 200, 'width' => 1200, 'height' => 630], 'pass'],
            'other size' => [true, ['status' => 200, 'width' => 800, 'height' => 800], 'warn'],
            'size unknown' => [true, ['status' => 200, 'width' => null, 'height' => null], 'warn'],
        ];
    }

    public function test_feed(): void
    {
        $this->assertSame('pass', $this->status(Ai\Feed::class, '', '', ['feedFound' => true]));
        $this->assertSame('warn', $this->status(Ai\Feed::class, '', '', ['feedFound' => false]));
    }

    public function test_every_check_has_copy(): void
    {
        foreach (glob(dirname(__DIR__, 3) . '/includes/engine/Checks/Ai/*.php') as $file) {
            $class = 'HaloAudit\\Checks\\Ai\\' . basename($file, '.php');
            $check = new $class();
            $this->assertSame('ai', $check->group(), $class);
            $this->assertNotSame('', $check->id() . '', $class);
            $this->assertNotSame('', $check->title(), $class);
            $this->assertNotSame('', $check->why(), $class);
            $this->assertNotSame('', $check->fix('warn') . $check->fix('fail'), $class);
            $this->assertStringNotContainsString('—', $check->why() . $check->fix('fail') . $check->fix('warn'), $class);
        }
    }
}
