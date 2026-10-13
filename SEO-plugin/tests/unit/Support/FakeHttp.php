<?php
namespace HaloAudit\Tests\Support;

use HaloAudit\HttpClient;
use HaloAudit\Response;

final class FakeHttp implements HttpClient
{
    /** @var array<string,Response> */
    public array $routes = [];
    /** @var string[] */
    public array $calls = [];

    public function request(string $method, string $url, int $timeout, int $maxBytes, array $headers = []): Response
    {
        $this->calls[] = $url;
        return isset($this->routes[$url]) ? clone $this->routes[$url] : Response::error('no route for ' . $url);
    }
}
