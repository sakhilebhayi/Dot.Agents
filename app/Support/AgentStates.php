<?php

namespace App\Support;

/** Every state an agent or a run can be in, and how each one is drawn. Words and shapes live here and nowhere else. */
final class AgentStates
{
    private const LAMPS = [
        'draft' => ['Draft', 'off'],
        'testing' => ['Testing', 'running'],
        'staging' => ['Staging', 'running'],
        'active' => ['Active', 'clear'],
        'running' => ['Running', 'running'],
        'queued' => ['Queued', 'off'],
        'waiting_approval' => ['Waiting for approval', 'hold'],
        'awaiting_approval' => ['Waiting for approval', 'hold'],
        'waiting_agent' => ['Waiting for a teammate', 'running'],
        'paused' => ['Paused', 'hold'],
        'partial' => ['Finished with problems', 'hold'],
        'skipped' => ['Skipped', 'off'],
        'pending' => ['Not started', 'off'],
        'failed' => ['Failed', 'stop'],
        'stopped' => ['Stopped', 'stop'],
        'cancelled' => ['Cancelled', 'off'],
        'completed' => ['Completed', 'clear'],
        'disabled' => ['Disabled', 'off'],
        'suspended' => ['Suspended', 'stop'],
        'archived' => ['Archived', 'off'],
        'decommissioned' => ['Archived', 'off'],
    ];

    /** @return array{0: string, 1: string} The word and the lamp. */
    public static function lamp(?string $state): array
    {
        return self::LAMPS[$state] ?? [ucfirst(str_replace('_', ' ', (string) $state)), 'off'];
    }
}
