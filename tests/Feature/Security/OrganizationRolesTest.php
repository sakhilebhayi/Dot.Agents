<?php

namespace Tests\Feature\Security;

use App\Livewire\Organizations\OrgMemberManager;
use App\Models\AgentDeployment;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrgRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationRolesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
    }

    private function member(string $role): User
    {
        $user = User::factory()->withPersonalTeam()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization->users()->attach($user->id, ['role' => $role, 'is_primary' => true, 'joined_at' => now()]);
        session(['current_organization_id' => $this->organization->id]);

        return $user;
    }

    /** @return array<string, array{string, string, bool}> */
    public static function matrix(): array
    {
        return [
            'an owner owns' => ['owner', 'organization.own', true],
            'an admin does not own' => ['admin', 'organization.own', false],
            'an admin manages people' => ['admin', 'members.manage', true],
            'an agent manager configures agents' => ['agent_manager', 'agents.manage', true],
            'an agent manager does not manage people' => ['agent_manager', 'members.manage', false],
            'a developer builds tools' => ['developer', 'tools.manage', true],
            'a developer does not review' => ['developer', 'approvals.review', false],
            'an approver reviews' => ['approver', 'approvals.review', true],
            'an approver does not configure' => ['approver', 'agents.manage', false],
            'an operator starts agents' => ['operator', 'agents.operate', true],
            'an operator does not configure' => ['operator', 'agents.manage', false],
            'a viewer sees work' => ['viewer', 'runs.view', true],
            'a viewer does not start agents' => ['viewer', 'agents.operate', false],
            'an old member is an operator' => ['member', 'agents.operate', true],
            'an old manager is an agent manager' => ['manager', 'agents.manage', true],
            'an unknown role has nothing' => ['wizard', 'agents.view', false],
        ];
    }

    #[DataProvider('matrix')]
    public function test_each_role_means_what_it_says(string $role, string $capability, bool $allowed): void
    {
        $this->assertSame($allowed, OrgRoles::allows($role, $capability));
    }

    public function test_an_owner_reaches_the_audit_log_and_security_centre(): void
    {
        $owner = $this->member('owner');

        $this->actingAs($owner)->get('/governance/audit')->assertOk();
        $this->assertTrue($owner->can('viewAny', AuditLog::class));
    }

    public function test_someone_outside_the_business_has_no_role_in_it(): void
    {
        $outsider = User::factory()->create();

        $this->assertNull($outsider->roleInOrganization($this->organization->id));
        $this->assertFalse($outsider->canInOrganization('agents.view', $this->organization->id));
    }

    public function test_an_agent_manager_can_change_an_agent_and_an_operator_cannot(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue($this->member('agent_manager')->can('update', $deployment));
        $this->assertFalse($this->member('operator')->can('update', $deployment));
    }

    public function test_an_admin_cannot_make_themselves_owner(): void
    {
        $admin = $this->member('admin');
        $other = $this->member('viewer');

        Livewire::actingAs($admin)->test(OrgMemberManager::class)->call('updateRole', $other->id, 'owner')->assertForbidden();
        Livewire::actingAs($admin)->test(OrgMemberManager::class)->call('updateRole', $admin->id, 'owner')->assertForbidden();

        $this->assertSame('admin', $admin->roleInOrganization($this->organization->id));
    }

    public function test_an_admin_cannot_change_an_owner(): void
    {
        $owner = $this->member('owner');
        $admin = $this->member('admin');

        Livewire::actingAs($admin)->test(OrgMemberManager::class)->call('updateRole', $owner->id, 'viewer')->assertForbidden();

        $this->assertSame('owner', $owner->roleInOrganization($this->organization->id));
    }

    public function test_a_made_up_role_is_refused(): void
    {
        $admin = $this->member('admin');
        $other = $this->member('viewer');

        Livewire::actingAs($admin)->test(OrgMemberManager::class)->call('updateRole', $other->id, 'wizard')->assertStatus(422);
    }

    public function test_a_role_change_is_written_to_the_audit_log(): void
    {
        $admin = $this->member('admin');
        $other = $this->member('viewer');

        Livewire::actingAs($admin)->test(OrgMemberManager::class)->call('updateRole', $other->id, 'approver')->assertOk();

        $this->assertSame('approver', $other->roleInOrganization($this->organization->id));
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('event', 'member.role_changed')->exists());
    }
}
