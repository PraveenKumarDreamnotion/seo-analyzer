<?php
namespace HaloAudit\Checks\Ai;

use HaloAudit\BaseCheck;
use HaloAudit\Context;
use HaloAudit\Result;
use HaloAudit\Url;

final class AiCrawlers extends BaseCheck
{
    public const ID = 'ai_crawlers';
    public const GROUP = 'ai';
    public const SEVERITY = 3;
    public const TITLE = 'AI crawlers can read the page';
    public const WHY = 'ChatGPT, Claude, Perplexity and Gemini can only cite pages that their crawlers are allowed to read.';
    public const FIX_WARN = 'Your robots.txt blocks some AI crawlers from this page. Remove those rules if you want AI assistants to cite you.';
    public const FIX_FAIL = 'Your robots.txt blocks most AI crawlers from this page. Remove those rules if you want AI assistants to cite you.';

    public const AGENTS = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot'];

    public function run(Context $c): Result
    {
        $status = $c->site->robotsStatus;
        if ($status === null || $status >= 500) {
            return Result::notMeasured('The robots.txt file could not be fetched');
        }
        if ($c->site->robots === null) {
            return Result::pass('No robots.txt rules, so all crawlers are allowed');
        }

        $path = Url::path($c->url);
        $blocked = [];
        foreach (self::AGENTS as $agent) {
            if (!$c->site->robots->isAllowed($agent, $path)) {
                $blocked[] = $agent;
            }
        }
        if ($blocked === []) {
            return Result::pass('All 7 AI crawlers are allowed');
        }
        $evidence = 'Blocked: ' . implode(', ', $blocked);
        return count($blocked) >= 3 ? Result::fail($evidence) : Result::warn($evidence);
    }
}
