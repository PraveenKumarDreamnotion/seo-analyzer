<?php
namespace HaloAudit;

final class UrlGuard
{
    /** @var callable(string):string[] */
    private $resolver;

    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $ips = gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            return $ips;
        };
    }

    public function reject(string $url): ?string
    {
        $p = parse_url($url);
        if ($p === false || empty($p['host']) || empty($p['scheme'])) {
            return 'not a valid address';
        }
        $scheme = strtolower($p['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return 'only http and https are allowed';
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return 'credentials in the address are not allowed';
        }
        if (isset($p['port']) && !in_array((int) $p['port'], [80, 443], true)) {
            return 'only ports 80 and 443 are allowed';
        }

        $host = strtolower(trim($p['host'], '[]'));
        if ($host === 'localhost' || preg_match('/\.(local|localhost|test|internal|lan|home|corp)$/', $host)) {
            return 'local host names are not allowed';
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);
        if ($ips === []) {
            return 'the host name does not resolve';
        }
        foreach ($ips as $ip) {
            if (!self::isPublic($ip)) {
                return 'private or reserved addresses are not allowed';
            }
        }
        return null;
    }

    private static function isPublic(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            // 100.64.0.0/10 carrier-grade NAT is not covered by the PHP flags.
            if (($long & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
                return false;
            }
        }
        return true;
    }
}
