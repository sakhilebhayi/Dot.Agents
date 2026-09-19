<?php

namespace App\Listeners;

use App\Events\AgentCapabilityContractChanged;
use App\Jobs\SendPlatformNotification;
use App\Models\AgentDeployment;
use App\Services\Governance\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TriggerCapabilityContractGovernanceReview
 *
 * Handles the AgentCapabilityContractChanged event.  Creates a governance
 * review record and notifies affected deployment owners so they can assess
 * the impact of the breaking capability change before the new version
 * propagates to autonomous deployments.
 */
class TriggerCapabilityContractGovernanceReview implements ShouldQueue
{
    public string $queue = 'governance';

    public int $tries = 3;

    public function __construct(private readonly AuditService $auditService) {}

    public function handle(AgentCapabilityContractChanged $event): void
    {
        $newVersion = $event->newVersion;
        $prevVersion = $event->previousVersion;
        $agent = $newVersion->agent;

        // Log the breaking change as a governance audit entry
        $this->auditService->logUserAction(
            event: 'agent_capability_contract_changed',
            description: "Agent capability contract changed for agent #{$agent->id} — new version {$newVersion->version} introduces breaking changes",
            subject: $agent,
            metadata: [
                'agent_id' => $agent->id,
                'new_version' => $newVersion->version,
                'previous_version' => $prevVersion->version,
                'new_capabilities' => array_keys($newVersion->capabilities_snapshot ?? []),
                'previous_capabilities' => array_keys($prevVersion->capabilities_snapshot ?? []),
                'breaking_change' => true,
            ]
        );

        // Agent is a platform-wide catalog resource with no organization_id of
        // its own — notify admins of every organization that actually has a
        // deployment of this agent, not a single (nonexistent) owning org.
        $affectedOrgIds = AgentDeployment::withoutGlobalScope('organization')
            ->where('agent_id', $agent->id)
            ->pluck('organization_id')
            ->unique();

        foreach ($affectedOrgIds as $organizationId) {
            SendPlatformNotification::toAdmins(
                organizationId: $organizationId,
                type: 'agent_capability_contract_changed',
                title: "Breaking capability change — {$agent->name} v{$newVersion->version}",
                message: "Agent \"{$agent->name}\" was published with a breaking capability "
                    ."change from v{$prevVersion->version} to v{$newVersion->version}. "
                    .'Review active deployments to ensure compatibility.',
                severity: 'warning',
                data: [
                    'agent_id' => $agent->id,
                    'new_version_id' => $newVersion->id,
                    'prev_version_id' => $prevVersion->id,
                    'governance_action' => 'review_required',
                ],
            );
        }

        Log::info('[TriggerCapabilityContractGovernanceReview] Breaking change governance review created', [
            'agent_id' => $agent->id,
            'new_version' => $newVersion->version,
            'prev_version' => $prevVersion->version,
        ]);
    }

    public function failed(AgentCapabilityContractChanged $event, Throwable $exception): void
    {
        Log::error('[TriggerCapabilityContractGovernanceReview] Failed to process breaking change event', [
            'agent_id' => $event->newVersion->agent_id,
            'new_version' => $event->newVersion->version,
            'error' => $exception->getMessage(),
        ]);
    }
}
