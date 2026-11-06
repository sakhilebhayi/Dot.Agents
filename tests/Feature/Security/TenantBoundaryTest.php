<?php

namespace Tests\Feature\Security;

use App\Actions\Agents\DeployAgentAction;
use App\DTOs\Agents\DeployAgentData;
use App\Jobs\GenerateMegaV2PlatformScorecard;
use App\Jobs\RunDigitalImmuneSystemCheck;
use App\Livewire\Social\SocialPostManager;
use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\Department;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class TenantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Organization} */
    private function business(string $role = 'owner'): array
    {
        $user = User::factory()->withPersonalTeam()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $organization = Organization::factory()->create(['owner_id' => $user->id]);
        $organization->users()->attach($user->id, ['role' => $role, 'is_primary' => true, 'joined_at' => now()]);

        return [$user, $organization];
    }

    private function page(Organization $organization): SocialPage
    {
        $account = SocialAccount::factory()->create(['organization_id' => $organization->id, 'platform' => 'facebook']);

        return SocialPage::withoutGlobalScopes()->forceCreate([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'social_account_id' => $account->id,
            'platform_page_id' => 'page-'.$organization->id,
            'name' => 'Page',
        ]);
    }

    public function test_reading_tenant_data_with_no_business_throws(): void
    {
        TenantContext::lenient(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no business in context');

        AgentDeployment::count();
    }

    public function test_a_named_business_sees_only_its_own_rows(): void
    {
        [$a, $orgA] = $this->business();
        [$b, $orgB] = $this->business();
        AgentDeployment::factory()->create(['organization_id' => $orgA->id, 'deployed_by' => $a->id]);
        AgentDeployment::factory()->count(2)->create(['organization_id' => $orgB->id, 'deployed_by' => $b->id]);
        TenantContext::lenient(false);

        $this->assertSame(1, TenantContext::forOrganization($orgA->id, fn () => AgentDeployment::count()));
        $this->assertSame(3, TenantContext::system(fn () => AgentDeployment::count()));
    }

    public function test_context_is_restored_after_the_work_even_if_it_throws(): void
    {
        TenantContext::set(5);

        try {
            TenantContext::system(fn () => throw new RuntimeException('stop'));
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertSame(5, TenantContext::id());
        $this->assertFalse(TenantContext::isSystem());
    }

    public function test_a_job_runs_for_the_business_that_dispatched_it(): void
    {
        TenantContext::set(7);
        $carried = Context::dehydrate();
        TenantContext::set(null);

        $this->assertNull(TenantContext::id());

        Context::hydrate($carried);

        $this->assertSame(7, TenantContext::id());
    }

    public function test_shared_skills_stay_visible_with_no_business_and_private_ones_do_not(): void
    {
        [, $org] = $this->business();
        AgentSkill::withoutGlobalScopes()->forceCreate(['organization_id' => null, 'key' => 'shared', 'name' => 'Shared', 'layer' => 'workforce', 'category' => 'x', 'is_active' => true, 'is_built_in' => true]);
        AgentSkill::withoutGlobalScopes()->forceCreate(['organization_id' => $org->id, 'key' => 'mine', 'name' => 'Mine', 'layer' => 'workforce', 'category' => 'x', 'is_active' => true, 'is_built_in' => false]);
        TenantContext::lenient(false);

        $this->assertSame(['shared'], AgentSkill::pluck('key')->all());
    }

    public function test_one_business_never_receives_anothers_custom_skill_from_the_catalogue(): void
    {
        [$a, $orgA] = $this->business();
        [$b] = $this->business();
        AgentSkill::withoutGlobalScopes()->forceCreate([
            'organization_id' => $orgA->id, 'key' => 'a-secret-skill', 'name' => 'A Secret Skill', 'layer' => 'workforce', 'category' => 'custom',
            'webhook_url' => 'https://internal.a.test/hook', 'webhook_headers' => ['Authorization' => 'Bearer KEEP-ME'], 'is_active' => true, 'is_built_in' => false,
        ]);

        $this->withToken($a->createToken('t', ['read'])->plainTextToken)->getJson('/api/v1/skills')
            ->assertOk()->assertSee('A Secret Skill')->assertDontSee('KEEP-ME')->assertDontSee('internal.a.test');

        session()->forget('current_organization_id');
        app('auth')->forgetGuards();

        $this->withToken($b->createToken('t', ['read'])->plainTextToken)->getJson('/api/v1/skills')
            ->assertOk()->assertDontSee('A Secret Skill');
    }

    public function test_webhook_headers_are_not_stored_in_the_clear(): void
    {
        [, $org] = $this->business();
        $skill = AgentSkill::withoutGlobalScopes()->forceCreate([
            'organization_id' => $org->id, 'key' => 'k', 'name' => 'K', 'layer' => 'workforce', 'category' => 'custom',
            'webhook_headers' => ['Authorization' => 'Bearer KEEP-ME'], 'is_active' => true, 'is_built_in' => false,
        ]);

        $this->assertStringNotContainsString('KEEP-ME', (string) AgentSkill::withoutGlobalScopes()->toBase()->where('id', $skill->id)->value('webhook_headers'));
        $this->assertSame('Bearer KEEP-ME', $skill->fresh()->webhook_headers['Authorization']);
    }

    public function test_a_post_cannot_be_scheduled_on_another_businesss_page(): void
    {
        [$a, $orgA] = $this->business();
        [, $orgB] = $this->business();
        $theirs = $this->page($orgB);
        session(['current_organization_id' => $orgA->id]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($a)->test(SocialPostManager::class)
            ->set('composingPageId', $theirs->id)
            ->set('composingContent', 'From the wrong business')
            ->set('composingPostType', 'post')
            ->set('composingScheduledAt', now()->addHour()->toDateTimeString())
            ->call('schedulePost');
    }

    public function test_social_can_only_be_connected_to_this_businesss_agent(): void
    {
        [$a, $orgA] = $this->business();
        [$b, $orgB] = $this->business();
        $theirs = AgentDeployment::factory()->create(['organization_id' => $orgB->id, 'deployed_by' => $b->id]);
        config(['services.facebook.client_id' => 'x', 'services.facebook.client_secret' => 'y', 'services.facebook.redirect' => 'https://a.test/b']);

        $this->actingAs($a)->withSession(['current_organization_id' => $orgA->id])
            ->get("/social/auth/facebook/redirect?agent_deployment_id={$theirs->id}")
            ->assertSessionHasErrors('agent_deployment_id');
    }

    public function test_an_agent_cannot_be_deployed_into_another_businesss_department(): void
    {
        [$a, $orgA] = $this->business();
        [, $orgB] = $this->business();
        $department = Department::withoutGlobalScopes()->forceCreate(['organization_id' => $orgB->id, 'name' => 'Theirs', 'slug' => 'theirs']);
        $agent = Agent::factory()->create(['status' => 'active']);
        $this->actingAs($a);
        session(['current_organization_id' => $orgA->id]);

        $this->expectException(ModelNotFoundException::class);

        app(DeployAgentAction::class)->execute(new DeployAgentData(
            agentId: $agent->id, organizationId: $orgA->id, deployedBy: $a->id, name: 'X', deploymentMode: 'advisory', departmentId: $department->id,
        ));
    }

    public function test_a_removed_member_loses_the_business_on_their_next_click(): void
    {
        [$user, $org] = $this->business();
        AgentDeployment::factory()->create(['organization_id' => $org->id, 'deployed_by' => $user->id, 'name' => 'Stay-hidden']);
        $this->actingAs($user)->withSession(['current_organization_id' => $org->id]);

        $page = $this->get('/my-agents')->assertOk()->assertSee('Stay-hidden');
        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $found);
        $snapshot = html_entity_decode($found[1]);

        $org->users()->detach($user->id);
        session(['current_organization_id' => $org->id]);

        $update = $this->withHeaders(['X-Livewire' => '1'])
            ->postJson(Livewire::getUpdateUri(), ['_token' => csrf_token(), 'components' => [['snapshot' => $snapshot, 'updates' => (object) [], 'calls' => []]]]);

        $this->assertSame(403, $update->status());
    }

    public function test_scheduled_work_declares_its_business_and_runs_without_a_session(): void
    {
        [$user, $org] = $this->business();
        $org->update(['status' => 'active']);
        AgentDeployment::factory()->create(['organization_id' => $org->id, 'deployed_by' => $user->id]);
        TenantContext::lenient(false);

        app()->call([new RunDigitalImmuneSystemCheck, 'handle']);
        app()->call([new GenerateMegaV2PlatformScorecard, 'handle']);

        $this->artisan('approvals:expire-overdue')->assertSuccessful();
        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();
        $this->artisan('dwca:audit')->assertSuccessful();
        $this->assertTrue(true);
    }

    public function test_a_page_with_a_record_in_its_address_refuses_the_wrong_business_first(): void
    {
        [$a, $orgA] = $this->business();
        [$b, $orgB] = $this->business();
        $theirs = AgentDeployment::factory()->create(['organization_id' => $orgB->id, 'deployed_by' => $b->id]);

        session()->forget('current_organization_id');
        $this->withToken($a->createToken('t', ['read'])->plainTextToken)->getJson("/api/v1/deployments/{$theirs->id}")->assertNotFound();
    }
}
