<?php

namespace App\Services\Memory;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * HTTP client for Dot.Memory's Intelligence Loop API (LoopController /
 * LoopRecord on the Dot.Memory side).
 *
 * Dot.Memory is telemetry, not a dependency: none of the four record*()
 * calls may ever throw or block the real task they are describing, so every
 * failure (unconfigured integration, non-2xx, timeout, network error) is
 * caught and turned into a `false` return plus a log line. context() is the
 * one read that a caller may reason with, so its nullable return is itself
 * the "degraded" signal -- null always means "could not fetch", while any
 * non-null array (even one describing an unknown subject) means the fetch
 * genuinely succeeded.
 */
class DotMemoryClient
{
    private const PLATFORM = 'dot-agents';

    private const TIMEOUT_SECONDS = 5;

    public function __construct(private readonly DotMemoryEnvelopeBuilder $envelopeBuilder) {}

    public function recordEvent(string $loopId, string $subjectType, string $subjectId, array $envelope = [], array $detail = []): bool
    {
        return $this->post('api/intelligence/events', $this->envelopeBuilder->build($loopId, $subjectType, $subjectId, $envelope, $detail));
    }

    public function recordDecision(
        string $loopId,
        string $subjectType,
        string $subjectId,
        float $confidence,
        float $risk,
        string $autonomyLevel,
        array $envelope = [],
        array $detail = [],
    ): bool {
        $payload = $this->envelopeBuilder->build($loopId, $subjectType, $subjectId, $envelope, $detail);
        $payload['confidence'] = $confidence;
        $payload['risk'] = $risk;
        $payload['autonomy_level'] = $autonomyLevel;

        return $this->post('api/intelligence/decisions', $payload);
    }

    public function recordAction(
        string $loopId,
        string $subjectType,
        string $subjectId,
        string $actionKind,
        string $executionStatus,
        array $envelope = [],
        array $detail = [],
    ): bool {
        $payload = $this->envelopeBuilder->build($loopId, $subjectType, $subjectId, $envelope, $detail);
        $payload['action_kind'] = $actionKind;
        $payload['executor_platform'] = self::PLATFORM;
        $payload['execution_status'] = $executionStatus;

        return $this->post('api/intelligence/actions', $payload);
    }

    public function recordOutcome(
        string $loopId,
        string $subjectType,
        string $subjectId,
        string $verdict,
        array $envelope = [],
        array $detail = [],
    ): bool {
        $payload = $this->envelopeBuilder->build($loopId, $subjectType, $subjectId, $envelope, $detail);
        $payload['verdict'] = $verdict;

        return $this->post('api/intelligence/outcomes', $payload);
    }

    /**
     * @return array<string, mixed>|null the context 'data' payload, or null on ANY
     *                                   failure -- including an unconfigured
     *                                   integration. null is the degraded signal a
     *                                   caller must branch on; it is never used to
     *                                   mean "subject unknown" (that is `known: false`
     *                                   inside a real, non-null payload).
     */
    public function context(string $subjectType, string $subjectId, ?string $signature = null): ?array
    {
        if (! $this->isConfigured()) {
            Log::warning('DotMemoryClient: context degraded, integration not configured', [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ]);

            return null;
        }

        try {
            $response = $this->client()->get('api/intelligence/context', array_filter([
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'signature' => $signature,
                'platform' => self::PLATFORM,
            ], static fn ($value) => $value !== null));

            if ($response->failed()) {
                Log::warning('DotMemoryClient: context degraded, non-2xx response', [
                    'subject_type' => $subjectType,
                    'subject_id' => $subjectId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('data');
        } catch (Throwable $e) {
            Log::warning('DotMemoryClient: context degraded, request threw', [
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = $this->client()->post($path, $payload);

            if ($response->failed()) {
                Log::warning('DotMemoryClient: request failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('DotMemoryClient: request threw', [
                'path' => $path,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function isConfigured(): bool
    {
        return filled(config('services.dot_memory.base_url')) && filled(config('services.dot_memory.token'));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.dot_memory.base_url'), '/'))
            ->withToken((string) config('services.dot_memory.token'))
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }
}
