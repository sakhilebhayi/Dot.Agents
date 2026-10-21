<?php

namespace Tests\Feature\ViewerRole;

use App\Livewire\Agents\AgentChat;
use App\Livewire\Agents\DeploymentManager;
use App\Livewire\Agents\ScorecardViewer;
use App\Livewire\Dashboard\AgentDashboard;
use App\Livewire\Governance\ApprovalQueue;
use App\Livewire\Marketplace\AgentMarketplace;
use App\Livewire\NotificationBell;
use App\Livewire\Organizations\ApiKeyManager;
use App\Livewire\Organizations\CustomSkillManager;
use App\Livewire\Organizations\DepartmentManager;
use App\Livewire\Organizations\KnowledgeManager;
use App\Livewire\Organizations\OrganizationSettings;
use App\Livewire\Organizations\OrgMemberManager;
use App\Livewire\Organizations\SocialCredentials;
use App\Livewire\Security\SecurityCenter;
use App\Livewire\Social\ConnectedAccounts;
use App\Livewire\Social\LeadPipeline;
use App\Livewire\Social\SentimentMonitor;
use App\Livewire\Social\SocialInbox;
use App\Livewire\Social\SocialPostManager;
use App\Livewire\Workflows\WorkflowBuilder;
use App\Livewire\Workflows\WorkflowList;
use App\Models\Agent;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentSkill;
use App\Models\AgentWorkflow;
use App\Models\Department;
use App\Models\KnowledgeBase;
use App\Models\PlatformNotification;
use App\Models\SecurityEvent;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Concerns\MakesRoleScenarios;
use Tests\TestCase;

/**
 * Livewire actions, one family at a time. Every viewer here is `owner` of the
 * organization, so the policies would say yes: the 403 is the read-only rule.
 * Where an editor can do the same thing, the test shows it still works.
 */
class ViewerReadOnlyLivewireTest extends TestCase
{
    use MakesRoleScenarios;
    use RefreshDatabase;

    private User $viewer;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Livewire::withoutLazyLoading();

