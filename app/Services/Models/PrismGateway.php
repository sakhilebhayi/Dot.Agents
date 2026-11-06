<?php

namespace App\Services\Models;

use Prism\Prism\Enums\Provider;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Exceptions\PrismProviderOverloadedException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\RawSchema;
use Prism\Prism\Tool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Throwable;

/**
 * Prism, used for a single step. Tools are declared but never run here: the handler does nothing, the call comes back
 * in the reply, and the engine decides what happens to it. Prism's own loop would run tools inside one long process,
 * past every lease, policy and approval.
 */
final class PrismGateway implements ModelGateway
{
    private const PROVIDERS = ['anthropic' => Provider::Anthropic, 'openai' => Provider::OpenAI, 'gemini' => Provider::Gemini];

    public function __construct(private readonly ModelCatalog $catalog) {}

    public function turn(ModelRequest $request): ModelReply
    {
        $provider = $this->catalog->providerFor($request->model);

        if (! isset(self::PROVIDERS[$provider]) || ! $this->catalog->providerReady($provider)) {
            throw new ModelFailure('no_provider', "No key is set for {$provider}.");
        }

        try {
            $response = Prism::text()
                ->using(self::PROVIDERS[$provider], $request->model)
                ->withSystemPrompt($request->system)
                ->withMessages($this->messages($request->messages))
                ->withTools(array_map(fn (array $tool) => $this->tool($tool), $request->tools))
                ->withMaxSteps(1)
                ->withMaxTokens($request->maxTokens)
                ->withClientOptions(['timeout' => config('agents.model.timeout')])
                ->asText();
        } catch (PrismRateLimitedException $e) {
            throw new ModelFailure('rate_limited', 'The provider asked us to slow down.');
        } catch (PrismProviderOverloadedException $e) {
            throw new ModelFailure('overloaded', 'The provider is overloaded.');
        } catch (PrismException $e) {
            throw $this->classify($e);
        } catch (Throwable $e) {
            throw new ModelFailure('timeout', 'The provider could not be reached.');
        }

        return new ModelReply(
            text: $response->text,
            calls: array_map(fn (ToolCall $call) => ['id' => $call->id, 'name' => $call->name, 'arguments' => $call->arguments()], $response->toolCalls),
            inputTokens: $response->usage->promptTokens,
            outputTokens: $response->usage->completionTokens,
            cachedTokens: (int) $response->usage->cacheReadInputTokens,
            provider: $provider,
            model: $response->meta->model ?: $request->model,
            finish: $response->finishReason->value,
        );
    }

    private function classify(PrismException $e): ModelFailure
    {
        $message = strtolower($e->getMessage());

        return match (true) {
            str_contains($message, '401'), str_contains($message, 'authentic'), str_contains($message, 'api key') => new ModelFailure('auth', 'The provider refused our key.'),
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => new ModelFailure('timeout', 'The provider took too long.'),
            default => new ModelFailure('bad_request', 'The provider refused the request.'),
        };
    }

    /** @param  array{name: string, description: string, schema: array<string, mixed>}  $tool */
    private function tool(array $tool): Tool
    {
        $properties = $tool['schema']['properties'] ?? [];
        $required = $tool['schema']['required'] ?? [];

        $built = (new Tool)->as($tool['name'])->for($tool['description'])->using(fn () => '');

        foreach ($properties as $name => $schema) {
            $built->withParameter(new RawSchema($name, $schema), in_array($name, $required, true));
        }

        return $built;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return list<UserMessage|AssistantMessage|ToolResultMessage>
     */
    private function messages(array $messages): array
    {
        return array_map(fn (array $message) => match ($message['role']) {
            'assistant' => new AssistantMessage((string) ($message['text'] ?? ''), array_map(
                fn (array $call) => new ToolCall($call['id'], $call['name'], $call['arguments']),
                $message['calls'] ?? [],
            )),
            'tool' => new ToolResultMessage(array_map(
                fn (array $result) => new ToolResult($result['id'], $result['name'], $result['arguments'] ?? [], $result['content']),
                $message['results'],
            )),
            default => new UserMessage((string) $message['text']),
        }, $messages);
    }
}
