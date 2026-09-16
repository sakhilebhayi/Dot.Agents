<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Agents\DeploymentOverview;
use App\Models\Agent;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Models\AgentTask;
use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeploymentOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'name' => 'Priya Deployer',
            'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]],
        ]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->organization->users()->attach($this->user->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
        session(['current_organization_id' => $this->organization->id]);

        $department = Department::create([
            'organization_id' => $this->organization->id,
            'name' => 'Revenue Operations',
            'slug' => 'revenue-operations',
            'type' => 'operational',
            'is_active' => true,
        ]);

        $agent = Agent::factory()->create([
            'name' => 'Compliance Sentinel',
            'agent_type' => 'monitor',
        ]);

        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'agent_id' => $agent->id,
            'department_id' => $department->id,
            'deployed_by' => $this->user->id,
            'name' => 'Sentinel Prod',
            'alias' => 'Sentinel',
            'status' => 'active',
            'deployment_mode' => 'autonomous',
            'confidence_threshold' => 82.5,
            'risk_tolerance' => 30.0,
            'requires_human_approval' => true,
            'model_override' => null,
            'custom_instructions' => 'Escalate anything touching payroll.',
            'deployed_at' => now()->subDays(10),
            'last_active_at' => now()->subHours(2),
        ]);
    }

    public function test_authorized_org_member_sees_real_deployment_data_on_the_route(): void
    {
        $this->actingAs($this->user);

        $response = $this->get("/my-agents/{$this->deployment->id}");

        $response->assertOk();
        $response->assertSeeText('Sentinel');
        $response->assertSeeText('Compliance Sentinel');
        $response->assertSeeText('Revenue Operations');
        $response->assertSeeText('Active');
        $response->assertSeeText('Autonomous');
        $response->assertSeeText('Priya Deployer');
        $response->assertSee(route('agents.chat', $this->deployment), false);
        $response->assertSee(route('agents.scorecard', $this->deployment), false);
        // custom_instructions is sensitive configuration -- indicator only, never the plaintext.
        $response->assertDontSee('Escalate anything touching payroll');
    }

    public function test_component_mounts_and_exposes_the_real_deployment(): void
    {
        $this->actingAs($this->user);

        Livewire::test(DeploymentOverview::class, ['deploymentId' => $this->deployment->id])
            ->assertStatus(200)
            ->assertSet('deploymentId', $this->deployment->id)
            ->assertSee('Sentinel')
            ->assertSee('Compliance Sentinel');
    }

    public function test_enabled_skills_are_listed(): void
    {
        $this->actingAs($this->user);

        $skill = AgentSkill::factory()->create(['name' => 'Ledger Reconciliation', 'key' => 'skill.ledger-reconciliation']);
        AgentSkillAssignment::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'skill_id' => $skill->id,
            'is_enabled' => true,
        ]);
        $disabledSkill = AgentSkill::factory()->create(['name' => 'Retired Skill']);
        AgentSkillAssignment::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'skill_id' => $disabledSkill->id,
            'is_enabled' => false,
        ]);

        Livewire::test(DeploymentOverview::class, ['deploymentId' => $this->deployment->id])
            ->assertSee('Ledger Reconciliation')
            ->assertDontSee('Retired Skill');
    }

    public function test_recent_tasks_and_pending_approvals_are_shown(): void
    {
        $this->actingAs($this->user);

        AgentTask::factory()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
            'title' => 'Reconcile Q3 ledger',
            'status' => 'completed',
        ]);

        AgentApproval::factory()->pending()->create([
            'agent_deployment_id' => $this->deployment->id,
            'organization_id' => $this->organization->id,
        ]);

        Livewire::test(DeploymentOverview::class, ['deploymentId' => $this->deployment->id])
            ->assertSee('Reconcile Q3 ledger')
            ->assertSee('1 pending approval')
            ->assertSee(route('governance.approvals'), false);
    }

    public function test_non_active_deployment_shows_a_banner_instead_of_live_data(): void
    {
        $this->actingAs($this->user);

        $this->deployment->update(['status' => 'paused']);

        $component = Livewire::test(DeploymentOverview::class, ['deploymentId' => $this->deployment->id])
            ->assertSee('paused', false);

        $component->assertSeeHtml('is paused');
    }

    public function test_user_outside_the_organization_cannot_view_the_deployment(): void
    {
        $outsider = User::factory()->create([
            'consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]],
        ]);
        $this->actingAs($outsider);

        session(['current_organization_id' => $this->organization->id]);

        $this->get("/my-agents/{$this->deployment->id}")->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_route(): void
    {
        $this->get("/my-agents/{$this->deployment->id}")->assertRedirect();
    }
}