        $this->setUpCompany();
        $this->viewer = $this->memberWith('viewer');
        $this->editor = $this->memberWith('editor');
        session(['current_organization_id' => $this->organization->id]);
    }

    private function as(User $user, string $component, array $params = [])
    {
        return Livewire::actingAs($user)->test($component, $params);
    }

    // ── deployments, approvals, scorecards ───────────────────────────────

    public function test_pausing_a_deployment(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);

        $this->as($this->viewer, DeploymentManager::class)->call('pauseDeployment', $deployment->id)->assertForbidden();
        $this->assertSame('active', $deployment->fresh()->status);

        $this->as($this->editor, DeploymentManager::class)->call('pauseDeployment', $deployment->id);
        $this->assertSame('paused', $deployment->fresh()->status);
    }

    public function test_decommissioning_and_resuming_a_deployment_are_refused_for_a_viewer(): void
    {
        $paused = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'paused']);

        $this->as($this->viewer, DeploymentManager::class)->call('resumeDeployment', $paused->id)->assertForbidden();
        $this->as($this->viewer, DeploymentManager::class)->call('decommissionDeployment', $paused->id)->assertForbidden();
        $this->assertSame('paused', $paused->fresh()->status);
    }

    public function test_a_viewer_sees_the_deployments_and_the_pause_button_is_hidden(): void
    {
        AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active', 'name' => 'Visible Bot']);

        $this->as($this->viewer, DeploymentManager::class)->assertSee('Visible Bot')->assertDontSee('pauseDeployment');
        $this->as($this->editor, DeploymentManager::class)->assertSee('Visible Bot')->assertSee('pauseDeployment');
    }

    public function test_approving_rejecting_and_escalating(): void
    {
        Notification::fake(); // the listener mails the requester; not under test here
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);
        $approval = AgentApproval::factory()->create(['organization_id' => $this->organization->id, 'agent_deployment_id' => $deployment->id, 'status' => 'pending', 'requested_from' => $this->companyOwner->id]);

        $this->as($this->viewer, ApprovalQueue::class)->call('selectApproval', $approval->id)->assertSet('selectedApproval.id', $approval->id);

        foreach (['approve', 'reject', 'escalate'] as $action) {
            $this->as($this->viewer, ApprovalQueue::class)->set('selectedApproval', $approval)->call($action)->assertForbidden();
        }
        $this->assertSame('pending', $approval->fresh()->status);

        $this->as($this->editor, ApprovalQueue::class)->call('selectApproval', $approval->id)->call('approve')->assertOk();
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_recalculating_a_scorecard(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);

        $this->as($this->viewer, ScorecardViewer::class, ['deploymentId' => $deployment->id])->call('recalculate')->assertForbidden();
        $this->as($this->viewer, ScorecardViewer::class, ['deploymentId' => $deployment->id])->assertOk();
    }

    public function test_chatting_with_an_agent_is_refused_for_a_viewer(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);

        $this->as($this->viewer, AgentChat::class, ['deploymentId' => $deployment->id])->assertForbidden();
        $this->assertDatabaseMissing('agent_sessions', ['agent_deployment_id' => $deployment->id]);
    }

    public function test_an_editor_can_still_chat(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);

        $this->as($this->editor, AgentChat::class, ['deploymentId' => $deployment->id])->assertOk();
        $this->assertDatabaseHas('agent_sessions', ['agent_deployment_id' => $deployment->id, 'user_id' => $this->editor->id]);
    }

    // ── marketplace ──────────────────────────────────────────────────────

    public function test_deploying_from_the_marketplace(): void
    {
        $agent = Agent::factory()->create();

        $this->as($this->viewer, AgentMarketplace::class)->call('startDeploy', $agent->id)->assertForbidden();
        $this->as($this->viewer, AgentMarketplace::class)
            ->set('deployingAgentId', $agent->id)
            ->set('deployForm.deployment_name', 'Sneaky')
            ->call('deploy')
            ->assertForbidden();
        $this->assertDatabaseMissing('agent_deployments', ['name' => 'Sneaky']);
    }

    public function test_browsing_the_marketplace_still_works_for_a_viewer(): void
    {
        $agent = Agent::factory()->create();

        $this->as($this->viewer, AgentMarketplace::class)
            ->call('openPreview', $agent->id)
            ->assertOk()
            ->call('closePreview')
            ->call('clearFilters')
            ->assertOk();
    }

    // ── workflows ────────────────────────────────────────────────────────

    public function test_creating_and_deleting_workflows(): void
    {
        $workflow = AgentWorkflow::factory()->create(['organization_id' => $this->organization->id]);

        $this->as($this->viewer, WorkflowList::class)->set('newName', 'Sneaky flow')->call('createWorkflow')->assertForbidden();
        $this->as($this->viewer, WorkflowList::class)->call('deleteWorkflow', $workflow->id)->assertForbidden();
        $this->assertDatabaseMissing('agent_workflows', ['name' => 'Sneaky flow']);
        $this->assertNotNull(AgentWorkflow::find($workflow->id));

        $this->as($this->editor, WorkflowList::class)->set('newName', 'Honest flow')->call('createWorkflow');
        $this->assertDatabaseHas('agent_workflows', ['name' => 'Honest flow']);
    }

    public function test_editing_saving_publishing_and_running_a_workflow(): void
    {
        $workflow = AgentWorkflow::factory()->create(['organization_id' => $this->organization->id, 'status' => 'draft']);
        $nodes = [['id' => 'n1', 'agent_key' => 'a', 'label' => 'A', 'x' => 1, 'y' => 1, 'config' => []]];

        foreach ([['save', null], ['publish', null], ['unpublish', null], ['run', null], ['addNode', 'x'], ['removeNode', 'n1']] as [$action, $arg]) {
            $this->as($this->viewer, WorkflowBuilder::class, ['workflowId' => $workflow->id])
                ->set('nodes', $nodes)
                ->call($action, ...($arg ? [$arg] : []))
                ->assertForbidden();
        }

        $this->assertSame('draft', $workflow->fresh()->status);
        $this->assertDatabaseCount('workflow_nodes', 0);

        $this->as($this->editor, WorkflowBuilder::class, ['workflowId' => $workflow->id])->set('nodes', $nodes)->call('save');
        $this->assertDatabaseCount('workflow_nodes', 1);
    }

    // ── organization ─────────────────────────────────────────────────────

    public function test_organization_member_management(): void
    {
        $newcomer = User::factory()->create();

        $this->as($this->viewer, OrgMemberManager::class)->set('inviteEmail', $newcomer->email)->call('invite')->assertForbidden();
        $this->as($this->viewer, OrgMemberManager::class)->call('updateRole', $this->editor->id, 'viewer')->assertForbidden();
        $this->as($this->viewer, OrgMemberManager::class)->set('removingUserId', $this->editor->id)->call('removeMember')->assertForbidden();

        $this->assertFalse($this->organization->users()->where('users.id', $newcomer->id)->exists());
        $this->assertSame('owner', $this->organization->users()->where('users.id', $this->editor->id)->first()->pivot->role);

        $this->as($this->editor, OrgMemberManager::class)->set('inviteEmail', $newcomer->email)->call('invite');
        $this->assertTrue($this->organization->users()->where('users.id', $newcomer->id)->exists());
    }

    public function test_departments(): void
    {
        $this->as($this->viewer, DepartmentManager::class)->set('formName', 'Sneaky')->call('save')->assertForbidden();
        $this->assertSame(0, Department::where('name', 'Sneaky')->count());

        $this->as($this->editor, DepartmentManager::class)->set('formName', 'Honest')->call('save');
        $this->assertSame(1, Department::where('name', 'Honest')->count());
    }

    public function test_knowledge_bases(): void
    {
        $this->as($this->viewer, KnowledgeManager::class)->set('baseName', 'Sneaky KB')->call('saveBase')->assertForbidden();
        $this->assertSame(0, KnowledgeBase::where('name', 'Sneaky KB')->count());

        $kb = KnowledgeBase::create(['organization_id' => $this->organization->id, 'name' => 'Existing', 'slug' => 'existing', 'access_level' => 'internal']);
        $this->as($this->viewer, KnowledgeManager::class)->call('selectBase', $kb->id)->assertOk();
    }

    public function test_organization_settings(): void
    {
        $original = $this->organization->name;

        $this->as($this->viewer, OrganizationSettings::class)->set('name', 'Hijacked')->call('save')->assertForbidden();
        $this->assertSame($original, $this->organization->fresh()->name);

        $this->as($this->editor, OrganizationSettings::class)->set('name', 'Renamed')->call('save');
        $this->assertSame('Renamed', $this->organization->fresh()->name);
    }

    public function test_social_credentials(): void
    {
        $this->as($this->viewer, SocialCredentials::class)->call('openEdit', 'facebook')->assertForbidden();

        $this->as($this->viewer, SocialCredentials::class)
            ->set('editing', 'facebook')->set('clientId', 'id')->set('clientSecret', 'secret')
            ->call('savePlatform')->assertForbidden();
        $this->as($this->viewer, SocialCredentials::class)->call('removePlatform', 'facebook')->assertForbidden();

        $this->assertDatabaseCount('organization_social_credentials', 0);
    }

    public function test_api_keys(): void
    {
        $this->as($this->viewer, ApiKeyManager::class)->set('newKeyName', 'my key')->call('createToken')->assertForbidden();
        $this->assertSame(0, $this->viewer->tokens()->count());

        $this->as($this->editor, ApiKeyManager::class)->set('newKeyName', 'my key')->call('createToken');
        $this->assertSame(1, $this->editor->tokens()->count());
    }

    public function test_custom_skills(): void
    {
        $skill = AgentSkill::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true]);

        $this->as($this->viewer, CustomSkillManager::class)->set('name', 'Sneaky')->set('webhookUrl', 'https://example.test/x')->call('create')->assertForbidden();
        $this->as($this->viewer, CustomSkillManager::class)->call('toggleActive', $skill->id)->assertForbidden();
        $this->as($this->viewer, CustomSkillManager::class)->call('delete', $skill->id)->assertForbidden();

        $this->assertTrue((bool) $skill->fresh()->is_active);
        $this->assertSame(0, AgentSkill::where('name', 'Sneaky')->count());
    }

    // ── security ─────────────────────────────────────────────────────────

    public function test_security_center_actions(): void
    {
        Role::findOrCreate('owner');
        $this->viewer->assignRole('owner');
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);
        $event = SecurityEvent::factory()->create(['organization_id' => $this->organization->id, 'status' => 'open']);

        $this->as($this->viewer, SecurityCenter::class)->assertOk();
        $this->as($this->viewer, SecurityCenter::class)->call('resolveEvent', $event->id)->assertForbidden();
        $this->as($this->viewer, SecurityCenter::class)->call('killDeployment', $deployment->id)->assertForbidden();
        $this->as($this->viewer, SecurityCenter::class)->set('killSwitchConfirmation', 'HALT WORKFLOWS')->call('killAllWorkflows')->assertForbidden();
        $this->as($this->viewer, SecurityCenter::class)->call('runDISCheck')->assertForbidden();

        $this->assertSame('active', $deployment->fresh()->status);
        $this->assertSame('open', $event->fresh()->status);
    }

    // ── social ───────────────────────────────────────────────────────────

    public function test_social_accounts_and_pipeline(): void
    {
        $account = SocialAccount::factory()->create(['organization_id' => $this->organization->id, 'platform' => 'facebook', 'status' => 'active']);
        $lead = SocialLead::factory()->create(['organization_id' => $this->organization->id]);

        $this->as($this->viewer, ConnectedAccounts::class)->call('disconnect', 'facebook')->assertForbidden();
        $this->as($this->viewer, ConnectedAccounts::class)->set('managing', 'facebook')->call('saveSettings')->assertForbidden();
        $this->assertFalse(SocialAccount::withTrashed()->find($account->id)->trashed());

        $status = $lead->status;
        $this->as($this->viewer, LeadPipeline::class)->call('qualify', $lead->id)->assertForbidden();
        $this->assertSame($status, $lead->fresh()->status);
    }

    public function test_social_inbox_posts_and_sentiment(): void
    {
        $account = SocialAccount::factory()->create(['organization_id' => $this->organization->id]);
        $conversation = SocialConversation::factory()->create(['organization_id' => $this->organization->id, 'social_account_id' => $account->id]);

        $this->as($this->viewer, SocialInbox::class)->call('openConversation', $conversation->id)->assertSet('activeConversationId', $conversation->id);
        $this->as($this->viewer, SocialInbox::class)->set('activeConversationId', $conversation->id)->set('replyContent', 'hello')->call('sendReply')->assertForbidden();
        $this->as($this->viewer, SocialInbox::class)->call('escalate', $conversation->id)->assertForbidden();
        $this->assertDatabaseCount('social_messages', 0);

        $this->as($this->viewer, SocialPostManager::class)
            ->set('composingPageId', 1)->set('composingContent', 'post')
            ->call('schedulePost')->assertForbidden();
        $this->as($this->viewer, SocialPostManager::class)->call('approvePost', 1)->assertForbidden();
        $this->as($this->viewer, SentimentMonitor::class)->call('markHandled', 1)->assertForbidden();
        $this->as($this->viewer, SentimentMonitor::class)->call('setTimeframe', '7d')->assertSet('timeframe', '7d');
        $this->assertDatabaseCount('social_posts', 0);
    }

    // ── what a viewer may still do ───────────────────────────────────────

    public function test_reading_actions_still_work_for_a_viewer(): void
    {
        $this->as($this->viewer, AgentDashboard::class)->call('setTimeframe', '30d')->assertSet('timeframe', '30d');
        $this->as($this->viewer, DeploymentManager::class)->set('search', 'x')->call('gotoPage', 2)->assertOk();
    }

    public function test_a_viewer_can_clear_their_own_notifications(): void
    {
        $note = PlatformNotification::factory()->create(['user_id' => $this->viewer->id, 'organization_id' => $this->organization->id, 'read_at' => null]);

        $this->as($this->viewer, NotificationBell::class)->call('markAsRead', $note->id)->assertOk()->call('markAllAsRead')->assertOk();
        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_the_hook_does_not_touch_people_who_are_not_read_only(): void
    {
        $deployment = AgentDeployment::factory()->create(['organization_id' => $this->organization->id, 'status' => 'active']);
        $admin = $this->memberWith('admin');

        $this->as($admin, DeploymentManager::class)->call('pauseDeployment', $deployment->id);
        $this->assertSame('paused', $deployment->fresh()->status);
    }
}
