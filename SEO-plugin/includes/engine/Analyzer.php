<?php
namespace HaloAudit;

final class Analyzer
{
    public const VERSION = '2.0.0';
    private const SIDE_FILE_BUDGET_SECONDS = 10;

    private Fetcher $fetcher;
    private CertProbe $cert;
    /** @var callable():int */
    private $clock;

    public function __construct(Fetcher $fetcher, CertProbe $cert, ?callable $clock = null)
    {
        $this->fetcher = $fetcher;
        $this->cert = $cert;
        $this->clock = $clock ?? 'time';
    }

    public function analyze(string $url, string $keyword): array
    {
        $url = Url::normalise($url);
        if ($url === '' || Url::host($url) === '') {
            return self::failure('invalid', 'Enter the full address of a web page.');
        }

        $response = $this->fetcher->get($url, 15, 3000000);
        if ($response->error !== null) {
            if (strpos($response->error, 'blocked') === 0) {
                return self::failure('blocked', 'This address cannot be checked.');
            }
            return self::failure('unreachable', 'We could not read this page. The site did not answer in time.');
        }
        if ($response->status >= 400) {
            return self::failure('http_error', 'We could not read this page. The site answered with HTTP ' . $response->status . '.');
        }
        $type = strtolower((string) $response->header('content-type'));
        if ($type !== '' && strpos($type, 'html') === false) {
            return self::failure('not_html', 'This address is not a web page.');
        }

        $this->fetcher->setDeadline(microtime(true) + self::SIDE_FILE_BUDGET_SECONDS);

        $finalUrl = $response->finalUrl !== '' ? $response->finalUrl : $url;
        $page = PageFacts::fromHtml($response->body, $finalUrl);
        $site = SiteProbe::collect($this->fetcher, $this->cert, $page, $response);
        $context = new Context($finalUrl, trim($keyword), $page, $site, (int) ($this->clock)());

        $isEducation = Education::detect(Url::host($finalUrl), $page->jsonLd()['types']);

        $rows = [];
        foreach (CheckRegistry::all() as $check) {
            if ($check->group() === 'education' && !$isEducation) {
                continue;
            }
            $rows[] = self::row($check, $context);
        }

        return ['ok' => true, 'report' => [
            'engine_version' => self::VERSION,
            'url' => $finalUrl,
            'keyword' => trim($keyword),
            'created_at' => $context->now,
            'is_education' => $isEducation,
            'speed_done' => false,
            'checks' => $rows,
            'summary' => Scorer::score($rows),
        ]];
    }

    /** @param array{score:int,lcp_ms:float,cls:float}|null $psi */
    public static function applySpeed(array $report, ?array $psi): array
    {
        $site = new SiteFacts();
        $site->page = Response::make(200);
        $site->psi = $psi;
        $context = new Context($report['url'], $report['keyword'], PageFacts::fromHtml('', $report['url']), $site, (int) $report['created_at']);

        $fresh = [];
        foreach (CheckRegistry::speed() as $check) {
            $fresh[$check->id()] = self::row($check, $context);
        }
        foreach ($report['checks'] as $i => $row) {
            if (isset($fresh[$row['id']])) {
                $report['checks'][$i] = $fresh[$row['id']];
            }
        }

        $report['speed_done'] = true;
        $report['summary'] = Scorer::score($report['checks']);
        return $report;
    }

    private static function row(Check $check, Context $context): array
    {
        try {
            $result = $check->run($context);
        } catch (\Throwable $e) {
            $result = Result::notMeasured('This check could not run');
        }
        return [
            'id' => $check->id(),
            'group' => $check->group(),
            'severity' => $check->severity(),
            'title' => $check->title(),
            'why' => $check->why(),
            'status' => $result->status,
            'evidence' => $result->evidence,
            'message' => $check->fix($result->status),
        ];
    }

    private static function failure(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }
}
