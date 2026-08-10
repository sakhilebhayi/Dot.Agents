<?php

namespace Tests\Unit\Policies;

use App\Models\AgentSkill;
use App\Models\Organization;
use App\Models\User;
use App\Policies\AgentSkillPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentSkillPolicyCustomSkillTest extends TestCase
{
    use RefreshDatabase;

    private AgentSkillPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AgentSkillPolicy;
    }

    private function memberOf(Organization $org, string $role = 'member'): User
    {
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['role' => $role, 'is_primary' => true, 'joined_at' => now()]);

        return $user;
    }

    public function test_platform_catalog_skill_is_visible_to_anyone(): void
    {
        $skill = AgentSkill::factory()->create(); // organization_id null
        $outsider = User::factory()->create();

        $this->assertTrue($this->policy->view($outsider, $skill));
    }

    public function test_custom_skill_is_visible_only_within_its_own_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($orgA->id)->create();

        $memberOfA = $this->memberOf($orgA);
        $memberOfB = $this->memberOf($orgB);

        $this->assertTrue($this->policy->view($memberOfA, $skill));
        $this->assertFalse($this->policy->view($memberOfB, $skill));
    }

    public function test_org_admin_can_update_their_own_orgs_custom_skill(): void
    {
        $org = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($org->id)->create();
        $admin = $this->memberOf($org, 'admin');

        $this->assertTrue($this->policy->update($admin, $skill));
    }

    public function test_org_admin_cannot_update_another_orgs_custom_skill(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($orgA->id)->create();
        $adminOfB = $this->memberOf($orgB, 'admin');

        $this->assertFalse($this->policy->update($adminOfB, $skill));
    }

    public function test_org_admin_cannot_update_the_shared_platform_catalog(): void
    {
        $org = Organization::factory()->create();
        $platformSkill = AgentSkill::factory()->create(); // organization_id null
        $admin = $this->memberOf($org, 'admin');

        $this->assertFalse($this->policy->update($admin, $platformSkill));
    }

    public function test_regular_member_cannot_update_their_orgs_custom_skill(): void
    {
        $org = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($org->id)->create();
        $member = $this->memberOf($org, 'member');

        $this->assertFalse($this->policy->update($member, $skill));
    }

    public function test_org_admin_can_delete_their_own_orgs_custom_skill(): void
    {
        $org = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($org->id)->create();
        $admin = $this->memberOf($org, 'admin');

        $this->assertTrue($this->policy->delete($admin, $skill));
    }

    public function test_org_admin_cannot_delete_another_orgs_custom_skill(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $skill = AgentSkill::factory()->webhook($orgA->id)->create();
        $adminOfB = $this->memberOf($orgB, 'admin');

        $this->assertFalse($this->policy->delete($adminOfB, $skill));
    }
}
