<?php
namespace HaloAudit;

interface HttpClient
{
    /** One hop only. Implementations must not follow redirects. */
    public function request(string $method, string $url, int $timeout, int $maxBytes, array $headers = []): Response;
}
