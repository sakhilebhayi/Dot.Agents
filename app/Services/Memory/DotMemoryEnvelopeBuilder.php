<?php

namespace App\Services\Memory;

use Illuminate\Support\Str;

/**
 * Builds the common Intelligence Loop request envelope shared by all four
 * record*() calls on DotMemoryClient (recordEvent, recordDecision,
 * recordAction, recordOutcome).
 */
class DotMemoryEnvelopeBuilder
{
    private const PLATFORM = 'dot-agents';

    /**
     * @param  array<string, mixed>  $envelope  overrides/extends any envelope field
     *                                          (source, subject_label, team_id, user_id,
     *                                          signature, requires_approval, mechanic_ref,
     *                                          approval_status, measure, ...) -- including
     *                                          event_id/occurred_at, for a caller that needs
     *                                          a deterministic retry.
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public function build(string $loopId, string $subjectType, string $subjectId, array $envelope, array $detail): array
    {
        return array_merge([
            'loop_id' => $loopId,
            'event_id' => (string) Str::uuid(),
            'platform' => self::PLATFORM,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'occurred_at' => now()->toISOString(),
            'detail' => $detail === [] ? null : $detail,
        ], $envelope);
    }
}
