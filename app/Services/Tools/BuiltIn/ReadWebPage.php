<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Services\Http\SafeHttp;
use App\Services\Http\UnsafeUrl;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolFailure;
use App\Services\Tools\ToolOutput;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;

/**
 * Reads a public page. Anyone could have written what comes back, and the address itself can carry data out, so the
 * definition says both and the gate treats it accordingly.
 */
class ReadWebPage implements AgentTool
{
    private const MAX_REDIRECTS = 3;

    public function __construct(private readonly SafeHttp $http) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'web_read',
            label: 'Read a web page',
            category: 'External services',
            description: 'Fetches a public web page and returns its text.',
            accesses: 'Any public web page. Never private networks.',
            schema: ['type' => 'object', 'properties' => ['url' => ['type' => 'string', 'maxLength' => 2000]], 'required' => ['url']],
            externalReach: true,
            untrustedOutput: true,
            timeoutSeconds: 10,
            retries: 1,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $url = $arguments['url'];

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            try {
                $response = $this->http->send('GET', $url, ['Accept' => 'text/html,text/plain,application/json'], null, $this->definition()->timeoutSeconds);
            } catch (UnsafeUrl $e) {
                throw new ToolFailure($e->getMessage());
            } catch (ConnectionException) {
                throw new ToolFailure('The page did not answer in time.', transient: true);
            }

            if (! $response->redirect()) {
                break;
            }

            // Every hop goes back through the guard, so a public page cannot bounce us somewhere private.
            $url = (string) UriResolver::resolve(new Uri($url), new Uri($response->header('Location')));
        }

        if ($response->serverError()) {
            throw new ToolFailure("The page answered with an error ({$response->status()}).", transient: true);
        }

        if (! $response->successful()) {
            throw new ToolFailure("The page could not be read ({$response->status()}).");
        }

        $type = strtolower($response->header('Content-Type'));

        if ($type !== '' && ! preg_match('#text/|json|xml#', $type)) {
            throw new ToolFailure('That address is not a page of text.');
        }

        return new ToolOutput($this->text($response->body()));
    }

    private function text(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|noscript|template)\b[^>]*>.*?</\1>#is', ' ', $html);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }
}
