<?php

namespace App\Skills;

use App\Models\AgentSkill;
use App\Skills\DTOs\SkillResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WebhookSkill
 *
 * Executes an organization's own custom skill by calling out to the
 * endpoint they configured on the AgentSkill row (webhook_url), instead of
 * invoking a platform-authored PHP class. This is what lets a tenant wire
 * their own API/Flow/automation into an agent's skill pipeline without
 * shipping code to this platform -- see SkillRegistryService::resolve().
 *
 * The endpoint is expected to respond with:
 *   { "status": "completed"|"failed"|"skipped",
 *     "output": {...}, "confidence": 0-100,
 *     "findings": [...], "recommendations": [...] }
 * A non-2xx response, a timeout, or a malformed body all become a
 * SkillResult::failed() rather than an exception -- the same failure shape
 * SkillExecutionPipeline already expects from a built-in skill.
 */
class WebhookSkill extends BaseSkill
{
    public function __construct(private readonly AgentSkill $skillRecord) {}

    public function execute(array $input, array $context = []): SkillResult
    {
        if (! $this->skillRecord->webhook_url) {
            return SkillResult::failed("Skill [{$this->skillRecord->key}] has no webhook_url configured.");
        }

        try {
            $response = Http::withHeaders($this->skillRecord->webhook_headers ?? [])
                ->timeout($this->skillRecord->webhook_timeout_seconds ?: 15)
                ->post($this->skillRecord->webhook_url, [
                    'skill_key' => $this->skillRecord->key,
                    'input' => $input,
                    'context' => $this->safeContext($context),
                ]);

            if ($response->failed()) {
                return SkillResult::failed(
                    "Webhook returned HTTP {$response->status()} for skill [{$this->skillRecord->key}]."
                );
            }

            return $this->parseResponse($response->json() ?? []);
        } catch (Throwable $e) {
            Log::warning('[WebhookSkill] Call failed', [
                'skill_key' => $this->skillRecord->key,
                'organization_id' => $this->skillRecord->organization_id,
                'error' => $e->getMessage(),
            ]);

            return SkillResult::failed("Webhook call failed for skill [{$this->skillRecord->key}]: {$e->getMessage()}");
        }
    }

    public function key(): string
    {
        return $this->skillRecord->key;
    }

    public function layer(): string
    {
        return $this->skillRecord->layer ?: 'workforce';
    }

    /**
     * Only send JSON-safe scalars/arrays from the runtime context -- it may
     * contain Eloquent models (deployment, task) that shouldn't be dumped
     * wholesale to a third-party endpoint.
     */
    private function safeContext(array $context): array
    {
        return [
            'phase' => $context['phase'] ?? null,
            'deployment_id' => $context['deployment']->id ?? null,
            'task_id' => $context['task']->id ?? null,
        ];
    }

    private function parseResponse(array $body): SkillResult
    {
        $status = $body['status'] ?? 'completed';

        return match ($status) {
            'failed' => SkillResult::failed($body['output']['error'] ?? 'Webhook skill reported failure.'),
            'skipped' => SkillResult::skipped($body['output']['reason'] ?? 'Webhook skill skipped.'),
            default => SkillResult::completed(
                output: $body['output'] ?? [],
                confidence: (float) ($body['confidence'] ?? 100.0),
                findings: $body['findings'] ?? [],
                recommendations: $body['recommendations'] ?? [],
            ),
        };
    }
}
