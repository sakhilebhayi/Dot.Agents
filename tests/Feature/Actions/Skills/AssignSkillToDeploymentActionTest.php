<?php

namespace Tests\Feature\Actions\Skills;

use App\Actions\Skills\AssignSkillToDeploymentAction;
use App\DTOs\Skills\AssignSkillData;
use App\Events\SkillAssigned;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillAssignment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssignSkillToDeploymentActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    private AgentDeployment $deployment;

    private AgentSkill $skill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);
        $this->skill = AgentSkill::factory()->create(['is_active' => true]);
        $this->actingAs($this->user);
        Gate::before(fn () => true);
    }

    #[Test]
    public function test_assigns_skill_to_deployment(): void
    {
        Event::fake([SkillAssigned::class]);

        $data = new AssignSkillData(
            skillId: $this->skill->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
        );

        $result = app(AssignSkillToDeploymentAction::class)->execute($data);

        $this->assertInstanceOf(AgentSkillAssignment::class, $result);
        $this->assertTrue($result->is_enabled);
        $this->assertDatabaseHas('agent_skill_assignments', [
            'skill_id' => $this->skill->id,
            'agent_deployment_id' => $this->deployment->id,
        ]);
        Event::assertDispatched(SkillAssigned::class);
    }

    #[Test]
    public function test_aborts_when_skill_is_inactive(): void
    {
        $inactiveSkill = AgentSkill::factory()->create(['is_active' => false]);

        $data = new AssignSkillData(
            skillId: $inactiveSkill->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
        );

        $this->expectException(HttpException::class);
        app(AssignSkillToDeploymentAction::class)->execute($data);
    }

    #[Test]
    public function test_aborts_when_skill_belongs_to_another_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $othersSkill = AgentSkill::factory()->webhook($otherOrg->id)->create(['is_active' => true]);

        $data = new AssignSkillData(
            skillId: $othersSkill->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
        );

        $this->expectException(HttpException::class);
        app(AssignSkillToDeploymentAction::class)->execute($data);
    }

    #[Test]
    public function test_assigns_a_platform_wide_skill_regardless_of_organization(): void
    {
        Event::fake([SkillAssigned::class]);

        $platformSkill = AgentSkill::factory()->create(['is_active' => true]); // organization_id null

        $data = new AssignSkillData(
            skillId: $platformSkill->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
        );

        $result = app(AssignSkillToDeploymentAction::class)->execute($data);

        $this->assertInstanceOf(AgentSkillAssignment::class, $result);
    }

    #[Test]
    public function test_assigns_the_organizations_own_custom_skill(): void
    {
        Event::fake([SkillAssigned::class]);

        $ownSkill = AgentSkill::factory()->webhook($this->organization->id)->create(['is_active' => true]);

        $data = new AssignSkillData(
            skillId: $ownSkill->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
        );

        $result = app(AssignSkillToDeploymentAction::class)->execute($data);

        $this->assertInstanceOf(AgentSkillAssignment::class, $result);
    }

    #[Test]
    public function test_is_idempotent_on_reassignment(): void
    {
        Event::fake();

        $data = new AssignSkillData(
            skillId: $this->skill->id,
            agentDeploymentId: $this->deployment->id,
            organizationId: $this->organization->id,
            isEnabled: true,
        );

        app(AssignSkillToDeploymentAction::class)->execute($data);
        app(AssignSkillToDeploymentAction::class)->execute($data);

        $this->assertCount(1, AgentSkillAssignment::where('skill_id', $this->skill->id)
            ->where('agent_deployment_id', $this->deployment->id)
            ->get());
    }
}
