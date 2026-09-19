<?php

namespace App\Services\AI;

use Prism\Prism\Enums\Provider as PrismProvider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use RuntimeException;

/**
 * Invokes a single AI model call via Prism PHP (or a deterministic mock
 * when AI_PROVIDER is unconfigured). Extracted from AgentModelCaller to
 * separate "how to call one model" from "which model to call, and what
 * to do when it fails" (failover orchestration).
 */
class PrismModelInvoker
{
    /**
     * Maps this platform's provider keys (ModelRouterService::FAILOVER_CHAIN)
     * onto Prism's own provider enum. Prism calls Google's models "gemini";
     * this platform calls the failover leg "google" -- both names are kept,
     * since "google" reads better in logs/UI and matches this platform's
     * own domain language, established before Prism was wired in.
     *
     * @var array<string, PrismProvider>
     */
    private const PRISM_PROVIDER_MAP = [
        'openai' => PrismProvider::OpenAI,
        'anthropic' => PrismProvider::Anthropic,
        'google' => PrismProvider::Gemini,
        'ollama' => PrismProvider::Ollama,
    ];

    /**
     * Approximate USD cost per 1,000 tokens (prompt, completion). Used only
     * for the cost figure attached to AgentTask/AgentMessage records --
     * not billing-grade, but good enough for the platform's own cost
     * dashboards. Unlisted models fall back to a conservative default.
     *
     * @var array<string, array{0: float, 1: float}>
     */
    private const TOKEN_PRICING_PER_1K = [
        'gpt-4o' => [0.0025, 0.01],
        'gpt-4o-mini' => [0.00015, 0.0006],
        'claude-3-5-haiku-20241022' => [0.0008, 0.004],
        'claude-3-5-sonnet-20241022' => [0.003, 0.015],
        'gemini-1.5-flash' => [0.000075, 0.0003],
        'gemini-1.5-pro' => [0.00125, 0.005],
    ];

    private const DEFAULT_PRICING_PER_1K = [0.001, 0.002];

    /**
     * Invoke the AI model for the given configuration via Prism PHP.
     *
     * config('prism.default_provider') === 'mock' (the safe default -- see
     * config/prism.php) short-circuits before any network call, so an
     * unconfigured environment (fresh clone, CI, this platform's own test
     * suite) never silently starts making real, billed model calls.
     */
    public function call(array $modelConfig, string $systemPrompt, array $history, string $userMessage): array
    {
        if (config('prism.default_provider') === 'mock') {
            return $this->mockModelResponse($modelConfig, $userMessage);
        }

        $prismProvider = self::PRISM_PROVIDER_MAP[$modelConfig['provider']] ?? null;

        if ($prismProvider === null) {
            throw new RuntimeException("No Prism provider mapping for [{$modelConfig['provider']}].");
        }

        $request = Prism::text()
            ->using($prismProvider, $modelConfig['model'])
            ->withSystemPrompt($systemPrompt)
            ->withMessages([...$this->buildMessages($history), new UserMessage($userMessage)])
            ->withMaxTokens($modelConfig['max_tokens'] ?? 4096);

        if (isset($modelConfig['temperature'])) {
            $request = $request->usingTemperature($modelConfig['temperature']);
        }

        $response = $request->asText();

        return [
            'content' => $response->text,
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'total_tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ],
            'cost' => $this->estimateCost($modelConfig['model'], $response->usage->promptTokens, $response->usage->completionTokens),
            'finish_reason' => $response->finishReason->value,
        ];
    }

    /**
     * Convert the platform's own {role, content} history shape into Prism
     * message objects. Only user/assistant turns are replayed -- system
     * context is passed separately via withSystemPrompt().
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, UserMessage|AssistantMessage>
     */
    private function buildMessages(array $history): array
    {
        return array_map(
            fn (array $turn) => $turn['role'] === 'assistant'
                ? new AssistantMessage($turn['content'])
                : new UserMessage($turn['content']),
            $history
        );
    }

    private function estimateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        [$promptRate, $completionRate] = self::TOKEN_PRICING_PER_1K[$model] ?? self::DEFAULT_PRICING_PER_1K;

        return round(($promptTokens / 1000 * $promptRate) + ($completionTokens / 1000 * $completionRate), 6);
    }

    /**
     * Deterministic, network-free response for AI_PROVIDER=mock. Distinct
     * from a failover/degradation response: this is the *expected* behavior
     * of an unconfigured environment, not a degraded/failure state, so it
     * reports a normal 'stop' finish reason and is never marked is_fallback.
     */
    private function mockModelResponse(array $modelConfig, string $userMessage): array
    {
        $promptTokens = max(1, (int) (mb_strlen($userMessage) / 4));
        $completionTokens = max(1, (int) ($promptTokens * 1.5));

        return [
            'content' => "[mock:{$modelConfig['provider']}/{$modelConfig['model']}] Acknowledged: ".mb_substr($userMessage, 0, 200),
            'usage' => [
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $promptTokens + $completionTokens,
            ],
            'cost' => $this->estimateCost($modelConfig['model'], $promptTokens, $completionTokens),
            'finish_reason' => 'stop',
        ];
    }
}
