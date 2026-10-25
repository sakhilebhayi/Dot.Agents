<?php

namespace Tests\Feature\ViewerRole;

use App\Livewire\Agents\DeploymentManager;
use App\Livewire\Organizations\ApiKeyManager;
use App\Livewire\Organizations\DepartmentManager;
use App\Livewire\Organizations\OrganizationSettings;
use App\Models\AgentDeployment;
use App\Models\Department;
use App\Models\Organization;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\ReadOnlyAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Concerns\SignsInThroughInfoDot;
use Tests\TestCase;

/**
 * The point of mapping InfoDot companies to organizations: a company viewer is read-only
 * because the company's data lives in the company organization, where they hold `viewer`.
 * Their own personal organization stays theirs (and writable), and the company's data is
 * not reachable for writing from it. Everything here starts from a real InfoDot sign-in.
 */
class CompanyOrganizationReadOnlyTest extends TestCase
{
    use RefreshDatabase;
    use SignsInThroughInfoDot;

    private Organization $acme;

    private Organization $globex;

    private AgentDeployment $acmeDeployment;

    private AgentDeployment $personalDeployment;

    private User $viewer;

    private User $editor;

    private User $admin;

    private Organization $viewersPersonal;

    protected function setUp(): void
    {
        parent::setUp();

        Livewire::withoutLazyLoading();
        $this->configureInfoDot();

        $consent = ['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]];

        $this->signIn('ann@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'owner')], self::ACME);
        $this->signIn('gina@globex.test', [$this->company(self::GLOBEX, 'Globex Corp', 'admin')], self::GLOBEX);
        $this->signIn('vic@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'viewer')], self::ACME);
        $this->signIn('eddie@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'editor')], self::ACME);
        $this->signIn('ada@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'admin')], self::ACME);

        User::query()->update($consent);
        $this->acme = Organization::where('infodot_team_uuid', self::ACME)->firstOrFail();
        $this->globex = Organization::where('infodot_team_uuid', self::GLOBEX)->firstOrFail();
        $this->viewer = User::where('email', 'vic@acme.test')->firstOrFail();
        $this->editor = User::where('email', 'eddie@acme.test')->firstOrFail();
        $this->admin = User::where('email', 'ada@acme.test')->firstOrFail();

        // The viewer also has a personal organization, as everyone gets one, which they own.
        $this->viewersPersonal = Organization::factory()->create(['owner_id' => $this->viewer->id, 'infodot_team_uuid' => null]);
        $this->viewersPersonal->users()->attach($this->viewer->id, ['role' => 'owner', 'is_primary' => true, 'joined_at' => now()]);

        $this->acmeDeployment = AgentDeployment::factory()->create(['organization_id' => $this->acme->id, 'status' => 'active']);
        $this->personalDeployment = AgentDeployment::factory()->create(['organization_id' => $this->viewersPersonal->id, 'status' => 'active']);
    }

    private function workingIn(Organization $organization): void
    {
        session(['current_organization_id' => $organization->id]);
    }

    /** The deployment is another organization's: Livewire either refuses (403) or cannot find it (organization scope). */
    private function assertLivewireRefused(User $user, string $action): void
    {
        try {
            Livewire::actingAs($user)->test(DeploymentManager::class)->call($action, $this->acmeDeployment->id)->assertForbidden();
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    /** Working in one's own sandbox: the personal organization AND the personal team (no company selected anywhere). */
    private function workingInPersonalSpace(User $user, Organization $personal): void
    {
        $user->switchTeam($user->personalTeam());
        $this->workingIn($personal);
    }

    public function test_after_infodot_sign_in_a_company_viewer_is_in_the_company_organization_and_read_only(): void
    {
        $this->signIn('vic@acme.test', [$this->company(self::ACME, 'Acme Ltd', 'viewer')], self::ACME);

        $this->assertSame($this->acme->id, session('current_organization_id'), 'not their primary personal organization');
        $this->assertTrue(ReadOnlyAccess::applies($this->viewer));
        $this->assertFalse(ReadOnlyAccess::applies($this->editor));
        $this->assertFalse(ReadOnlyAccess::applies($this->admin));
    }

    public function test_a_company_viewer_cannot_write_through_http(): void
    {
        $this->workingIn($this->acme);
        $plan = SubscriptionPlan::factory()->create();

        $this->actingAs($this->viewer)->get('/dashboard')->assertOk();
        $this->get('/organization/departments')->assertOk();
        $this->post(route('billing.checkout', $plan))->assertForbidden();
        $this->get(route('agents.chat', $this->acmeDeployment))->assertForbidden();
    }

    public function test_a_company_viewer_cannot_write_through_the_api_but_can_read(): void
    {
        $this->workingIn($this->acme);
        Sanctum::actingAs($this->viewer, ['read', 'write', 'deploy-agents']);

        $this->getJson('/api/v1/deployments/'.$this->acmeDeployment->id)->assertOk();
        $this->postJson('/api/v1/deployments/'.$this->acmeDeployment->id.'/pause')->assertForbidden();
        $this->patchJson('/api/v1/deployments/'.$this->acmeDeployment->id, ['name' => 'renamed'])->assertForbidden();
        $this->deleteJson('/api/v1/deployments/'.$this->acmeDeployment->id)->assertForbidden();

        $this->assertSame('active', $this->acmeDeployment->fresh()->status);
    }

    public function test_a_company_viewer_cannot_write_through_livewire(): void
    {
        $this->workingIn($this->acme);

        Livewire::actingAs($this->viewer)->test(DeploymentManager::class)->call('pauseDeployment', $this->acmeDeployment->id)->assertForbidden();
        Livewire::actingAs($this->viewer)->test(DepartmentManager::class)->set('formName', 'Sneaky')->call('save')->assertForbidden();
        Livewire::actingAs($this->viewer)->test(OrganizationSettings::class)->set('name', 'Hijacked')->call('save')->assertForbidden();
        // No policy guards this one for ordinary members: only the read-only rule stops it.
        Livewire::actingAs($this->viewer)->test(ApiKeyManager::class)->set('newKeyName', 'my key')->call('createToken')->assertForbidden();

        $this->assertSame('active', $this->acmeDeployment->fresh()->status);
        $this->assertSame(0, Department::where('name', 'Sneaky')->count());
        $this->assertSame('Acme Ltd', $this->acme->fresh()->name);
        $this->assertSame(0, $this->viewer->tokens()->count());
    }

    public function test_the_company_organization_alone_makes_a_viewer_read_only_whichever_team_is_current(): void
    {
        // The Jetstream team layer is taken out of the picture: the personal team is current.
        $this->viewer->switchTeam($this->viewer->personalTeam());
        $this->workingIn($this->acme);
        $plan = SubscriptionPlan::factory()->create();

        $this->assertTrue(ReadOnlyAccess::applies($this->viewer));
        $this->actingAs($this->viewer)->post(route('billing.checkout', $plan))->assertForbidden();

        Sanctum::actingAs($this->viewer, ['read', 'write', 'deploy-agents']);
        $this->postJson('/api/v1/deployments/'.$this->acmeDeployment->id.'/pause')->assertForbidden();

        Livewire::actingAs($this->viewer)->test(ApiKeyManager::class)->set('newKeyName', 'my key')->call('createToken')->assertForbidden();
        $this->assertSame('active', $this->acmeDeployment->fresh()->status);
    }

    public function test_company_admins_and_editors_are_not_stopped_by_the_read_only_rule(): void
    {
        $this->workingIn($this->acme);

        Livewire::actingAs($this->admin)->test(DepartmentManager::class)->set('formName', 'Finance')->call('save');
        $this->assertSame(1, Department::where('name', 'Finance')->where('organization_id', $this->acme->id)->count());

        Livewire::actingAs($this->editor)->test(ApiKeyManager::class)->set('newKeyName', 'my key')->call('createToken');
        $this->assertSame(1, $this->editor->tokens()->count());

        Sanctum::actingAs($this->admin, ['read', 'write']);
        $this->postJson('/api/v1/deployments/'.$this->acmeDeployment->id.'/pause')->assertOk();
    }

    // ── cross-organization isolation ─────────────────────────────────────

    public function test_company_data_cannot_be_written_from_the_viewers_personal_organization(): void
    {
        $this->workingInPersonalSpace($this->viewer, $this->viewersPersonal); // the documented choice: the personal organization is theirs
        $this->assertFalse(ReadOnlyAccess::applies($this->viewer));

        Sanctum::actingAs($this->viewer, ['read', 'write', 'deploy-agents']);
        // Not the current organization's deployment: invisible (404, organization scope) or refused (403).
        foreach ([['postJson', '/pause'], ['patchJson', ''], ['deleteJson', '']] as [$verb, $suffix]) {
            $this->assertContains($this->{$verb}('/api/v1/deployments/'.$this->acmeDeployment->id.$suffix, ['name' => 'renamed'])->getStatusCode(), [403, 404], $verb);
        }

        foreach (['pauseDeployment', 'decommissionDeployment'] as $action) {
            $this->assertLivewireRefused($this->viewer, $action);
        }

        $this->assertTrue(Gate::forUser($this->viewer)->denies('update', $this->acmeDeployment));
        $this->assertTrue(Gate::forUser($this->viewer)->denies('delete', $this->acmeDeployment));
        $this->assertSame('active', $this->acmeDeployment->fresh()->status);
        $this->assertNotSame('renamed', $this->acmeDeployment->fresh()->name);
    }

    public function test_the_personal_organization_stays_writable_and_separate_from_the_company(): void
    {
        $this->workingInPersonalSpace($this->viewer, $this->viewersPersonal);

        Livewire::actingAs($this->viewer)->test(DepartmentManager::class)->set('formName', 'Mine')->call('save');

        $this->assertSame(1, Department::where('name', 'Mine')->where('organization_id', $this->viewersPersonal->id)->count());
        $this->assertSame(0, Department::where('organization_id', $this->acme->id)->count(), 'nothing leaks into the company');
    }

    public function test_a_member_of_one_company_cannot_write_in_another(): void
    {
        $gina = User::where('email', 'gina@globex.test')->firstOrFail();
        $this->workingIn($this->globex);

        Sanctum::actingAs($gina, ['read', 'write', 'deploy-agents']);
        // Another company's deployment is invisible to Gina (organization scope): 404, or 403 from the check behind it.
        $this->assertContains($this->postJson('/api/v1/deployments/'.$this->acmeDeployment->id.'/pause')->getStatusCode(), [403, 404]);
        $this->assertLivewireRefused($gina, 'pauseDeployment');

        $this->assertSame('active', $this->acmeDeployment->fresh()->status);
        $this->assertNull($this->acme->users()->where('users.id', $gina->id)->first());
    }

    public function test_pointing_the_session_at_a_company_organization_one_does_not_belong_to_is_refused(): void
    {
        $gina = User::where('email', 'gina@globex.test')->firstOrFail();
        $this->workingIn($this->acme);

        $this->actingAs($gina)->get('/dashboard')->assertForbidden();
    }
}
