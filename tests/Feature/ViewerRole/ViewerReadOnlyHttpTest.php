<?php

namespace Tests\Feature\ViewerRole;

use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentSkillApproval;
use App\Models\AgentWorkflow;
use App\Models\SocialAccount;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MakesRoleScenarios;
use Tests\TestCase;

/**
 * Routes and API endpoints: a viewer is refused (403) on every write and can still
 * read every page; editors and admins keep working. Organization role is `owner`
 * throughout, so only the read-only rule can be what stops the viewer.
 */
class ViewerReadOnlyHttpTest extends TestCase
{
    use MakesRoleScenarios;
    use RefreshDatabase;

    private AgentDeployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCompany();
        $this->deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);
    }

    // ── read pages keep working ──────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function readPages(): array
    {
        return array_map(fn ($url) => [$url], [
            'dashboard' => '/dashboard',
            'marketplace' => '/marketplace',
            'my agents' => '/my-agents',
            'workflows' => '/workflows',
            'approvals' => '/governance/approvals',
            'decisions' => '/governance/decisions',
            'security' => '/security',
            'departments' => '/organization/departments',
            'members' => '/organization/members',
            'knowledge' => '/organization/knowledge',
            'skills' => '/organization/skills',
            'org settings' => '/organization/settings',
            'billing' => '/billing',
            'plans' => '/billing/plans',
            'billing settings' => '/billing/settings',
            'social' => '/social',
            'inbox' => '/social/inbox',
            'leads' => '/social/leads',
            'posts' => '/social/posts',
            'sentiment' => '/social/sentiment',
            'accounts' => '/social/accounts',
            'connect' => '/social/connect',
        ]);
    }

    #[DataProvider('readPages')]
    public function test_a_viewer_can_read_every_page(string $url): void
    {
        $this->actingInCompany($this->memberWith('viewer'))->get($url)->assertOk();
    }

    public function test_a_viewer_can_read_deployment_and_workflow_detail_pages(): void
    {
        $workflow = AgentWorkflow::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingInCompany($this->memberWith('viewer'));

        $this->get(route('agents.show', $this->deployment))->assertOk();
        $this->get(route('agents.scorecard', $this->deployment))->assertOk();
        $this->get(route('workflows.builder', $workflow))->assertOk();
    }

    public function test_a_viewer_with_a_compliance_role_can_read_the_audit_log(): void
    {
        // The audit log is gated by its own (Spatie) roles; "viewAny" stays allowed for a viewer.
        $viewer = $this->memberWith('viewer');
        Role::findOrCreate('compliance_officer');
        $viewer->assignRole('compliance_officer');

        $this->actingInCompany($viewer)->get('/governance/audit')->assertOk();
    }

    // ── web routes that write ────────────────────────────────────────────

    public function test_billing_checkout_post_is_refused_for_a_viewer(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->actingInCompany($this->memberWith('viewer'))
            ->post(route('billing.checkout', $plan))
            ->assertForbidden();
    }

    public function test_billing_portal_is_refused_for_a_viewer(): void
    {
        $this->actingInCompany($this->memberWith('viewer'))
            ->get(route('billing.portal'))
            ->assertForbidden();
    }

    public function test_disconnecting_a_social_account_is_refused_for_a_viewer_and_works_for_an_editor(): void
    {
        $account = SocialAccount::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);

        $this->actingInCompany($this->memberWith('viewer'))
            ->delete(route('social.accounts.destroy', $account))
            ->assertForbidden();
        $this->assertFalse(SocialAccount::withTrashed()->findOrFail($account->id)->trashed(), 'the account is still connected');

        $this->actingInCompany($this->memberWith('editor'))
            ->delete(route('social.accounts.destroy', $account))
            ->assertRedirect(route('social.accounts'));
        $this->assertTrue(SocialAccount::withTrashed()->findOrFail($account->id)->trashed(), 'the editor disconnected it');
    }

    public function test_the_social_connect_flow_is_refused_for_a_viewer_even_though_it_is_a_get(): void
    {
        $this->actingInCompany($this->memberWith('viewer'));

        $this->get(route('social.auth.redirect', ['platform' => 'facebook']))->assertForbidden();
        $this->get(route('social.auth.callback', ['platform' => 'facebook']))->assertForbidden();
    }

    public function test_the_social_connect_flow_is_not_blocked_for_an_editor_or_admin(): void
    {
        foreach (['editor', 'admin'] as $role) {
            $this->actingInCompany($this->memberWith($role));

            $this->assertNotSame(403, $this->get(route('social.auth.callback', ['platform' => 'facebook', 'error' => 'access_denied']))->getStatusCode(), $role);
        }
    }

    public function test_chat_is_refused_for_a_viewer_and_open_to_an_editor(): void
    {
        $this->actingInCompany($this->memberWith('viewer'))
            ->get(route('agents.chat', $this->deployment))
            ->assertForbidden();

        $this->actingInCompany($this->memberWith('editor'))
            ->get(route('agents.chat', $this->deployment))
            ->assertOk();
    }

    public function test_an_organization_level_viewer_is_read_only_too(): void
    {
        // Team role editor, but the members screen made them a viewer of the organization.
        $user = $this->memberWith('editor', 'viewer');
        $plan = SubscriptionPlan::factory()->create();

        $this->actingInCompany($user)->get('/dashboard')->assertOk();
        $this->post(route('billing.checkout', $plan))->assertForbidden();
        $this->get(route('agents.chat', $this->deployment))->assertForbidden();
    }

    // ── API ──────────────────────────────────────────────────────────────

    /** @return array<string, array{string, string, array<string, mixed>}> */
    public static function apiWrites(): array
    {
        return [
            'deploy' => ['POST', 'deployments', ['agent_id' => 1, 'name' => 'x', 'deployment_mode' => 'advisory']],
            'update' => ['PATCH', 'deployments/{d}', ['name' => 'renamed']],
            'pause' => ['POST', 'deployments/{d}/pause', []],
            'decommission' => ['DELETE', 'deployments/{d}', []],
            'assign skill' => ['POST', 'deployments/{d}/skills', ['skill_id' => 1]],
            'toggle skill' => ['PATCH', 'deployments/{d}/skills/{s}', ['is_enabled' => false]],
            'execute skill' => ['POST', 'deployments/{d}/skills/{s}/execute', ['input' => []]],
            'approve skill' => ['POST', 'skill-approvals/{a}/approve', []],
            'reject skill' => ['POST', 'skill-approvals/{a}/reject', ['reviewer_notes' => 'no']],
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('apiWrites')]
    public function test_every_api_write_is_refused_for_a_viewer_whatever_its_token_carries(string $method, string $path, array $payload): void
    {
        $skill = AgentSkill::factory()->create();
        $approval = AgentSkillApproval::factory()->create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $this->deployment->id, 'skill_id' => $skill->id]);
        $url = '/api/v1/'.str_replace(['{d}', '{s}', '{a}'], [$this->deployment->id, $skill->id, $approval->id], $path);

        $viewer = $this->memberWith('viewer');
        session(['current_organization_id' => $this->organization->id]);
        Sanctum::actingAs($viewer, ['read', 'write', 'deploy-agents']);

        $this->json($method, $url, $payload)->assertForbidden();

        $this->assertSame('active', $this->deployment->fresh()->status);
        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_a_viewer_can_still_read_through_the_api(): void
    {
        $skill = AgentSkill::factory()->create();

        Sanctum::actingAs($this->memberWith('viewer'), ['read']);
        session(['current_organization_id' => $this->organization->id]);

        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/deployments')->assertOk();
        $this->getJson('/api/v1/deployments/'.$this->deployment->id)->assertOk();
        $this->getJson('/api/v1/skills')->assertOk();
        $this->getJson('/api/v1/skills/'.$skill->id)->assertOk();
        $this->getJson('/api/v1/skill-approvals')->assertOk();
        $this->getJson('/api/v1/agents')->assertOk();
    }

    public function test_an_editor_and_an_admin_are_not_stopped_by_the_read_only_rule_on_the_api(): void
    {
        foreach (['editor', 'admin'] as $role) {
            $user = $this->memberWith($role);
            session(['current_organization_id' => $this->organization->id]);
            Sanctum::actingAs($user, ['read', 'write']);

            $this->postJson('/api/v1/deployments/'.$this->deployment->id.'/pause')->assertOk();
            $this->deployment->update(['status' => 'active']);
        }

    }
}
