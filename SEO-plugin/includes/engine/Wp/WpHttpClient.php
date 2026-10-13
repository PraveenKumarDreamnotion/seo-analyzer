<?php
namespace HaloAudit\Wp;

use HaloAudit\HttpClient;
use HaloAudit\Response;

final class WpHttpClient implements HttpClient
{
    public const USER_AGENT = 'Mozilla/5.0 (compatible; HaloAudit/2.0; +https://thrivemattic.com/ai-tools/seo-analysis/)';

    public function request(string $method, string $url, int $timeout, int $maxBytes, array $headers = []): Response
    {
        $result = wp_safe_remote_request($url, [
            'method' => $method,
            'timeout' => $timeout,
            'redirection' => 0,
            'limit_response_size' => $maxBytes,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'user-agent' => self::USER_AGENT,
            'headers' => $headers,
        ]);

        if (is_wp_error($result)) {
            return Response::error($result->get_error_message());
        }

        $flat = [];
        foreach (wp_remote_retrieve_headers($result) as $name => $value) {
            $flat[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return Response::make((int) wp_remote_retrieve_response_code($result), (string) wp_remote_retrieve_body($result), $flat);
    }
}
