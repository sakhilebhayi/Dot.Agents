<?php

namespace Tests\Feature\Governance;

use App\Actions\Governance\ProcessRetentionPurgeAction;
use App\DTOs\Governance\ProcessRetentionPurgeData;
use App\Events\RetentionPurgeProcessed;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RetentionPurgeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleTaskProposal(): RetentionPurgeProposal
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        return RetentionPurgeProposal::create([
            'model_class' => AgentTask::class,
            'eligible_count' => 1,
            'retention_summary' => 'Completed AgentTask rows older than 90 days',
            'status' => 'pending',
        ]);
    }

    private function platformAdmin(): User
    {
        if (! Role::where('name', 'platform_admin')->exists()) {
            Role::create(['name' => 'platform_admin']);
        }

        // A platform_admin browsing the app for real still belongs to (and has
        // a current session organization for) some organization, even though
        // the retention-purge decision itself isn't org-scoped -- matching
        // ApprovalWorkflowTest's own setUp() convention. AuditService::
        // logUserAction() resolves organization_id from the session, and
        // audit_logs.organization_id is NOT NULL, so this is required for a
        // realistic fixture, not an artificial scoping of the action.
        $org = Organization::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');
        session(['current_organization_id' => $org->id]);

        return $admin;
    }

    public function test_platform_admin_can_approve_and_the_rows_are_actually_deleted(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $result = app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved', 'Reviewed, safe to purge.'),
        );

        $this->assertSame('approved', $result->status);
        $this->assertSame($admin->id, $result->reviewed_by);
        $this->assertSame(1, $result->deleted_count);
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_platform_admin_can_reject_and_the_rows_are_untouched(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $result = app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'rejected', 'Need these for an ongoing investigation.'),
        );

        $this->assertSame('rejected', $result->status);
        $this->assertSame('Need these for an ongoing investigation.', $result->reviewer_notes);
        $this->assertDatabaseCount('agent_tasks', 1);
    }

    public function test_processing_fires_the_event(): void
    {
        Event::fake([RetentionPurgeProcessed::class]);
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved'),
        );

        Event::assertDispatched(RetentionPurgeProcessed::class);
    }

    public function test_processing_creates_an_audit_log(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved', 'Approved.'),
        );

        $this->assertDatabaseHas('audit_logs', ['event' => 'retention_purge.approved']);
    }

    public function test_cannot_process_an_already_reviewed_proposal(): void
    {
        $this->expectException(\RuntimeException::class);

        $proposal = $this->eligibleTaskProposal();
        $proposal->update(['status' => 'approved']);
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved'),
        );
    }

    public function test_a_user_without_platform_admin_role_is_denied(): void
    {
        $this->expectException(AuthorizationException::class);

        $proposal = $this->eligibleTaskProposal();
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved'),
        );
    }
}
