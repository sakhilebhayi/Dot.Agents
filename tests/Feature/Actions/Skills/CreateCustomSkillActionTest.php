<?php

namespace Tests\Feature\Actions\Skills;

use App\Actions\Skills\CreateCustomSkillAction;
use App\DTOs\Skills\CreateCustomSkillData;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomSkillActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_webhook_skill_scoped_to_the_organization(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create();
        $org->users()->attach($admin->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
        $this->actingAs($admin);

        $skill = app(CreateCustomSkillAction::class)->execute(new CreateCustomSkillData(
            organizationId: $org->id,
            name: 'Inventory Lookup',
            description: 'Looks up stock levels.',
            webhookUrl: 'https://example.test/skills/inventory',
        ));

        $this->assertSame($org->id, $skill->organization_id);
        $this->assertSame('Inventory Lookup', $skill->name);
        $this->assertSame('https://example.test/skills/inventory', $skill->webhook_url);
        $this->assertFalse($skill->is_built_in);
        $this->assertTrue($skill->is_active);
        $this->assertNotEmpty($skill->key);
    }

    public function test_generates_distinct_keys_when_two_orgs_pick_the_same_skill_name(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $admin = User::factory()->create();
        $orgA->users()->attach($admin->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
        $orgB->users()->attach($admin->id, ['role' => 'admin', 'is_primary' => true, 'joined_at' => now()]);
        $this->actingAs($admin);

        $action = app(CreateCustomSkillAction::class);

        $skillA = $action->execute(new CreateCustomSkillData($orgA->id, 'Lead Scorer', null, 'https://a.test/hook'));
        $skillB = $action->execute(new CreateCustomSkillData($orgB->id, 'Lead Scorer', null, 'https://b.test/hook'));

        $this->assertNotSame($skillA->key, $skillB->key);
    }

    public function test_a_user_with_no_org_role_cannot_create_a_custom_skill(): void
    {
        $org = Organization::factory()->create();
        $outsider = User::factory()->create();
        $this->actingAs($outsider);

        $this->expectException(AuthorizationException::class);

        app(CreateCustomSkillAction::class)->execute(new CreateCustomSkillData(
            organizationId: $org->id,
            name: 'Should Not Exist',
            description: null,
            webhookUrl: 'https://example.test/hook',
        ));

        $this->assertDatabaseMissing('agent_skills', ['name' => 'Should Not Exist']);
    }
}
