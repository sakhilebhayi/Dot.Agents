<?php

namespace Tests\Feature\Agents;

use App\Livewire\Organizations\ApiKeyManager;
use App\Models\Agent;
use App\Models\AgentDeployment;
use App\Models\AgentMemory;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Governance\AuditChain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use SQLite3;
use Tests\TestCase;

class ReadinessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['consent_records' => ['platform_terms' => ['accepted_at' => now()->toISOString()]]]);
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->organization->users()->attach($this->owner, ['role' => 'owner']);
        session(['current_organization_id' => $this->organization->id]);
    }

    private function entry(string $event = 'test.event'): AuditLog
    {
        return AuditLog::create(['organization_id' => $this->organization->id, 'event' => $event, 'event_category' => 'test', 'description' => 'Something happened', 'risk_level' => 'low']);
    }

    public function test_entries_are_sealed_into_a_chain_per_business(): void
    {
        $this->entry();
        $this->entry();

        $this->assertSame(2, app(AuditChain::class)->seal());
        $rows = DB::table('audit_logs')->orderBy('id')->get();

        $this->assertNull($rows[0]->previous_hash);
        $this->assertSame($rows[0]->entry_hash, $rows[1]->previous_hash);
        $this->assertSame([], app(AuditChain::class)->verify());
    }

    public function test_changing_a_sealed_entry_shows(): void
    {
        $entry = $this->entry();
        app(AuditChain::class)->seal();

        DB::table('audit_logs')->where('id', $entry->id)->update(['description' => 'Nothing to see here']);

        $this->artisan('agents:verify-audit')->assertFailed();
    }

    public function test_removing_a_sealed_entry_shows(): void
    {
        $this->entry();
        $middle = $this->entry();
        $this->entry();
        app(AuditChain::class)->seal();

        DB::table('audit_logs')->where('id', $middle->id)->delete();

        $this->artisan('agents:verify-audit')->assertFailed();
    }

    public function test_an_approved_retention_purge_does_not_read_as_tampering(): void
    {
        $first = $this->entry();
        $this->entry();
        app(AuditChain::class)->seal();
        DB::table('audit_logs')->where('id', $first->id)->delete();

        $this->artisan('agents:verify-audit')->assertFailed();

        RetentionPurgeProposal::create(['model_class' => AuditLog::class, 'eligible_count' => 1, 'retention_summary' => 'Old', 'status' => 'approved']);

        $this->artisan('agents:verify-audit')->assertSuccessful();
    }

    public function test_new_entries_carry_on_the_chain_across_seals(): void
    {
        $this->entry();
        app(AuditChain::class)->seal();
        $this->entry();
        app(AuditChain::class)->seal();

        $this->artisan('agents:verify-audit')->assertSuccessful();
    }

    public function test_a_chain_is_kept_per_business(): void
    {
        $other = Organization::factory()->create();
        $this->entry();
        AuditLog::create(['organization_id' => $other->id, 'event' => 'x', 'event_category' => 'test', 'description' => 'y', 'risk_level' => 'low']);
        $this->entry();
        app(AuditChain::class)->seal();

        $this->assertSame([], app(AuditChain::class)->verify());
        $this->assertNull(DB::table('audit_logs')->where('organization_id', $other->id)->value('previous_hash'));
    }

    public function test_a_restore_check_passes_a_sound_backup_and_fails_a_bad_one(): void
    {
        $sound = tempnam(sys_get_temp_dir(), 'backup');
        $database = new SQLite3($sound);
        foreach (['migrations', 'users', 'organizations', 'agent_deployments', 'agent_config_versions', 'agent_runs', 'agent_run_steps', 'agent_effects', 'agent_approvals', 'audit_logs'] as $table) {
            $database->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, input TEXT)");
        }
        $database->exec("INSERT INTO agent_runs (input) VALUES ('".encrypt('secret words', false)."')");
        $database->close();

        $this->artisan('agents:verify-restore', ['file' => $sound])->assertSuccessful();

        $bad = tempnam(sys_get_temp_dir(), 'backup');
        $database = new SQLite3($bad);
        $database->exec('CREATE TABLE users (id INTEGER)');
        $database->close();

        $this->artisan('agents:verify-restore', ['file' => $bad])->assertFailed();
    }

    public function test_a_backup_this_server_cannot_read_fails_the_check(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'backup');
        $database = new SQLite3($file);
        foreach (['migrations', 'users', 'organizations', 'agent_deployments', 'agent_config_versions', 'agent_runs', 'agent_run_steps', 'agent_effects', 'agent_approvals', 'audit_logs'] as $table) {
            $database->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, input TEXT)");
        }
        $database->exec("INSERT INTO agent_runs (input) VALUES ('not encrypted with this key')");
        $database->close();

        $this->artisan('agents:verify-restore', ['file' => $file])->assertFailed();
    }

    public function test_no_backup_is_a_failure_not_a_pass(): void
    {
        $this->artisan('agents:verify-restore', ['file' => '/nonexistent/backup.sqlite'])->assertFailed();
    }

    public function test_a_person_can_take_their_data_including_what_agents_kept(): void
    {
        $agent = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);
        AgentMemory::create(['agent_deployment_id' => $agent->id, 'organization_id' => $this->organization->id, 'user_id' => $this->owner->id, 'memory_type' => 'long_term', 'scope' => 'person', 'subject' => 'Lunch', 'content' => 'Prefers early lunch']);

        $response = $this->actingAs($this->owner)->post(route('privacy.export'));

        $response->assertOk();
        $this->assertStringContainsString('Prefers early lunch', $response->streamedContent());
    }

    public function test_erasing_needs_the_email_typed_and_takes_memories_with_it(): void
    {
        $agent = AgentDeployment::factory()->create(['organization_id' => $this->organization->id]);
        AgentMemory::create(['agent_deployment_id' => $agent->id, 'organization_id' => $this->organization->id, 'user_id' => $this->owner->id, 'memory_type' => 'long_term', 'scope' => 'person', 'subject' => 'Lunch', 'content' => 'Prefers early lunch']);
        $email = $this->owner->email;

        $this->actingAs($this->owner)->post(route('privacy.erase'), ['confirm_email' => 'wrong@example.com'])->assertSessionHasErrors('confirm_email');
        $this->assertNull($this->owner->fresh()->erased_at);

        $this->actingAs($this->owner)->post(route('privacy.erase'), ['confirm_email' => $email])->assertRedirect('/');

        $this->assertNotNull($this->owner->fresh()->erased_at);
        $this->assertSame(0, AgentMemory::withoutGlobalScopes()->count());
        $this->assertNull(auth()->guard('web')->user());
        $this->assertSame(0, AuditLog::withoutGlobalScopes()->where('description', 'like', "%{$email}%")->count());
    }

    public function test_every_schedule_the_engine_needs_is_registered(): void
    {
        Artisan::call('schedule:list');
        $list = Artisan::output();

        foreach (['agents:sweep-runs', 'agents:fire-schedules', 'agents:seal-audit', 'agents:forget-expired'] as $command) {
            $this->assertStringContainsString($command, $list);
        }
    }

    public function test_the_deploy_runs_a_worker_for_agent_steps_with_a_lease_longer_than_the_timeout(): void
    {
        $this->assertStringContainsString('--queue=agent-steps', (string) file_get_contents(base_path('.github/workflows/deploy.yml')));
        $this->assertGreaterThan(180, config('queue.connections.agents.retry_after'));
    }

    public function test_the_openai_client_is_gone(): void
    {
        $this->assertStringNotContainsString('openai-php', (string) file_get_contents(base_path('composer.json')));
    }

    public function test_a_backup_with_damaged_pages_fails_the_check(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'backup');
        $database = new SQLite3($file);
        $database->exec('PRAGMA page_size = 4096');
        foreach (['migrations', 'users', 'organizations', 'agent_deployments', 'agent_config_versions', 'agent_runs', 'agent_run_steps', 'agent_effects', 'agent_approvals', 'audit_logs'] as $table) {
            $database->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, input TEXT)");
        }
        $database->exec('CREATE TABLE filler (id INTEGER PRIMARY KEY, words TEXT)');
        $database->exec('CREATE INDEX filler_words ON filler (words)');
        for ($i = 0; $i < 500; $i++) {
            $database->exec("INSERT INTO filler (words) VALUES ('".str_repeat('x', 200).$i."')");
        }
        $database->close();

        $bytes = file_get_contents($file);
        file_put_contents($file, substr_replace($bytes, str_repeat("\xFF", 4096), 4096 * 20, 4096));

        $this->artisan('agents:verify-restore', ['file' => $file])->assertFailed();
        unlink($file);
    }

    public function test_a_webhook_secret_is_not_stored_in_the_clear(): void
    {
        $webhook = Webhook::create(['organization_id' => $this->organization->id, 'created_by' => $this->owner->id, 'name' => 'Out', 'url' => 'https://example.com/hook', 'events' => ['run.finished']]);

        $this->assertNotSame($webhook->secret, DB::table('webhooks')->where('id', $webhook->id)->value('secret'));
        $this->assertSame(64, strlen($webhook->fresh()->secret));
    }

    public function test_the_catalogue_api_leaves_out_figures_nobody_measured(): void
    {
        $agent = Agent::factory()->create(['status' => 'active', 'avg_rating' => 4.9, 'total_deployments' => 900]);
        Sanctum::actingAs($this->owner, ['read']);

        $this->getJson("/api/v1/agents/{$agent->getRouteKey()}")->assertOk()->assertJsonMissingPath('avg_rating')->assertJsonMissingPath('total_deployments');
        $this->getJson('/api/v1/agents')->assertOk()->assertJsonMissingPath('data.0.avg_rating');
    }

    public function test_api_keys_stay_with_their_business(): void
    {
        $other = Organization::factory()->create();
        $other->users()->attach($this->owner, ['role' => 'owner']);
        $theirs = $this->owner->createToken('elsewhere', ['read']);
        $theirs->accessToken->forceFill(['organization_id' => $other->id])->save();

        $page = Livewire::actingAs($this->owner)->test(ApiKeyManager::class);

        $this->assertCount(0, $page->instance()->tokens());

        $page->call('revokeToken', $theirs->accessToken->id);
        $this->assertSame(1, $this->owner->tokens()->count());
    }
}
