<?php

namespace App\Services\AI;

use App\Services\Governance\AuditService;
use App\Services\Resilience\CircuitBreakerService;
use Illuminate\Support\Facades\Log;

/**
 * AgentModelCaller
 *
 * Handles multi-provider AI model invocation with automatic failover.
 *
 * Responsibilities:
 *  - Build a failover chain via ModelRouterService
 *  - Call each provider in order using CircuitBreakerService
 *  - Record provider failures for health tracking
 *  - Return a graceful degradation response if all providers fail
 *
 * Extracted from AgentOrchestrationService to keep orchestration logic focused;
 * the actual per-provider Prism mechanics live in PrismModelInvoker.
 */
class AgentModelCaller
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
        private readonly CircuitBreakerService $circuitBreaker,
        private readonly AuditService $auditService,
        private readonly PrismModelInvoker $invoker,
    ) {}

    /**
     * Call the AI model with automatic multi-provider failover.
     * Returns the response array (content, usage, cost, model_used, provider).
     */
    public function callWithFailover(
        int $deploymentId,
        array $chain,
        string $systemPrompt,
        array $history,
        string $userMessage
    ): array {
        // SECURITY: Screen user message for prompt injection before any model call.
        // Stored content can re-enter as AI input; this is the last line of defence.
        if ($this->auditService->detectPromptInjection($userMessage)) {
            Log::warning('[AgentModelCaller] Prompt injection detected — request blocked', [
                'deployment_id' => $deploymentId,
            ]);

            return [
                'content' => 'Your request could not be processed due to a security policy violation.',
                'usage' => ['total_tokens' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0],
                'cost' => 0,
                'finish_reason' => 'injection_blocked',
                'is_fallback' => true,
            ];
        }

        $lastException = null;

        foreach ($chain as $index => $modelConfig) {
            $provider = $modelConfig['provider'];

            if ($this->modelRouter->isProviderDegraded($provider)) {
                Log::info('[AgentModelCaller] Skipping degraded provider', [
                    'provider' => $provider,
                    'deployment_id' => $deploymentId,
                ]);

                continue;
            }

            try {
                $response = $this->circuitBreaker->call(
                    "ai_inference_{$provider}",
                    fn () => $this->invoker->call($modelConfig, $systemPrompt, $history, $userMessage)
                );

                if ($index > 0) {
                    Log::info('[AgentModelCaller] Failover succeeded', [
                        'deployment_id' => $deploymentId,
                        'provider' => $provider,
                        'model' => $modelConfig['model'],
                        'attempt' => $index + 1,
                    ]);
                }

                return array_merge($response, ['model_used' => $modelConfig['model'], 'provider' => $provider]);

            } catch (\Throwable $e) {
                $lastException = $e;
                $this->modelRouter->recordProviderFailure($provider);

                Log::warning('[AgentModelCaller] Provider failed, trying next', [
                    'deployment_id' => $deploymentId,
                    'provider' => $provider,
                    'model' => $modelConfig['model'],
                    'error' => $e->getMessage(),
                    'attempt' => $index + 1,
                ]);
            }
        }

        Log::error('[AgentModelCaller] All providers failed, using fallback', [
            'deployment_id' => $deploymentId,
            'last_error' => $lastException?->getMessage(),
        ]);

        return $this->fallbackResponse();
    }

    /**
     * Graceful degradation response when all AI providers are unavailable.
     */
    private function fallbackResponse(): array
    {
        return [
            'content' => "I'm temporarily unable to process your request — the AI service is "
                .'undergoing maintenance. Please try again in a few minutes.',
            'usage' => ['total_tokens' => 0, 'prompt_tokens' => 0, 'completion_tokens' => 0],
            'cost' => 0,
            'finish_reason' => 'service_unavailable',
            'is_fallback' => true,
        ];
    }
}
