<?php
namespace HaloAudit\Wp;

use HaloAudit\Analyzer;
use HaloAudit\Fetcher;
use HaloAudit\HttpClient;
use HaloAudit\UrlGuard;

final class Plugin
{
    public static function boot(): void
    {
    }

    public static function http(): HttpClient
    {
        return new WpHttpClient();
    }

    public static function analyzer(): Analyzer
    {
        $guard = new UrlGuard(apply_filters('halo_audit_dns_resolver', null));
        $cert = apply_filters('halo_audit_cert_probe', new StreamCertProbe());
        return new Analyzer(new Fetcher(self::http(), $guard), $cert);
    }
}
