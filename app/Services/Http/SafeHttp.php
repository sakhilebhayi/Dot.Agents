<?php

namespace App\Services\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The only way the platform calls a URL it was given: guarded by UrlGuard, with a timeout, redirects never followed
 * unseen (a public URL could otherwise bounce us to an internal one), and the connection pinned to the address UrlGuard
 * approved.
 */
class SafeHttp
{
    private const MAX_BYTES = 2_000_000;

    public function __construct(private readonly UrlGuard $guard) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $json
     *
     * @throws UnsafeUrl
     * @throws ConnectionException
     */
    public function send(string $method, string $url, array $headers, ?array $json, int $timeout): Response
    {
        $target = $this->guard->assertSafe($url);

        $options = ['on_headers' => function ($response) {
            if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                throw new UnsafeUrl('The response is too large.');
            }
        }];

        if (! filter_var($target['host'], FILTER_VALIDATE_IP) && defined('CURLOPT_RESOLVE')) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]];
        }

        $request = Http::timeout($timeout)->connectTimeout(min($timeout, 5))->withoutRedirecting()->withOptions($options)->withHeaders($headers);

        return $json === null ? $request->send($method, $url) : $request->send($method, $url, ['json' => $json]);
    }
}
