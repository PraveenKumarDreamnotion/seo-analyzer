<?php
namespace HaloAudit;

/** Plain data about the site. A null status means the fetch itself failed. */
final class SiteFacts
{
    public Response $page;

    public ?int $robotsStatus = null;
    public ?RobotsTxt $robots = null;

    public ?int $sitemapStatus = null;
    public bool $sitemapValid = false;
    public ?int $sitemapNewest = null;

    public ?int $llmsStatus = null;
    public string $llmsBody = '';

    public ?int $certDaysLeft = null;

    /** @var array{status:int,width:?int,height:?int}|null */
    public ?array $ogImage = null;

    public bool $feedFound = false;

    /** @var array{score:int,lcp_ms:float,cls:float}|null */
    public ?array $psi = null;
}
