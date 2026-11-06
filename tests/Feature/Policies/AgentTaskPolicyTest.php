<?php

namespace Tests\Feature\Policies;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentTaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_org_member_can_view_task_in_their_organization(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['role' => 'editor']);

        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
        ]);

        $this->assertTrue($user->can('view', $task));
    }

    public function test_user_from_different_org_cannot_view_task(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $user = User::factory()->create();
        $user->organizations()->attach($orgB->id, ['role' => 'editor']);

        $deployment = AgentDeployment::factory()->create(['organization_id' => $orgA->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $orgA->id,
        ]);

        $this->assertFalse($user->can('view', $task));
    }

    public function test_org_admin_can_update_task(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create();
        $admin->organizations()->attach($org->id, ['role' => 'admin']);

        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
        ]);

        $this->assertTrue($admin->can('update', $task));
    }

    public function test_org_editor_cannot_update_task(): void
    {
        $org = Organization::factory()->create();
        $editor = User::factory()->create();
        $editor->organizations()->attach($org->id, ['role' => 'editor']);

        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
        ]);

        $this->assertFalse($editor->can('update', $task));
    }

    public function test_only_someone_who_manages_agents_can_delete_a_task(): void
    {
        $org = Organization::factory()->create();

        $owner = User::factory()->create();
        $owner->organizations()->attach($org->id, ['role' => 'owner']);

        $admin = User::factory()->create();
        $admin->organizations()->attach($org->id, ['role' => 'admin']);

        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
        ]);

        $this->assertTrue($owner->can('delete', $task));
        $this->assertTrue($admin->can('delete', $task));
    }
}
