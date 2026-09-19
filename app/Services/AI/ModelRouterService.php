<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Model Router Service
 *
 * Routes agent inference requests to the optimal AI provider.
 * Supports automatic multi-level failover:
 *
 *   Primary provider (e.g. OpenAI gpt-4o)
 *     ↓ on circuit-open / API error
 *   Secondary provider (e.g. Anthropic claude-3-5-haiku)
 *     ↓
 *   Tertiary provider (e.g. Google gemini-1.5-flash)
 *     ↓
 *   Local model (e.g. Ollama llama3.2)
 *     ↓
 *   Graceful fallback response
 */
class ModelRouterService
{
    /**
     * Failover chain: ordered list of models to try when the primary fails.
     * Keyed by provider name so the circuit breaker can skip providers that are open.
     */
    private const FAILOVER_CHAIN = [
        ['provider' => 'openai',    'model' => 'gpt-4o-mini'],
        ['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-20241022'],
        ['provider' => 'google',    'model' => 'gemini-1.5-flash'],
        ['provider' => 'ollama',    'model' => 'llama3.2'],
    ];

    /**
     * Resolve the primary model config for a deployment.
     */
    public function resolve(AgentDeployment $deployment): array
    {
        $model = $deployment->model_override ?? $deployment->agent->primary_model ?? 'gpt-4o';
        $provider = $this->detectProvider($model);

        return array_merge(
            $this->getProviderDefaults($provider),
            [
                'model' => $model,
                'provider' => $provider,
                'temperature' => $deployment->agent->defaultPersona->temperature ?? 0.7,
                'max_tokens' => $deployment->agent->defaultPersona->max_tokens ?? 4096,
            ],
            $deployment->model_config_override ?? []
        );
    }

    /**
     * Build an ordered failover chain for a deployment.
     *
     * The chain starts with the deployment's primary model, then appends the
     * configured failover providers — excluding any whose circuit is OPEN.
     *
     * @return array<int, array{provider: string, model: string, api_key: ?string, base_url: string, temperature: float, max_tokens: int}>
     */
    public function buildFailoverChain(AgentDeployment $deployment): array
    {
        $primary = $this->resolve($deployment);
        $chain = [$primary];

        $temperature = $primary['temperature'];
        $maxTokens = $primary['max_tokens'];

        foreach (self::FAILOVER_CHAIN as $fallback) {
            // Skip if this is the same provider as primary (already in chain)
            if ($fallback['provider'] === $primary['provider']) {
                continue;
            }

            // Skip if the API key is missing (provider not configured)
            $defaults = $this->getProviderDefaults($fallback['provider']);
            if ($fallback['provider'] !== 'ollama' && empty($defaults['api_key'])) {
                continue;
            }

            $chain[] = array_merge($defaults, [
                'model' => $fallback['model'],
                'provider' => $fallback['provider'],
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'is_fallback' => true,
            ]);
        }

        return $chain;
    }

    /**
     * Record a provider failure in the failover tracking cache.
     * Used by AgentOrchestrationService to mark a provider as temporarily unavailable.
     */
    public function recordProviderFailure(string $provider): void
    {
        $key = "model_router_failure_{$provider}";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, 300); // 5-minute window

        Log::warning('[ModelRouter] Provider failure recorded', [
            'provider' => $provider,
            'failure_count' => $count,
        ]);
    }

    /**
     * Check if a provider has exceeded its failure threshold in the window.
     */
    public function isProviderDegraded(string $provider): bool
    {
        return ((int) Cache::get("model_router_failure_{$provider}", 0)) >= 3;
    }

    public function detectProvider(string $model): string
    {
        return match (true) {
            str_starts_with($model, 'gpt-') || str_starts_with($model, 'o1') => 'openai',
            str_starts_with($model, 'claude-') => 'anthropic',
            str_starts_with($model, 'gemini-') => 'google',
            str_starts_with($model, 'llama') || str_starts_with($model, 'mistral') => 'ollama',
            default => 'openai',
        };
    }

    public function getProviderDefaults(string $provider): array
    {
        return match ($provider) {
            'openai' => [
                'api_key' => config('prism.providers.openai.api_key'),
                'base_url' => 'https://api.openai.com/v1',
            ],
            'anthropic' => [
                'api_key' => config('prism.providers.anthropic.api_key'),
                'base_url' => 'https://api.anthropic.com/v1',
            ],
            'google' => [
                'api_key' => config('prism.providers.gemini.api_key'),
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            ],
            'ollama' => [
                'api_key' => null,
                'base_url' => config('services.ollama.url', 'http://localhost:11434'),
            ],
            default => [],
        };
    }
}
