<?php

namespace Tests\Feature\Agents;

use App\Actions\Agents\CancelRunAction;
use App\Actions\Agents\StartRunAction;
use App\Actions\Governance\DelegateApprovalAction;
use App\Actions\Governance\ProcessApprovalAction;
use App\Actions\Governance\SaveApprovalRuleAction;
use App\DTOs\Agents\CancelRunData;
use App\DTOs\Agents\StartRunData;
use App\DTOs\Governance\DelegateApprovalData;
use App\DTOs\Governance\ProcessApprovalData;
use App\DTOs\Governance\SaveApprovalRuleData;
use App\Events\ApprovalRequested;
use App\Livewire\Governance\ApprovalQueue;
use App\Livewire\Governance\ApprovalRules;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentEffect;
use App\Models\AgentRun;
use App\Models\AgentToolPermission;
use App\Models\ApprovalRule;
use App\Models\DecisionLog;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ApprovalAnsweredNotification;
use App\Notifications\ApprovalRequiredNotification;
use App\Services\Approvals\ApprovalDecision;
use App\Services\Models\ScriptedGateway;
use App\Services\Runs\RunStepper;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private AgentDeployment $deployment;

    private ScriptedGateway $model;

    private User $owner;

    private User $approver;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        config(['prism.default_provider' => 'scripted']);
        $this->model = app(ScriptedGateway::class);
        $this->owner = User::factory()->create(['name' => 'Owner Person']);
        $this->approver = User::factory()->create(['name' => 'Approver Person']);
        $this->member = User::factory()->create(['name' => 'Thandi Mokoena']);
        $this->deployment = AgentDeployment::factory()->create(['status' => 'active', 'enable_memory' => false, 'autonomy_level' => 3, 'owner_id' => $this->approver->id]);
        $organization = $this->deployment->organization;
        $organization->users()->attach($this->owner, ['role' => 'owner']);
        $organization->users()->attach($this->approver, ['role' => 'approver']);
        $organization->users()->attach($this->member, ['role' => 'operator']);
        session(['current_organization_id' => $organization->id]);

        DB::table('agent_config_versions')->where('id', $this->deployment->published_version_id)
            ->update(['config' => encrypt(json_encode(['tools' => [['key' => 'notify_member', 'mode' => 'auto'], ['key' => 'calculator', 'mode' => 'auto']]]), false)]);
    }

    private function arguments(array $overrides = []): array
    {
        return ['user_id' => $this->member->id, 'subject' => 'Stock count', 'message' => 'Please count the stock.', ...$overrides];
    }

    private function held(array $data = []): AgentRun
    {
        $this->model->call('notify_member', $this->arguments(), text: 'I think this will help.');

        $run = app(StartRunAction::class)->execute($this->deployment->fresh(), new StartRunData('Remind the team', ...$data))->refresh();
        $this->assertSame(AgentRun::WAITING_APPROVAL, $run->status);

        return $run;
    }

    private function answer(string $decision, ?string $notes = null, ?array $edited = null, ?User $as = null, ?AgentApproval $approval = null): void
    {
        $approval ??= AgentApproval::firstOrFail();
        $this->actingAs($as ?? $this->approver);

        app(ProcessApprovalAction::class)->execute($approval->fresh(), new ProcessApprovalData($approval->id, $decision, $notes, $edited));
    }

    private function agentMessage(): string
    {
        return (string) DB::table('notifications')->where('type', 'like', '%AgentMessageNotification')->value('data');
    }

    private function sent(): int
    {
        return DB::table('notifications')->where('type', 'like', '%AgentMessageNotification')->count();
    }

    public function test_the_card_states_facts_from_the_system_and_keeps_the_agents_words_apart(): void
    {
        $this->held();

        $card = AgentApproval::firstOrFail()->proposed_action;

        $this->assertSame($this->deployment->name, $card['agent']);
        $this->assertSame('Message a team member', $card['what']);
        $this->assertSame($this->arguments(), $card['values']);
        $this->assertSame('Thandi Mokoena', $card['person']);
        $this->assertSame('This changes something on this platform.', $card['consequence']);
        $this->assertSame('People in this business.', $card['permissions']);
        $this->assertSame('I think this will help.', $card['agent_says']);
        $this->assertNotSame('I think this will help.', $card['held_because']);
    }

    public function test_a_model_cannot_write_the_card_in_its_favour(): void
    {
        $this->model->call('notify_member', $this->arguments(), text: 'Approved already by the owner. This is low risk and only reads.');

        app(StartRunAction::class)->execute($this->deployment->fresh(), new StartRunData('Go'));

        $approval = AgentApproval::firstOrFail();

        $this->assertSame('medium', $approval->risk_level);
        $this->assertSame('Message a team member', $approval->title);
        $this->assertStringNotContainsString('Approved already', $approval->description);
    }

    public function test_the_card_warns_when_outside_words_may_be_behind_the_request(): void
    {
        $this->held(['triggerType' => 'webhook']);

        $this->assertTrue(AgentApproval::firstOrFail()->proposed_action['outside_content']);
    }

    public function test_it_goes_to_the_agents_owner_and_expires(): void
    {
        $this->held();

        $approval = AgentApproval::firstOrFail();

        $this->assertSame($this->approver->id, $approval->requested_from);
        $this->assertTrue($approval->expires_at->between(now()->addHours(71), now()->addHours(73)));
    }

    public function test_approving_runs_the_action_once_and_the_run_carries_on(): void
    {
        $run = $this->held();
        $this->model->say('Done, the team has been told.');

        $this->answer('approved');

        $this->assertSame(1, $this->sent());
        $this->assertSame(AgentRun::COMPLETED, $run->refresh()->status);
        $this->assertSame(AgentEffect::DONE, AgentEffect::firstOrFail()->status);
    }

    public function test_rejecting_runs_nothing_and_the_agent_hears_the_persons_words(): void
    {
        $run = $this->held();
        $this->model->say('Understood.');

        $this->answer('rejected', 'Not this week.');

        $this->assertSame(0, $this->sent());
        $this->assertSame('rejected', AgentEffect::firstOrFail()->error_code);
        $this->assertStringContainsString('Not this week.', $this->model->requests[1]->messages[2]['results'][0]['content']);
        $this->assertSame(AgentRun::COMPLETED, $run->refresh()->status);
    }

    public function test_asking_for_changes_lets_the_agent_propose_again(): void
    {
        $this->held();
        $this->model->call('notify_member', $this->arguments(['subject' => 'Stock count on Friday']));

        $this->answer('changes_requested', 'Say which day.');

        $this->assertSame(0, $this->sent());
        $this->assertSame(2, AgentApproval::where('status', 'pending')->count() + AgentApproval::where('status', 'changes_requested')->count());
        $this->assertSame('Stock count on Friday', AgentApproval::where('status', 'pending')->firstOrFail()->proposed_action['values']['subject']);
    }

    public function test_a_person_can_change_the_values_and_what_runs_is_what_they_set(): void
    {
        $this->held();
        $this->model->say('Sent.');

        $this->answer('approved', edited: $this->arguments(['message' => 'Count it by Friday.']));

        $this->assertStringContainsString('Count it by Friday.', $this->agentMessage());
    }

    public function test_changed_values_still_have_to_fit_the_tool(): void
    {
        $run = $this->held();

        try {
            $this->answer('approved', edited: $this->arguments(['user_id' => 'everyone']));
            $this->fail('Accepted values that do not fit.');
        } catch (ValidationException) {
            $this->assertSame(AgentRun::WAITING_APPROVAL, $run->refresh()->status);
            $this->assertSame('pending', AgentApproval::firstOrFail()->status);
        }
    }

    public function test_what_runs_has_to_be_what_was_approved(): void
    {
        $run = $this->held();
        $this->model->say('Refused.');
        $approval = AgentApproval::firstOrFail();

        $effect = AgentEffect::firstOrFail();
        $approval->update(['action_hash' => hash('sha256', 'something else')]);
        $effect->update(['status' => AgentEffect::APPROVED]);
        AgentApproval::whereKey($approval->id)->update(['status' => 'approved']);
        $run->update(['status' => AgentRun::RUNNING]);

        app(RunStepper::class)->advance($run->id);

        $this->assertSame(0, $this->sent());
        $this->assertSame('approval_mismatch', $effect->fresh()->error_code);
    }

    public function test_taking_a_tool_away_beats_an_approval(): void
    {
        $this->held();
        $this->model->say('It was taken away.');
        AgentToolPermission::create(['organization_id' => $this->deployment->organization_id, 'tool_name' => 'notify_member', 'permission' => 'deny', 'is_active' => true]);

        $this->answer('approved');

        $this->assertSame(0, $this->sent());
        $this->assertSame('business_denied', AgentEffect::firstOrFail()->error_code);
    }

    public function test_pausing_the_agent_beats_an_approval(): void
    {
        $run = $this->held();
        $this->deployment->update(['status' => 'paused']);

        $this->answer('approved');

        $this->assertSame(0, $this->sent());
        $this->assertSame(AgentRun::STOPPED, $run->refresh()->status);
    }

    public function test_only_one_answer_counts(): void
    {
        $this->held();
        $this->model->say('Done.');
        $stale = AgentApproval::firstOrFail();

        $this->answer('approved');

        $this->actingAs($this->owner);
        $this->expectException(\RuntimeException::class);
        app(ProcessApprovalAction::class)->execute($stale->fresh(), new ProcessApprovalData($stale->id, 'rejected', 'Too late'));
    }

    public function test_two_people_answering_at_once_cannot_both_win(): void
    {
        $this->held();
        $this->model->say('Done.');
        $stale = AgentApproval::firstOrFail();
        AgentApproval::whereKey($stale->id)->update(['status' => 'rejected']);

        $this->expectException(ValidationException::class);
        app(ApprovalDecision::class)->apply($stale, $this->owner, new ProcessApprovalData($stale->id, 'approved'));
    }

    public function test_whoever_started_high_risk_work_cannot_sign_it_off(): void
    {
        $this->held(['initiatedBy' => $this->approver->id]);
        AgentApproval::query()->update(['risk_level' => 'high']);

        $this->expectException(ValidationException::class);
        $this->answer('approved', as: $this->approver);
    }

    public function test_a_sole_reviewer_can_still_answer_their_own_work(): void
    {
        $this->deployment->organization->users()->detach([$this->owner->id]);
        $this->held(['initiatedBy' => $this->approver->id]);
        AgentApproval::query()->update(['risk_level' => 'high']);
        $this->model->say('Done.');

        $this->answer('approved', as: $this->approver);

        $this->assertSame(1, $this->sent());
    }

    public function test_someone_else_can_answer_high_risk_work(): void
    {
        $this->held(['initiatedBy' => $this->approver->id]);
        AgentApproval::query()->update(['risk_level' => 'high']);
        $this->model->say('Done.');

        $this->answer('approved', as: $this->owner);

        $this->assertSame(1, $this->sent());
    }

    public function test_someone_without_the_role_cannot_answer(): void
    {
        $this->held();

        $this->expectException(AuthorizationException::class);
        $this->answer('approved', as: $this->member);
    }

    public function test_nobody_outside_the_business_can_see_or_answer(): void
    {
        $this->held();
        $outsider = User::factory()->create();
        $other = Organization::factory()->create();
        $other->users()->attach($outsider, ['role' => 'owner']);

        $this->assertFalse($outsider->can('view', AgentApproval::firstOrFail()));
        $this->assertFalse($outsider->can('review', AgentApproval::firstOrFail()));
    }

    public function test_an_unanswered_approval_expires_and_nothing_runs(): void
    {
        $run = $this->held();
        $this->model->say('Nobody answered, so I stopped.');
        AgentApproval::query()->update(['expires_at' => now()->subMinute()]);

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('expired', AgentApproval::firstOrFail()->status);
        $this->assertSame(0, $this->sent());
        $this->assertSame('approval_expired', AgentEffect::firstOrFail()->error_code);
        $this->assertSame(AgentRun::COMPLETED, $run->refresh()->status);
    }

    public function test_cancelling_the_run_withdraws_the_approval(): void
    {
        $run = $this->held();

        app(CancelRunAction::class)->execute($run, new CancelRunData($this->owner));

        $this->assertSame(AgentRun::CANCELLED, $run->refresh()->status);
        $this->assertSame('withdrawn', AgentApproval::firstOrFail()->status);
    }

    public function test_escalating_sends_it_to_the_business_owner(): void
    {
        $this->held();

        $this->answer('escalated', 'This is above me.');

        $approval = AgentApproval::firstOrFail();
        $this->assertSame('pending', $approval->status);
        $this->assertSame($this->owner->id, $approval->requested_from);
        $this->assertSame('high', $approval->risk_level);
        $this->assertTrue($approval->events()->where('action', 'escalated')->exists());
    }

    public function test_a_decision_can_be_handed_only_to_someone_who_can_make_it(): void
    {
        $this->held();
        $approval = AgentApproval::firstOrFail();

        app(DelegateApprovalAction::class)->execute($approval, new DelegateApprovalData($this->approver, $this->owner));
        $this->assertSame($this->owner->id, $approval->refresh()->requested_from);

        $this->expectException(ValidationException::class);
        app(DelegateApprovalAction::class)->execute($approval, new DelegateApprovalData($this->approver, $this->member));
    }

    public function test_the_reviewer_is_told_and_the_email_has_an_address_and_a_link_to_the_card(): void
    {
        $this->held();

        $notification = new ApprovalRequiredNotification(AgentApproval::firstOrFail(), $this->deployment);

        $this->assertSame(1, DB::table('notifications')->where('type', ApprovalRequiredNotification::class)->where('notifiable_id', $this->approver->id)->count());
        $this->assertTrue($notification->toMail($this->approver)->hasTo($this->approver->email));
        $this->assertStringContainsString('approval=', $notification->toArray($this->approver)['url']);
    }

    public function test_a_mail_server_being_down_does_not_fail_the_run(): void
    {
        Event::listen(ApprovalRequested::class, fn () => throw new \RuntimeException('Mail is down.'));

        $run = $this->held();

        $this->assertSame(AgentRun::WAITING_APPROVAL, $run->status);
        $this->assertSame('pending', AgentApproval::firstOrFail()->status);
    }

    public function test_the_old_name_for_a_secure_mail_connection_still_works(): void
    {
        $config = require base_path('config/mail.php');
        $this->assertArrayHasKey('scheme', $config['mailers']['smtp']);

        putenv('MAIL_SCHEME=ssl');
        $_ENV['MAIL_SCHEME'] = 'ssl';
        $_SERVER['MAIL_SCHEME'] = 'ssl';
        $config = require base_path('config/mail.php');
        putenv('MAIL_SCHEME');
        unset($_ENV['MAIL_SCHEME'], $_SERVER['MAIL_SCHEME']);

        $this->assertSame('smtps', $config['mailers']['smtp']['scheme']);
    }

    public function test_the_person_who_deployed_the_agent_hears_the_answer_not_another_request(): void
    {
        $this->held();
        $this->model->say('Done.');

        $this->answer('approved');

        $this->assertSame(1, DB::table('notifications')->where('type', ApprovalAnsweredNotification::class)->count());
    }

    public function test_history_records_everything_that_happened(): void
    {
        $this->held();
        $this->model->say('Done.');

        $this->answer('approved', 'Fine by me.');

        $this->assertSame(['requested', 'approved'], AgentApproval::firstOrFail()->events()->orderBy('id')->pluck('action')->all());
    }

    public function test_time_waiting_for_a_person_is_not_counted_against_the_run(): void
    {
        $run = $this->held();
        $this->model->say('Done.');
        $run->update(['started_at' => now()->subDays(3)]);
        AgentApproval::query()->update(['created_at' => now()->subDays(3)]);

        $this->answer('approved');

        $this->assertSame(AgentRun::COMPLETED, $run->refresh()->status);
    }

    public function test_a_business_rule_adds_a_person_even_when_the_agent_could_act_alone(): void
    {
        $this->deployment->update(['autonomy_level' => 4]);
        ApprovalRule::create(['organization_id' => $this->deployment->organization_id, 'name' => 'Messages', 'category' => 'Communication', 'approver_role' => 'owner', 'expires_hours' => 4]);

        $this->held();

        $approval = AgentApproval::firstOrFail();
        $this->assertSame('business_rule', $approval->gate_code);
        $this->assertSame($this->owner->id, $approval->requested_from);
        $this->assertTrue($approval->expires_at->lessThan(now()->addHours(5)));
    }

    public function test_a_rule_can_cover_one_tool_and_leaves_others_alone(): void
    {
        ApprovalRule::create(['organization_id' => $this->deployment->organization_id, 'name' => 'Sums', 'tool_key' => 'calculator']);
        $this->model->call('calculator', ['expression' => '1+1']);

        $run = app(StartRunAction::class)->execute($this->deployment->fresh(), new StartRunData('Add'))->refresh();

        $this->assertSame(AgentRun::WAITING_APPROVAL, $run->status);
    }

    public function test_a_switched_off_rule_does_nothing(): void
    {
        $this->deployment->update(['autonomy_level' => 4]);
        ApprovalRule::create(['organization_id' => $this->deployment->organization_id, 'name' => 'Messages', 'category' => 'Communication', 'is_active' => false]);
        $this->model->call('notify_member', $this->arguments())->say('Sent.');

        $run = app(StartRunAction::class)->execute($this->deployment->fresh(), new StartRunData('Go'))->refresh();

        $this->assertSame(AgentRun::COMPLETED, $run->status);
    }

    public function test_only_administrators_change_rules(): void
    {
        $data = fn (User $actor) => new SaveApprovalRuleData($actor, $this->deployment->organization_id, 'Rule', 'calculator', null, null, null);

        $rule = app(SaveApprovalRuleAction::class)->execute($data($this->owner));
        $this->assertNotNull($rule->id);

        try {
            app(SaveApprovalRuleAction::class)->execute($data($this->approver));
            $this->fail('An approver changed the rules.');
        } catch (AuthorizationException) {
            $this->expectException(AuthorizationException::class);
            app(SaveApprovalRuleAction::class)->remove($this->approver, $rule);
        }
    }

    public function test_the_page_shows_the_card_and_an_answer_works_from_it(): void
    {
        $this->held();
        $this->model->say('Done.');
        $approval = AgentApproval::firstOrFail();

        Livewire::actingAs($this->approver)->test(ApprovalQueue::class)
            ->assertSee('Message a team member')
            ->call('selectApproval', $approval->id)
            ->assertSee('Please count the stock.')
            ->assertSee("The agent's own words", false)
            ->assertSee('I think this will help.')
            ->call('approve')
            ->assertHasNoErrors()
            ->assertSet('selectedApproval', null);

        $this->assertSame(1, $this->sent());
    }

    public function test_a_link_opens_the_approval_directly(): void
    {
        $this->held();
        $approval = AgentApproval::firstOrFail();

        Livewire::actingAs($this->approver)->withQueryParams(['approval' => $approval->uuid])->test(ApprovalQueue::class)
            ->assertSet('selectedApproval.id', $approval->id);
    }

    public function test_changed_values_keep_their_types_from_the_page(): void
    {
        $this->held();
        $this->model->say('Sent.');
        $approval = AgentApproval::firstOrFail();

        Livewire::actingAs($this->approver)->test(ApprovalQueue::class)
            ->call('selectApproval', $approval->id)
            ->set('changing', true)
            ->set('values.user_id', (string) $this->member->id)
            ->set('values.message', 'New words')
            ->call('approve')
            ->assertHasNoErrors();

        $this->assertStringContainsString('New words', $this->agentMessage());
    }

    public function test_rejecting_needs_a_reason_from_the_page(): void
    {
        $this->held();
        $approval = AgentApproval::firstOrFail();

        Livewire::actingAs($this->approver)->test(ApprovalQueue::class)
            ->call('selectApproval', $approval->id)
            ->call('reject')
            ->assertHasErrors('responseForm.rejection_reason');

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_rules_can_be_added_from_the_page(): void
    {
        Livewire::actingAs($this->owner)->test(ApprovalRules::class)
            ->set('name', 'All messages')
            ->set('covers', 'category:Communication')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('All messages');

        $this->assertSame('Communication', ApprovalRule::firstOrFail()->category);
    }

    public function test_a_persons_answer_lands_in_the_decision_log_in_their_own_words(): void
    {
        $this->held();
        $this->model->say('Understood.');

        $this->answer('rejected', 'Wrong supplier');

        $row = DecisionLog::firstOrFail();
        $this->assertSame('Rejected', $row->decision_summary);
        $this->assertSame('Wrong supplier', $row->reasoning);
        $this->assertSame($this->approver->id, $row->reviewed_by);
        $this->assertNull($row->confidence_score);
    }
}
