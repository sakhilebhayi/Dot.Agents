<?php

namespace App\Services\Tools;

use App\Contracts\AgentTool;
use App\Models\CustomTool;
use App\Services\Http\SafeHttp;
use App\Services\Http\UnsafeUrl;
use Illuminate\Http\Client\ConnectionException;

/**
 * Calls an API a business set up. The model supplies only the values the schema names. They go into the path or query
 * as encoded text or into the JSON body, so no argument can change the host, and the headers are never shown to it.
 */
class CustomApiTool implements AgentTool
{
    public function __construct(private readonly CustomTool $tool) {}

    public function definition(): ToolDefinition
    {
        $changes = $this->tool->method !== 'GET';

        return new ToolDefinition(
            key: 'custom_'.$this->tool->key,
            label: $this->tool->label,
            category: 'Custom tools',
            description: $this->tool->description,
            accesses: 'The API this business connected at '.parse_url($this->tool->url, PHP_URL_HOST).'.',
            schema: $this->tool->input_schema,
            version: $this->tool->version,
            readsSensitive: $this->tool->reads_sensitive,
            writesState: $changes,
            externalReach: true,
            untrustedOutput: true,
            safeToRepeat: ! $changes,
            retries: $changes ? 0 : 1,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $definition = $this->definition();
        ['url' => $url, 'rest' => $rest] = $this->address($arguments);
        $json = $this->tool->method === 'GET' || $rest === [] ? null : $rest;

        if ($this->tool->method === 'GET' && $rest !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($rest);
        }

        $headers = ($this->tool->headers ?? []) + ['Idempotency-Key' => $context->idempotencyKey];

        try {
            $response = app(SafeHttp::class)->send($this->tool->method, $url, $headers, $json, $definition->timeoutSeconds);
        } catch (UnsafeUrl $e) {
            throw new ToolFailure($e->getMessage());
        } catch (ConnectionException) {
            throw new ToolFailure('The API did not answer in time.', transient: true, outcomeUnknown: true);
        }

        if ($response->serverError()) {
            throw new ToolFailure("The API answered with an error ({$response->status()}).", transient: true, outcomeUnknown: true);
        }

        if (! $response->successful()) {
            throw new ToolFailure("The API said no ({$response->status()}).");
        }

        return new ToolOutput($response->body());
    }

    /**
     * Fills {placeholders} in the address the business saved and returns what is left over.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{url: string, rest: array<string, mixed>}
     */
    private function address(array $arguments): array
    {
        $url = $this->tool->url;

        foreach ($arguments as $name => $value) {
            if (str_contains($url, "{{$name}}")) {
                $url = str_replace("{{$name}}", rawurlencode((string) $value), $url);
                unset($arguments[$name]);
            }
        }

        if (parse_url($url, PHP_URL_HOST) !== parse_url($this->tool->url, PHP_URL_HOST)) {
            throw new ToolFailure('The address of this tool cannot change.');
        }

        return ['url' => $url, 'rest' => $arguments];
    }
}
