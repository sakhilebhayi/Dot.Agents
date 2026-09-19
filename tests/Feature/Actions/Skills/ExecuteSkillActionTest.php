<?php

namespace Tests\Feature\Actions\Skills;

use App\Actions\Skills\ExecuteSkillAction;
use App\DTOs\Skills\ExecuteSkillData;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExecuteSkillActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);
        $this->deployment = AgentDeployment::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->user);
    }

    private function data(int $skillId): ExecuteSkillData
    {
        return new ExecuteSkillData(
            skillId: $skillId,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
            actorId: $this->user->id,
            trigger: 'on_demand',
        );
    }

    #[Test]
    public function test_cannot_execute_another_organizations_custom_skill(): void
    {
        $otherOrg = Organization::factory()->create();
        $othersSkill = AgentSkill::factory()->webhook($otherOrg->id)->create(['is_active' => true]);

        $this->expectException(AuthorizationException::class);
        app(ExecuteSkillAction::class)->execute($this->data($othersSkill->id));
    }

    private function enable(AgentSkill $skill): void
    {
        AgentSkillAssignment::create([
            'agent_deployment_id' => $this->deployment->id,
            'skill_id' => $skill->id,
            'organization_id' => $this->organization->id,
            'is_enabled' => true,
        ]);
    }

    #[Test]
    public function test_can_execute_a_platform_wide_skill(): void
    {
        $platformSkill = AgentSkill::factory()->create([
            'is_active' => true,
            'approval_required' => false,
            'audit_required' => false,
        ]);
        $this->enable($platformSkill);

        $execution = app(ExecuteSkillAction::class)->execute($this->data($platformSkill->id));

        $this->assertSame('running', $execution->status);
    }

    #[Test]
    public function test_can_execute_its_own_organizations_custom_skill(): void
    {
        $ownSkill = AgentSkill::factory()->webhook($this->organization->id)->create([
            'is_active' => true,
            'approval_required' => false,
            'audit_required' => false,
        ]);
        $this->enable($ownSkill);

        $execution = app(ExecuteSkillAction::class)->execute($this->data($ownSkill->id));

        $this->assertSame('running', $execution->status);
    }
}
