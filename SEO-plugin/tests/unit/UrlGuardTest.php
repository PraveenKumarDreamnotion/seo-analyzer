<?php
namespace HaloAudit\Tests;

use HaloAudit\UrlGuard;
use PHPUnit\Framework\TestCase;

final class UrlGuardTest extends TestCase
{
    private function guard(array $map = []): UrlGuard
    {
        return new UrlGuard(static fn(string $host): array => $map[$host] ?? []);
    }

    /** @dataProvider rejected */
    public function test_unsafe_urls_are_rejected(string $url, array $dns = []): void
    {
        $this->assertNotNull($this->guard($dns)->reject($url), $url);
    }

    public function rejected(): array
    {
        return [
            'loopback ip' => ['http://127.0.0.1/'],
            'private 10' => ['http://10.1.2.3/'],
            'private 192' => ['http://192.168.1.1/'],
            'private 172' => ['http://172.16.0.1/'],
            'metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'cgnat' => ['http://100.64.0.1/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'localhost' => ['http://localhost/'],
            'dot local' => ['http://printer.local/'],
            'dot test' => ['http://site.test/'],
            'ftp scheme' => ['ftp://example.com/'],
            'file scheme' => ['file:///etc/passwd'],
            'odd port' => ['http://example.com:8080/', ['example.com' => ['93.184.216.34']]],
            'credentials' => ['http://user:pass@example.com/', ['example.com' => ['93.184.216.34']]],
            'unresolvable' => ['http://nope.invalid/'],
            'public name, private ip' => ['http://rebind.example/', ['rebind.example' => ['10.0.0.5']]],
            'one of two ips private' => ['http://mixed.example/', ['mixed.example' => ['93.184.216.34', '127.0.0.1']]],
            'no host' => ['http:///path'],
        ];
    }

    public function test_public_urls_pass(): void
    {
        $guard = $this->guard(['example.com' => ['93.184.216.34']]);
        $this->assertNull($guard->reject('https://example.com/a?b=1'));
        $this->assertNull($guard->reject('http://example.com:80/'));
        $this->assertNull($guard->reject('https://93.184.216.34/'));
    }
}
