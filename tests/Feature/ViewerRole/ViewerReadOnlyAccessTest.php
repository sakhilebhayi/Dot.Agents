<?php

namespace Tests\Feature\ViewerRole;

use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\PlatformNotification;
use App\Models\User;
use App\Support\ReadOnlyAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Laravel\Jetstream\Http\Livewire\CreateTeamForm;
use Laravel\Jetstream\Http\Livewire\DeleteTeamForm;
use Laravel\Jetstream\Http\Livewire\TeamMemberManager;
use Laravel\Jetstream\Http\Livewire\UpdateTeamNameForm;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Tests\Concerns\MakesRoleScenarios;
use Tests\TestCase;

/**
 * The shared rule (who is read-only), the Gate, team management, and a structural
 * check that no Livewire component action can slip past the viewer role by default.
 */
class ViewerReadOnlyAccessTest extends TestCase
{
    use MakesRoleScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCompany();
        session(['current_organization_id' => $this->organization->id]);
    }

    // ── who is read-only ─────────────────────────────────────────────────

    public function test_only_a_viewer_in_the_current_team_or_organization_is_read_only(): void
    {
        $this->assertTrue(ReadOnlyAccess::applies($this->memberWith('viewer')));
        $this->assertFalse(ReadOnlyAccess::applies($this->memberWith('editor')));
        $this->assertFalse(ReadOnlyAccess::applies($this->memberWith('admin')));
        $this->assertFalse(ReadOnlyAccess::applies($this->companyOwner), 'the team owner is never a viewer');
        $this->assertTrue(ReadOnlyAccess::applies($this->memberWith('editor', 'viewer')), 'organization-level viewer');
        $this->assertFalse(ReadOnlyAccess::applies(null));
    }

    public function test_the_role_only_applies_in_the_team_the_person_is_working_in(): void
    {
        $viewer = $this->memberWith('viewer');

        $personal = User::factory()->withPersonalTeam()->create();
        $viewer->forceFill(['current_team_id' => $personal->ownedTeams()->first()->id])->save();
        $this->assertFalse(ReadOnlyAccess::applies($viewer->fresh()), 'working in a different team');

        $viewer->forceFill(['current_team_id' => $this->company->id])->save();
        $this->assertTrue(ReadOnlyAccess::applies($viewer->fresh()));
    }

    // ── the Gate ─────────────────────────────────────────────────────────

    public function test_the_gate_refuses_every_write_ability_but_not_reads_for_a_viewer(): void
    {
        $viewer = $this->memberWith('viewer');
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);
        $this->actingAs($viewer); // viewAny policies read auth()
        $gate = Gate::forUser($viewer);

        $this->assertTrue($gate->allows('view', $deployment));
        $this->assertTrue($gate->allows('viewAny', AgentDeployment::class));
        $this->assertTrue($gate->allows('view', $this->organization));

        foreach (['update', 'delete', 'chat'] as $ability) {
            $this->assertTrue($gate->denies($ability, $deployment), $ability);
        }
        $this->assertTrue($gate->denies('create', [AgentDeployment::class, $this->organization->id]));
        $this->assertTrue($gate->denies('update', $this->organization));
        $this->assertTrue($gate->denies('manageMembers', $this->organization));
        $this->assertTrue($gate->denies('manageBilling', $this->organization));
    }

    public function test_the_gate_keeps_saying_yes_to_editors_and_admins(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);

        foreach (['editor', 'admin'] as $role) {
            $gate = Gate::forUser($this->memberWith($role));

            $this->assertTrue($gate->allows('update', $deployment), $role);
            $this->assertTrue($gate->allows('chat', $deployment), $role);
            $this->assertTrue($gate->allows('update', $this->organization), $role);
            $this->assertTrue($gate->allows('manageBilling', $this->organization), $role);
        }
    }

    public function test_a_viewer_keeps_control_of_their_own_notifications(): void
    {
        $viewer = $this->memberWith('viewer');
        $own = PlatformNotification::factory()->create(['user_id' => $viewer->id, 'organization_id' => $this->organization->id]);
        $others = PlatformNotification::factory()->create(['user_id' => $this->companyOwner->id, 'organization_id' => $this->organization->id]);

        $this->assertTrue(Gate::forUser($viewer)->allows('update', $own));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', $others));
    }

    public function test_an_organization_a_viewer_does_not_work_in_is_not_affected(): void
    {
        $viewer = $this->memberWith('editor', 'owner');
        $other = Organization::factory()->create(['owner_id' => $viewer->id]);
        $other->users()->attach($viewer->id, ['role' => 'viewer']);

        session(['current_organization_id' => $this->organization->id]);
        $this->assertFalse(ReadOnlyAccess::applies($viewer), 'viewer elsewhere, owner here');

        session(['current_organization_id' => $other->id]);
        $this->assertTrue(ReadOnlyAccess::applies($viewer));
    }

    // ── team management ──────────────────────────────────────────────────

    public function test_a_viewer_cannot_manage_team_members(): void
    {
        $viewer = $this->memberWith('viewer');
        $editor = $this->memberWith('editor');

        Livewire::actingAs($viewer)->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('addTeamMemberForm', ['email' => 'new@example.com', 'role' => 'admin'])
            ->call('addTeamMember')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('managingRoleFor', $editor)->set('currentRole', 'admin')
            ->call('updateRole')
            ->assertForbidden();

        Livewire::actingAs($viewer)->test(TeamMemberManager::class, ['team' => $this->company])
            ->set('teamMemberIdBeingRemoved', $editor->id)
            ->call('removeTeamMember')
            ->assertForbidden();

        $this->assertSame('editor', $editor->fresh()->teamRole($this->company->fresh())->key);
        $this->assertCount(0, $this->company->fresh()->teamInvitations);
    }

    public function test_a_viewer_cannot_rename_or_delete_the_team_or_make_a_new_one(): void
    {
        $viewer = $this->memberWith('viewer');

        Livewire::actingAs($viewer)->test(UpdateTeamNameForm::class, ['team' => $this->company])
            ->set('state', ['name' => 'Hijacked'])->call('updateTeamName')->assertForbidden();
        Livewire::actingAs($viewer)->test(DeleteTeamForm::class, ['team' => $this->company])
            ->call('deleteTeam')->assertForbidden();
        Livewire::actingAs($viewer)->test(CreateTeamForm::class)
            ->set('state', ['name' => 'Mine'])->call('createTeam')->assertForbidden();

        $this->assertNotSame('Hijacked', $this->company->fresh()->name);
        $this->assertNotNull($this->company->fresh());
    }

    public function test_a_viewer_can_view_the_team_and_leave_it(): void
    {
        $viewer = $this->memberWith('viewer');

        $this->actingInCompany($viewer)->get(route('teams.show', $this->company))->assertOk();

        Livewire::actingAs($viewer)->test(TeamMemberManager::class, ['team' => $this->company])
            ->call('leaveTeam');

        $this->assertFalse($viewer->fresh()->belongsToTeam($this->company->fresh()));
    }

    // ── nothing slips past by default ────────────────────────────────────

    /** @return list<class-string<Component>> */
    private function appLivewireComponents(): array
    {
        $classes = [];

        foreach (File::allFiles(app_path('Livewire')) as $file) {
            $class = 'App\\Livewire\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (class_exists($class) && is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** @return list<string> the public actions a client can call (not lifecycle, computed or render) */
    private function actionsOf(string $class): array
    {
        $actions = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($method->isStatic() || $method->getDeclaringClass()->getName() === Component::class
                || str_starts_with($name, '__') || str_starts_with($name, 'updat')
                || in_array($name, ['render', 'mount', 'boot', 'booted', 'hydrate', 'dehydrate', 'placeholder'], true)
                || $method->getAttributes(Computed::class)) {
                continue;
            }

            $actions[] = $name;
        }

        return $actions;
    }

    public function test_there_are_app_livewire_components_to_check(): void
    {
        $this->assertGreaterThan(30, count($this->appLivewireComponents()));
    }

    public function test_every_declared_read_only_action_exists_and_is_not_a_write_by_name(): void
    {
        $writeVerbs = '/^(save|create|delete|remove|approve|reject|escalate|send|run|publish|unpublish|deploy|startDeploy|kill|resolve|disconnect|revoke|invite|qualify|markHandled|schedule|pause|resume|decommission|toggleActive|recalculate|activate|connect|add|move|update(Role|Label)|confirm)/';
        $ownStateWrites = ['markAsRead', 'markAllAsRead']; // the person's own notifications

        foreach ($this->appLivewireComponents() as $class) {
            $declared = defined($class.'::READ_ONLY_ACTIONS') ? constant($class.'::READ_ONLY_ACTIONS') : [];

            foreach ($declared as $action) {
                $this->assertTrue(method_exists($class, $action) || $action === '__dispatch', "$class declares $action which does not exist");

                if (! in_array($action, $ownStateWrites, true)) {
                    $this->assertSame(0, preg_match($writeVerbs, $action), "$class::$action looks like a write but is allowed for viewers");
                }
            }
        }
    }

    public function test_every_other_action_on_every_component_is_refused_for_a_viewer_by_default(): void
    {
        $refused = 0;

        foreach ($this->appLivewireComponents() as $class) {
            $declared = defined($class.'::READ_ONLY_ACTIONS') ? constant($class.'::READ_ONLY_ACTIONS') : [];
            $component = Livewire::new($class);

            foreach ($this->actionsOf($class) as $action) {
                if (in_array($action, $declared, true) || in_array($action, ReadOnlyAccess::LIVEWIRE_FRAMEWORK_CALLS, true)) {
                    $this->assertTrue(ReadOnlyAccess::permitsLivewireCall($component, $action), "$class::$action");

                    continue;
                }

                $this->assertFalse(ReadOnlyAccess::permitsLivewireCall($component, $action), "$class::$action must be refused for a viewer");
                $refused++;
            }
        }

        $this->assertGreaterThan(60, $refused, 'the walk should have found the app\'s write actions');
    }
}
