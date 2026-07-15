<?php

namespace App\Listeners;

use App\Events\SkillScoreRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogSkillScoreRecorded implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'agents';

    public int $tries = 3;

    public function handle(SkillScoreRecorded $event): void
    {
        Log::info('skill.score_recorded', [
            'organization_id' => $event->organizationId,
            'deployment_id' => $event->deploymentId,
            'skill_id' => $event->skillId,
            'execution_status' => $event->executionStatus,
            'confidence' => $event->confidence,
        ]);
    }
}
