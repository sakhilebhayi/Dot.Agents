<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Organizations\CustomSkillManager;
use App\Models\AgentSkill;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomSkillManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create();
        $this->organization->users()->attach($this->admin->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
        session(['current_organization_id' => $this->organization->id]);
    }

    public function test_org_admin_can_create_a_custom_skill(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CustomSkillManager::class)
            ->set('name', 'Inventory Lookup')
            ->set('webhookUrl', 'https://example.test/skills/inventory')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_skills', [
            'organization_id' => $this->organization->id,
            'name' => 'Inventory Lookup',
        ]);
    }

    public function test_invalid_webhook_url_is_rejected(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CustomSkillManager::class)
            ->set('name', 'Bad Skill')
            ->set('webhookUrl', 'not-a-url')
            ->call('create')
            ->assertHasErrors(['webhookUrl' => 'url']);
    }

    public function test_only_sees_its_own_organizations_custom_skills(): void
    {
        $otherOrg = Organization::factory()->create();
        AgentSkill::factory()->webhook($this->organization->id)->create(['name' => 'Mine']);
        AgentSkill::factory()->webhook($otherOrg->id)->create(['name' => 'Not Mine']);

        Livewire::actingAs($this->admin)
            ->test(CustomSkillManager::class)
            ->assertSee('Mine')
            ->assertDontSee('Not Mine');
    }

    public function test_cannot_delete_another_organizations_custom_skill_by_guessing_its_id(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreignSkill = AgentSkill::factory()->webhook($otherOrg->id)->create();

        // delete() scopes its lookup to the current org, so a foreign
        // skill's id never resolves in the first place -- a 404-shaped
        // failure (ModelNotFoundException), not a 403, the same IDOR
        // defense added to ApprovalQueue/KnowledgeManager (wiki §4a).
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(CustomSkillManager::class)
            ->call('delete', $foreignSkill->id);
    }

    public function test_can_toggle_a_custom_skill_active_state(): void
    {
        $skill = AgentSkill::factory()->webhook($this->organization->id)->create(['is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test(CustomSkillManager::class)
            ->call('toggleActive', $skill->id);

        $this->assertFalse($skill->fresh()->is_active);
    }

    public function test_regular_member_cannot_delete_a_custom_skill(): void
    {
        $member = User::factory()->create();
        $this->organization->users()->attach($member->id, ['role' => 'member', 'is_primary' => true, 'joined_at' => now()]);
        $skill = AgentSkill::factory()->webhook($this->organization->id)->create();

        Livewire::actingAs($member)
            ->test(CustomSkillManager::class)
            ->call('delete', $skill->id)
            ->assertForbidden();
    }
}
