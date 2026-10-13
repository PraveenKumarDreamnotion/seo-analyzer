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
        Installer::maybeUpgrade();
        add_action(Installer::CRON_HOOK, static function (): void {
            (new ReportStore())->purgeUnverified();
        });
        register_deactivation_hook(SEO_ANALYZER_PLUGIN_FILE, static function (): void {
            wp_clear_scheduled_hook(Installer::CRON_HOOK);
        });
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
