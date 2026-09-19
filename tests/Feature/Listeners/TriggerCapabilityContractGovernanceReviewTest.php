<?php

namespace Tests\Feature\Listeners;

use App\Events\AgentCapabilityContractChanged;
use App\Jobs\SendPlatformNotification;
use App\Listeners\TriggerCapabilityContractGovernanceReview;
use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Models\AgentVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\Governance\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TriggerCapabilityContractGovernanceReviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_notifies_admins_of_every_organization_deploying_the_agent(): void
    {
        Bus::fake([SendPlatformNotification::class]);

        // Agent is a platform-wide catalog resource — it has no organization_id
        // of its own. Two unrelated organizations both deploy it.
        $agent = Agent::factory()->create();

        $ownerA = User::factory()->create();
        $orgA = Organization::factory()->create(['owner_id' => $ownerA->id]);
        $orgA->users()->attach($ownerA->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
        AgentDeployment::factory()->create(['agent_id' => $agent->id, 'organization_id' => $orgA->id]);

        $ownerB = User::factory()->create();
        $orgB = Organization::factory()->create(['owner_id' => $ownerB->id]);
        $orgB->users()->attach($ownerB->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
        AgentDeployment::factory()->create(['agent_id' => $agent->id, 'organization_id' => $orgB->id]);

        // A third organization has never deployed this agent — must not be notified.
        $unrelatedOrg = Organization::factory()->create();

        $previousVersion = AgentVersion::create([
            'agent_id' => $agent->id,
            'created_by' => $ownerA->id,
            'version' => '1.0.0',
            'status' => 'deprecated',
            'config_snapshot' => [], 'capabilities_snapshot' => ['analyze' => ['input_type' => 'text']],
            'is_current' => false,
        ]);

        $newVersion = AgentVersion::create([
            'agent_id' => $agent->id,
            'created_by' => $ownerA->id,
            'version' => '2.0.0',
            'status' => 'published',
            'config_snapshot' => [], 'capabilities_snapshot' => [],
            'is_current' => true,
        ]);

        $auditService = Mockery::mock(AuditService::class);
        $auditService->shouldReceive('logUserAction')->once();

        $listener = new TriggerCapabilityContractGovernanceReview($auditService);
        $listener->handle(new AgentCapabilityContractChanged($newVersion, $previousVersion));

        Bus::assertDispatched(
            SendPlatformNotification::class,
            fn (SendPlatformNotification $job) => $job->organizationId === $orgA->id
                && $job->type === 'agent_capability_contract_changed'
        );

        Bus::assertDispatched(
            SendPlatformNotification::class,
            fn (SendPlatformNotification $job) => $job->organizationId === $orgB->id
                && $job->type === 'agent_capability_contract_changed'
        );

        Bus::assertNotDispatched(
            SendPlatformNotification::class,
            fn (SendPlatformNotification $job) => $job->organizationId === $unrelatedOrg->id
        );
    }

    #[Test]
    public function it_notifies_only_once_per_organization_with_multiple_deployments(): void
    {
        Bus::fake([SendPlatformNotification::class]);

        $agent = Agent::factory()->create();

        $owner = User::factory()->create();
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $org->users()->attach($owner->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        // Two separate deployments of the same agent within the same organization.
        AgentDeployment::factory()->count(2)->create(['agent_id' => $agent->id, 'organization_id' => $org->id]);

        $previousVersion = AgentVersion::create([
            'agent_id' => $agent->id,
            'created_by' => $owner->id,
            'version' => '1.0.0',
            'status' => 'deprecated',
            'config_snapshot' => [], 'capabilities_snapshot' => ['analyze' => ['input_type' => 'text']],
            'is_current' => false,
        ]);

        $newVersion = AgentVersion::create([
            'agent_id' => $agent->id,
            'created_by' => $owner->id,
            'version' => '2.0.0',
            'status' => 'published',
            'config_snapshot' => [], 'capabilities_snapshot' => [],
            'is_current' => true,
        ]);

        $auditService = Mockery::mock(AuditService::class);
        $auditService->shouldReceive('logUserAction')->once();

        $listener = new TriggerCapabilityContractGovernanceReview($auditService);
        $listener->handle(new AgentCapabilityContractChanged($newVersion, $previousVersion));

        Bus::assertDispatchedTimes(SendPlatformNotification::class, 1);
    }
}
