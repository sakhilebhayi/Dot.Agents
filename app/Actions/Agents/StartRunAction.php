<?php

namespace App\Actions\Agents;

use App\DTOs\Agents\StartRunData;
use App\Exceptions\RunRefused;
use App\Jobs\RunAgentStep;
use App\Models\AgentDeployment;
use App\Models\AgentRun;
use App\Services\AI\AgentQuotaGuard;
use App\Services\AI\AgentSandboxService;
use App\Services\Runs\RunKick;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Every way of starting work comes through here: a person, a schedule, a webhook, a workflow. A live run pins the
 * published version, so a later change to the agent cannot change work already under way.
 */
class StartRunAction
{
    public function __construct(
        private readonly RunKick $kick,
        private readonly AgentSandboxService $sandbox,
        private readonly AgentQuotaGuard $quota,
    ) {}

    /** @throws RunRefused */
    public function execute(AgentDeployment $deployment, StartRunData $data): AgentRun
    {
        if (! $data->isTest && $deployment->status !== 'active') {
            throw new RunRefused("{$deployment->name} is not active, so it cannot start work.");
        }

        $this->assertAllowed($deployment);

        $version = $data->versionId !== null
            ? $deployment->versions()->findOrFail($data->versionId)
            : ($data->isTest ? ($deployment->draftVersion()->first() ?? $deployment->publishedVersion) : $deployment->publishedVersion);

        if ($version === null) {
            throw new RunRefused("{$deployment->name} has no published setup yet.");
        }

        if ($data->idempotencyKey !== null && ($existing = $this->existing($deployment, $data->idempotencyKey))) {
            return $existing;
        }

        try {
            $run = AgentRun::create([
                'organization_id' => $deployment->organization_id,
                'agent_deployment_id' => $deployment->id,
                'config_version_id' => $version->id,
                'trigger_type' => $data->triggerType,
                'trigger_ref' => $data->triggerRef,
                'initiated_by' => $data->initiatedBy,
                'agent_session_id' => $data->sessionId,
                'agent_task_id' => $data->taskId,
                'workflow_execution_id' => $data->workflowExecutionId,
                'parent_run_id' => $data->parentRunId,
                'status' => AgentRun::QUEUED,
                'summary' => Str::limit(trim((string) preg_replace('/\s+/', ' ', $data->input)), 190),
                'input' => $data->input,
                'limits' => $version->settings()['limits'],
                'idempotency_key' => $data->idempotencyKey,
                'is_test' => $data->isTest,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->existing($deployment, (string) $data->idempotencyKey);
        }

        RunAgentStep::dispatch($run->id, $run->organization_id);
        $this->kick->after($run);

        return $run;
    }

    /** @throws RunRefused */
    private function assertAllowed(AgentDeployment $deployment): void
    {
        try {
            $this->sandbox->assertPermitted($deployment, 'process_message', ['organization_id' => $deployment->organization_id]);
            $this->quota->assertQuotaAvailable($deployment->organization_id, $deployment->organization?->plan);
        } catch (RuntimeException $e) {
            throw new RunRefused($e->getMessage(), previous: $e);
        }
    }

    private function existing(AgentDeployment $deployment, string $key): ?AgentRun
    {
        return AgentRun::where('organization_id', $deployment->organization_id)->where('idempotency_key', $key)->first();
    }
}
