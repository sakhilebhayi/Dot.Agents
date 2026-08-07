<?php

namespace App\Actions\Security;

use App\Actions\Concerns\LogsActionErrors;
use App\Events\KillSwitchActivated;
use App\Models\AgentDeployment;
use App\Models\AgentWorkflow;
use App\Models\Organization;
use App\Models\WorkflowExecution;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Emergency Kill Switch Action
 *
 * Provides instant, authorised stop controls for:
 *   1. Single agent deployment  — pause + quarantine immediately
 *   2. All workflows for an org — stop running executions + disable workflows
 *   3. Entire organisation      — suspend all agents + disable all workflows
 *
 * All kill-switch activations are recorded as immutable audit log entries
 * and broadcast as SecurityThreatDetected events for SOC alert routing.
 *
 * Zero Trust: every method requires an explicit Gate authorization check.
 */
class EmergencyKillSwitchAction
{
    use LogsActionErrors;

    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Kill a single agent deployment immediately.
     * Sets status → suspended and halts any in-progress tasks.
     */
    public function killDeployment(AgentDeployment $deployment, string $reason = 'Emergency kill switch activated'): void
    {
        Gate::authorize('update', $deployment);

        DB::transaction(function () use ($deployment, $reason) {
            // 1. Suspend the deployment
            $deployment->update(['status' => 'suspended']);

            // 2. Mark any in-progress tasks as aborted
            $deployment->tasks()
                ->whereIn('status', ['pending', 'in_progress'])
                ->update(['status' => 'aborted', 'completed_at' => now()]);

            // 3. Invalidate agent-related caches
            Cache::forget("agent_system_prompt_{$deployment->id}");
            Cache::forget("agent_reputation_{$deployment->id}");

            Log::critical('[KillSwitch] Deployment killed', [
                'deployment_id' => $deployment->id,
                'organization_id' => $deployment->organization_id,
                'reason' => $reason,
            ]);

            // 4. Immutable audit record
            $this->auditService->logUserAction(
                event: 'security.kill_switch.deployment',
                description: "KILL SWITCH: Deployment '{$deployment->name}' suspended. Reason: {$reason}",
                subject: $deployment,
                metadata: ['reason' => $reason, 'triggered_by' => auth()->id()],
            );
        });

        event(new KillSwitchActivated(
            scope: 'deployment',
            organizationId: $deployment->organization_id,
            reason: $reason,
            actorId: auth()->id(),
            metadata: ['deployment_id' => $deployment->id, 'deployment_name' => $deployment->name],
        ));
    }

    /**
     * Kill all active workflows for an organisation.
     * Stops running executions and pauses all enabled workflows.
     */
    public function killAllWorkflows(Organization $organization, string $reason = 'Emergency workflow halt'): int
    {
        Gate::authorize('update', $organization);

        $affected = 0;

        DB::transaction(function () use ($organization, $reason, &$affected) {
            // 1. Mark running executions as aborted
            $affected = WorkflowExecution::withoutGlobalScope('organization')
                ->where('organization_id', $organization->id)
                ->where('status', 'running')
                ->update(['status' => 'aborted', 'error_message' => "Halted by kill switch: {$reason}", 'completed_at' => now()]);

            // 2. Disable all active workflows
            AgentWorkflow::withoutGlobalScope('organization')
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->update(['status' => 'paused']);

            Log::critical('[KillSwitch] All org workflows killed', [
                'organization_id' => $organization->id,
                'executions_aborted' => $affected,
                'reason' => $reason,
            ]);

            $this->auditService->logUserAction(
                event: 'security.kill_switch.workflows',
                description: "KILL SWITCH: All workflows for org '{$organization->name}' halted. {$affected} running executions aborted. Reason: {$reason}",
                subject: $organization,
                metadata: ['reason' => $reason, 'executions_aborted' => $affected, 'triggered_by' => auth()->id()],
            );
        });

        event(new KillSwitchActivated(
            scope: 'workflows',
            organizationId: $organization->id,
            reason: $reason,
            actorId: auth()->id(),
            metadata: ['executions_aborted' => $affected],
        ));

        return $affected;
    }

    /**
     * Full organisation suspension — immediately halts all agents and workflows.
     * Used for billing suspension, security incidents, or compliance violations.
     * Requires platform_admin role.
     */
    public function suspendOrganization(Organization $organization, string $reason = 'Platform suspension'): array
    {
        if (! auth()->user()?->hasRole('platform_admin')) {
            abort(403, 'Only platform administrators can suspend an entire organization.');
        }

        $stats = ['agents_suspended' => 0, 'workflows_halted' => 0, 'executions_aborted' => 0];

        DB::transaction(function () use ($organization, $reason, &$stats) {
            // 1. Suspend all active/paused deployments
            $stats['agents_suspended'] = AgentDeployment::withoutGlobalScope('organization')
                ->where('organization_id', $organization->id)
                ->whereIn('status', ['active', 'paused'])
                ->update(['status' => 'suspended']);

            // 2. Abort running workflow executions
            $stats['executions_aborted'] = WorkflowExecution::withoutGlobalScope('organization')
                ->where('organization_id', $organization->id)
                ->where('status', 'running')
                ->update(['status' => 'aborted', 'error_message' => "Org suspended: {$reason}", 'completed_at' => now()]);

            // 3. Disable all active workflows
            $stats['workflows_halted'] = AgentWorkflow::withoutGlobalScope('organization')
                ->where('organization_id', $organization->id)
                ->where('status', 'active')
                ->update(['status' => 'paused']);

            // 4. Mark the org as suspended
            $organization->update(['status' => 'suspended']);

            Log::critical('[KillSwitch] Organization suspended', [
                'organization_id' => $organization->id,
                'stats' => $stats,
                'reason' => $reason,
            ]);

            $this->auditService->logUserAction(
                event: 'security.kill_switch.organization',
                description: "KILL SWITCH: Organization '{$organization->name}' fully suspended. Reason: {$reason}",
                subject: $organization,
                metadata: ['reason' => $reason, 'stats' => $stats, 'triggered_by' => auth()->id()],
            );
        });

        event(new KillSwitchActivated(
            scope: 'organization',
            organizationId: $organization->id,
            reason: $reason,
            actorId: auth()->id(),
            metadata: $stats,
        ));

        return $stats;
    }
}
