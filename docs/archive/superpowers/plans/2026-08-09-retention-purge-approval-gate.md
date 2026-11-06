# Retention Purge Approval Gate + Overdue Approval Expiry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `approvals:expire-overdue` becomes a real command that expires stale `pending` approvals. Separately, `model:prune`'s nightly unattended permanent deletion is replaced by a detect-then-approve flow: a scheduled command proposes each eligible model's purge, and a `platform_admin` approves (the only place deletion now happens) or rejects it on a new governance screen.

**Architecture:** Two independent pieces, built in sequence. Piece 1 (Task 1) is a standalone command with no dependencies. Piece 2 (Tasks 2-4) mirrors this codebase's existing `AgentApproval`/`ProcessApprovalAction`/`ApprovalQueue` architecture exactly: a proposal model, a Gate-authorized Action, a Livewire review screen — built in that dependency order (schema+detection, then the gated action, then the UI that calls it).

**Tech Stack:** Laravel 12 (Actions pattern, Gate-based Policies, Spatie `laravel-permission` roles, `MassPrunable`), Livewire 3, PHPUnit.

## Global Constraints

- `ExpireOverdueApprovals` and `DetectRetentionPurgeCandidates` are both cross-tenant, unattended commands — call `withoutGlobalScope('organization')` explicitly wherever `HasOrganizationScope`-using models are queried, matching every existing `prunable()` method's own convention (the scope auto-disables with no active session in a real scheduled run, but explicit is this codebase's established style, not implicit).
- `RetentionPurgeProposal` and its reviewer authority (`platform_admin`) are platform-wide — no `organization_id` column, no per-org scoping.
- `ProcessRetentionPurgeAction` throws `\RuntimeException` when a proposal is no longer `pending` — matching `ProcessApprovalAction`'s existing error-signaling convention exactly, not a silent no-op.
- `platform_admin` is a real, existing Spatie role (checked today by `AuditLogPolicy`, `SecurityEventPolicy`, `EmergencyKillSwitchAction`) — reused as-is, never provisioned by application code. Tests create it exactly the way `tests/Feature/Actions/Security/EmergencyKillSwitchActionTest.php` already does: `Role::create(['name' => 'platform_admin'])` then `$user->assignRole('platform_admin')`.
- No individual model's own `prunable()` retention window changes — `AuditLog` stays 730 days (`config('audit.retention_days')`), `AgentTask` 90 days, `AgentSession` 60 days (`ended_at`), `AgentSkillExecution` 60 days, `PlatformMegaScorecard` 12 months. This plan only gates *when* already-eligible rows actually get deleted.
- `AgentMessage` does not implement `MassPrunable`/`Prunable` — `DetectRetentionPurgeCandidates` must skip it via `method_exists($instance, 'prunable')`, not error on it, and must not invent a retention policy for it.
- This repo's own `.github/instructions/laravel-boost.instructions.md` states: "Do not create verification scripts or tinker when tests cover that functionality and prove they work." Task 5 relies on the automated test suite only — no manual tinker verification step.
- Every `git add` lists files explicitly, never `-A`/`.` — this repo had pre-existing unrelated uncommitted changes (`application-mark.blade.php`, `consent/show.blade.php`, `layouts/platform.blade.php`, mark images) stashed before this work started.

---

### Task 1: `ExpireOverdueApprovals` command

**Files:**
- Create: `app/Console/Commands/ExpireOverdueApprovals.php`
- Test: `tests/Feature/Console/ExpireOverdueApprovalsCommandTest.php`

**Interfaces:**
- Produces: `approvals:expire-overdue` Artisan command (the exact name `routes/console.php` already schedules).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Console/ExpireOverdueApprovalsCommandTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentSkillApproval;
use App\Models\AgentTask;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireOverdueApprovalsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function agentApproval(string $status, string $expiresAt): AgentApproval
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        $task = AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
        ]);

        return AgentApproval::factory()->create([
            'task_id' => $task->id,
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_an_overdue_pending_agent_approval_becomes_expired(): void
    {
        $approval = $this->agentApproval('pending', now()->subHour()->toDateTimeString());

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('expired', $approval->fresh()->status);
    }

    public function test_a_pending_agent_approval_still_within_its_window_is_untouched(): void
    {
        $approval = $this->agentApproval('pending', now()->addHour()->toDateTimeString());

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_an_already_approved_overdue_agent_approval_is_untouched(): void
    {
        $approval = $this->agentApproval('approved', now()->subHour()->toDateTimeString());

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_an_overdue_pending_skill_approval_becomes_expired(): void
    {
        $skillApproval = AgentSkillApproval::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('expired', $skillApproval->fresh()->status);
    }

    public function test_a_skill_approval_still_within_its_window_is_untouched(): void
    {
        $skillApproval = AgentSkillApproval::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->addHour(),
        ]);

        $this->artisan('approvals:expire-overdue')->assertSuccessful();

        $this->assertSame('pending', $skillApproval->fresh()->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Console/ExpireOverdueApprovalsCommandTest.php`
Expected: FAIL — `approvals:expire-overdue` doesn't exist yet ("Command \"approvals:expire-overdue\" is not defined").

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/ExpireOverdueApprovals.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\AgentApproval;
use App\Models\AgentSkillApproval;
use Illuminate\Console\Command;

/**
 * Expires pending approvals (both AgentApproval and AgentSkillApproval) whose
 * expires_at deadline has passed. Pure status bookkeeping -- no task or
 * deployment side effect, no notification. This is the command
 * routes/console.php has scheduled every 30 minutes all along; it just
 * didn't exist as a class until now.
 */
class ExpireOverdueApprovals extends Command
{
    protected $signature = 'approvals:expire-overdue';

    protected $description = 'Mark pending agent/skill approvals past their expiry deadline as expired.';

    public function handle(): int
    {
        $expiredApprovals = AgentApproval::withoutGlobalScope('organization')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $expiredSkillApprovals = AgentSkillApproval::withoutGlobalScope('organization')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expiredApprovals} agent approval(s) and {$expiredSkillApprovals} skill approval(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Console/ExpireOverdueApprovalsCommandTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes; re-run Step 4 if it reformats anything.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/ExpireOverdueApprovals.php \
  tests/Feature/Console/ExpireOverdueApprovalsCommandTest.php \
  docs/superpowers/plans/2026-08-09-retention-purge-approval-gate.md
git commit -m "$(cat <<'EOF'
feat: implement the missing approvals:expire-overdue command

routes/console.php has scheduled approvals:expire-overdue every 30
minutes all along, but no command class existed anywhere in
app/Console/Commands -- confirmed via full-tree grep during the
platform autonomy audit. Both AgentApproval (whose migration lists
'expired' as a valid status) and AgentSkillApproval (which has a
first-class STATUS_EXPIRED constant and a live isExpired() check)
already anticipated this state; nothing ever set it.

Pure bookkeeping: only status changes, no task/deployment side
effect, no notification.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `RetentionPurgeProposal` + `DetectRetentionPurgeCandidates`

**Files:**
- Create: `database/migrations/2026_08_09_000001_create_retention_purge_proposals_table.php`
- Create: `app/Models/RetentionPurgeProposal.php`
- Create: `app/Console/Commands/DetectRetentionPurgeCandidates.php`
- Test: `tests/Feature/Console/DetectRetentionPurgeCandidatesCommandTest.php`

**Interfaces:**
- Produces: `RetentionPurgeProposal::create(array $attributes)` with fillable `model_class`, `eligible_count`, `retention_summary`, `status`, `reviewed_by`, `reviewed_at`, `reviewer_notes`, `deleted_count`; `retention:detect-purge-candidates` Artisan command.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Console/DetectRetentionPurgeCandidatesCommandTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetectRetentionPurgeCandidatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_model_with_eligible_rows_gets_exactly_one_pending_proposal(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();

        $this->assertDatabaseCount('retention_purge_proposals', 1);
        $proposal = RetentionPurgeProposal::first();
        $this->assertSame(\App\Models\AgentTask::class, $proposal->model_class);
        $this->assertSame(1, $proposal->eligible_count);
        $this->assertSame('pending', $proposal->status);
    }

    public function test_a_model_with_no_eligible_rows_gets_no_proposal(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(1), // not yet past the 90-day window
        ]);

        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();

        $this->assertDatabaseCount('retention_purge_proposals', 0);
    }

    public function test_running_the_command_twice_does_not_duplicate_a_pending_proposal(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('retention:detect-purge-candidates');
        $this->artisan('retention:detect-purge-candidates');

        $this->assertDatabaseCount('retention_purge_proposals', 1);
    }

    public function test_a_rejected_proposal_gets_a_fresh_one_on_the_next_eligible_run(): void
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('retention:detect-purge-candidates');

        $existing = RetentionPurgeProposal::where('model_class', \App\Models\AgentTask::class)->firstOrFail();
        $existing->update(['status' => 'rejected']);

        $this->artisan('retention:detect-purge-candidates');

        $this->assertDatabaseCount('retention_purge_proposals', 2);
        $this->assertSame(
            1,
            RetentionPurgeProposal::where('model_class', \App\Models\AgentTask::class)->where('status', 'pending')->count()
        );
    }

    public function test_the_command_does_not_error_on_agent_message_which_has_no_prunable_method(): void
    {
        $this->artisan('retention:detect-purge-candidates')->assertSuccessful();

        $this->assertDatabaseMissing('retention_purge_proposals', ['model_class' => \App\Models\AgentMessage::class]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Console/DetectRetentionPurgeCandidatesCommandTest.php`
Expected: FAIL — `retention_purge_proposals` table doesn't exist, `retention:detect-purge-candidates` command doesn't exist.

- [ ] **Step 3: Write the migration**

Create `database/migrations/2026_08_09_000001_create_retention_purge_proposals_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_purge_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('model_class');
            $table->unsignedInteger('eligible_count');
            $table->text('retention_summary');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('reviewer_notes')->nullable();
            $table->unsignedInteger('deleted_count')->nullable();
            $table->timestamps();
            $table->index(['status', 'model_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_purge_proposals');
    }
};
```

- [ ] **Step 4: Create the `RetentionPurgeProposal` model**

Create `app/Models/RetentionPurgeProposal.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPurgeProposal extends Model
{
    protected $fillable = [
        'model_class', 'eligible_count', 'retention_summary', 'status',
        'reviewed_by', 'reviewed_at', 'reviewer_notes', 'deleted_count',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

- [ ] **Step 5: Create the `DetectRetentionPurgeCandidates` command**

Create `app/Console/Commands/DetectRetentionPurgeCandidates.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Models\AgentSkillExecution;
use App\Models\AgentTask;
use App\Models\AuditLog;
use App\Models\PlatformMegaScorecard;
use App\Models\RetentionPurgeProposal;
use Illuminate\Console\Command;

/**
 * Detects which configured models currently have rows eligible for pruning
 * under their own already-defined prunable() retention window, and proposes
 * a purge for each -- creating a RetentionPurgeProposal, never deleting
 * anything itself. Real deletion only ever happens in
 * ProcessRetentionPurgeAction, once a platform_admin approves. Intended to
 * run daily (see routes/console.php), replacing the old direct model:prune
 * schedule entry, which deleted these same rows unattended.
 *
 * AgentMessage is deliberately included in the checked list but skipped: it
 * does not implement MassPrunable/Prunable today, and this command does not
 * invent a retention policy for it.
 */
class DetectRetentionPurgeCandidates extends Command
{
    protected $signature = 'retention:detect-purge-candidates';

    protected $description = 'Propose purging models with rows past their own retention window.';

    /** @var array<class-string, string> */
    private const RETENTION_SUMMARIES = [
        AuditLog::class => 'AuditLog rows older than the configured retention_days (default 730 days)',
        AgentTask::class => 'Completed/failed/cancelled AgentTask rows older than 90 days',
        AgentSession::class => 'Ended/abandoned/expired AgentSession rows, ended more than 60 days ago',
        AgentSkillExecution::class => 'Completed/failed/skipped AgentSkillExecution rows older than 60 days',
        PlatformMegaScorecard::class => 'PlatformMegaScorecard rows older than 12 months',
        AgentMessage::class => 'AgentMessage rows (no retention window defined -- skipped)',
    ];

    public function handle(): int
    {
        $proposed = 0;

        foreach (self::RETENTION_SUMMARIES as $modelClass => $summary) {
            $instance = new $modelClass;

            if (! method_exists($instance, 'prunable')) {
                $this->line("Skipping {$modelClass}: no prunable() method defined.");

                continue;
            }

            $eligibleCount = $instance->prunable()->count();

            if ($eligibleCount === 0) {
                continue;
            }

            $hasPendingProposal = RetentionPurgeProposal::where('model_class', $modelClass)
                ->where('status', 'pending')
                ->exists();

            if ($hasPendingProposal) {
                continue;
            }

            RetentionPurgeProposal::create([
                'model_class' => $modelClass,
                'eligible_count' => $eligibleCount,
                'retention_summary' => $summary,
                'status' => 'pending',
            ]);

            $proposed++;
        }

        $this->info("Proposed {$proposed} retention purge(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 6: Run migration**

Run: `php artisan migrate`
Expected: migration runs with no errors.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Console/DetectRetentionPurgeCandidatesCommandTest.php`
Expected: PASS (5 tests)

- [ ] **Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes; re-run Step 7 if it reformats anything.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_08_09_000001_create_retention_purge_proposals_table.php \
  app/Models/RetentionPurgeProposal.php app/Console/Commands/DetectRetentionPurgeCandidates.php \
  tests/Feature/Console/DetectRetentionPurgeCandidatesCommandTest.php \
  docs/superpowers/plans/2026-08-09-retention-purge-approval-gate.md
git commit -m "$(cat <<'EOF'
feat: detect retention-purge candidates instead of deleting directly

New retention:detect-purge-candidates command -- for each of
AuditLog/AgentTask/AgentSession/AgentSkillExecution/
PlatformMegaScorecard, counts rows already eligible under that
model's own existing prunable() retention window and creates a
RetentionPurgeProposal (never deletes). Skips a model that already
has an open pending proposal, so daily re-runs don't duplicate.

AgentMessage is checked but skipped (no prunable() method exists for
it today) rather than assigning it an invented retention policy.

This is the detection half of replacing model:prune's unattended
nightly permanent deletion -- the real delete only happens in
ProcessRetentionPurgeAction (next task), once a platform_admin
approves.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `RetentionPurgeProposalPolicy` + `ProcessRetentionPurgeAction`

**Files:**
- Create: `app/Policies/RetentionPurgeProposalPolicy.php`
- Create: `app/DTOs/Governance/ProcessRetentionPurgeData.php`
- Create: `app/Events/RetentionPurgeProcessed.php`
- Create: `app/Actions/Governance/ProcessRetentionPurgeAction.php`
- Test: `tests/Feature/Governance/RetentionPurgeWorkflowTest.php`

**Interfaces:**
- Consumes: `RetentionPurgeProposal` (Task 2).
- Produces: `RetentionPurgeProposalPolicy::review(User $user): bool`; `ProcessRetentionPurgeData::__construct(int $proposalId, string $decision, ?string $reviewerNotes = null)`; `RetentionPurgeProcessed::__construct(public RetentionPurgeProposal $proposal)`; `ProcessRetentionPurgeAction::execute(RetentionPurgeProposal $proposal, ProcessRetentionPurgeData $data): RetentionPurgeProposal`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Governance/RetentionPurgeWorkflowTest.php`:

```php
<?php

namespace Tests\Feature\Governance;

use App\Actions\Governance\ProcessRetentionPurgeAction;
use App\DTOs\Governance\ProcessRetentionPurgeData;
use App\Events\RetentionPurgeProcessed;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RetentionPurgeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleTaskProposal(): RetentionPurgeProposal
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        return RetentionPurgeProposal::create([
            'model_class' => AgentTask::class,
            'eligible_count' => 1,
            'retention_summary' => 'Completed AgentTask rows older than 90 days',
            'status' => 'pending',
        ]);
    }

    private function platformAdmin(): User
    {
        if (! Role::where('name', 'platform_admin')->exists()) {
            Role::create(['name' => 'platform_admin']);
        }

        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');

        return $admin;
    }

    public function test_platform_admin_can_approve_and_the_rows_are_actually_deleted(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $result = app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved', 'Reviewed, safe to purge.'),
        );

        $this->assertSame('approved', $result->status);
        $this->assertSame($admin->id, $result->reviewed_by);
        $this->assertSame(1, $result->deleted_count);
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_platform_admin_can_reject_and_the_rows_are_untouched(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        $result = app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'rejected', 'Need these for an ongoing investigation.'),
        );

        $this->assertSame('rejected', $result->status);
        $this->assertSame('Need these for an ongoing investigation.', $result->reviewer_notes);
        $this->assertDatabaseCount('agent_tasks', 1);
    }

    public function test_processing_fires_the_event(): void
    {
        Event::fake([RetentionPurgeProcessed::class]);
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved'),
        );

        Event::assertDispatched(RetentionPurgeProcessed::class);
    }

    public function test_processing_creates_an_audit_log(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved', 'Approved.'),
        );

        $this->assertDatabaseHas('audit_logs', ['event' => 'retention_purge.approved']);
    }

    public function test_cannot_process_an_already_reviewed_proposal(): void
    {
        $this->expectException(\RuntimeException::class);

        $proposal = $this->eligibleTaskProposal();
        $proposal->update(['status' => 'approved']);
        $admin = $this->platformAdmin();
        $this->actingAs($admin);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved'),
        );
    }

    public function test_a_user_without_platform_admin_role_is_denied(): void
    {
        $this->expectException(AuthorizationException::class);

        $proposal = $this->eligibleTaskProposal();
        $regularUser = User::factory()->create();
        $this->actingAs($regularUser);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved'),
        );
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Governance/RetentionPurgeWorkflowTest.php`
Expected: FAIL — `App\Actions\Governance\ProcessRetentionPurgeAction`, `App\DTOs\Governance\ProcessRetentionPurgeData`, `App\Events\RetentionPurgeProcessed` don't exist yet.

- [ ] **Step 3: Create the policy**

Create `app/Policies/RetentionPurgeProposalPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\RetentionPurgeProposal;
use App\Models\User;

class RetentionPurgeProposalPolicy
{
    /**
     * Retention purges are cross-organization, compliance-adjacent
     * infrastructure decisions -- gated by the same platform_admin role
     * AuditLogPolicy already uses to restrict who can even read audit logs.
     * No per-organization admin/manager role applies here; this is
     * deliberately narrower than that.
     */
    public function review(User $user, RetentionPurgeProposal $proposal): bool
    {
        return $user->hasAnyRole(['platform_admin']);
    }
}
```

- [ ] **Step 4: Create the DTO**

Create `app/DTOs/Governance/ProcessRetentionPurgeData.php`:

```php
<?php

namespace App\DTOs\Governance;

readonly class ProcessRetentionPurgeData
{
    public function __construct(
        public int $proposalId,
        public string $decision,
        public ?string $reviewerNotes = null,
    ) {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new \InvalidArgumentException("Invalid decision: {$decision}");
        }
    }
}
```

- [ ] **Step 5: Create the event**

Create `app/Events/RetentionPurgeProcessed.php`:

```php
<?php

namespace App\Events;

use App\Models\RetentionPurgeProposal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RetentionPurgeProcessed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly RetentionPurgeProposal $proposal
    ) {}
}
```

- [ ] **Step 6: Create the Action**

Create `app/Actions/Governance/ProcessRetentionPurgeAction.php`:

```php
<?php

namespace App\Actions\Governance;

use App\DTOs\Governance\ProcessRetentionPurgeData;
use App\Events\RetentionPurgeProcessed;
use App\Models\RetentionPurgeProposal;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProcessRetentionPurgeAction
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Process a human decision on a pending RetentionPurgeProposal.
     *
     * On approval, this is the only place any of the configured models'
     * rows are actually deleted -- via that model's own already-defined
     * prunable() query and Laravel's real MassPrunable::prune().
     *
     * @throws \RuntimeException When the proposal is not 'pending'.
     * @throws \Illuminate\Auth\Access\AuthorizationException When the actor lacks 'review' permission.
     */
    public function execute(RetentionPurgeProposal $proposal, ProcessRetentionPurgeData $data): RetentionPurgeProposal
    {
        Gate::authorize('review', $proposal);

        if ($proposal->status !== 'pending') {
            throw new \RuntimeException("Retention purge proposal #{$proposal->id} is already {$proposal->status}.");
        }

        $deletedCount = null;

        if ($data->decision === 'approved') {
            $modelClass = $proposal->model_class;
            $deletedCount = $modelClass::prune();
        }

        $proposal->update([
            'status' => $data->decision,
            'reviewer_notes' => $data->reviewerNotes,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'deleted_count' => $deletedCount,
        ]);

        $this->auditService->logUserAction(
            event: "retention_purge.{$data->decision}",
            description: "Retention purge proposal #{$proposal->id} ({$proposal->model_class}) {$data->decision}",
            data: ['deleted_count' => $deletedCount],
            subject: $proposal,
        );

        event(new RetentionPurgeProcessed($proposal));

        return $proposal->refresh();
    }
}
```

Note: `$modelClass::prune()` calls Laravel's real `Illuminate\Database\Eloquent\MassPrunable::prune()` method directly on the class — it internally re-runs that model's own `prunable()` query and performs the mass delete, returning the number of rows deleted. This is the exact same mechanism `php artisan model:prune` itself calls; the only change is *when* and *who authorizes* it runs, never *how* it deletes.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Governance/RetentionPurgeWorkflowTest.php`
Expected: PASS (6 tests)

- [ ] **Step 8: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes; re-run Step 7 if it reformats anything.

- [ ] **Step 9: Commit**

```bash
git add app/Policies/RetentionPurgeProposalPolicy.php app/DTOs/Governance/ProcessRetentionPurgeData.php \
  app/Events/RetentionPurgeProcessed.php app/Actions/Governance/ProcessRetentionPurgeAction.php \
  tests/Feature/Governance/RetentionPurgeWorkflowTest.php \
  docs/superpowers/plans/2026-08-09-retention-purge-approval-gate.md
git commit -m "$(cat <<'EOF'
feat: gate retention purge behind platform_admin approval

ProcessRetentionPurgeAction mirrors ProcessApprovalAction's exact
shape: Gate-authorized, throws on an already-reviewed proposal (not a
silent no-op), logs via AuditService, dispatches an event. Approving
is the only place any configured model's rows actually get deleted
now -- via that model's own prunable() query and Laravel's real
MassPrunable::prune(), the same mechanism model:prune itself used,
just no longer unattended.

RetentionPurgeProposalPolicy::review() reuses the real, existing
platform_admin role -- the same one AuditLogPolicy already gates
*reading* audit logs behind, now also gating whether they get purged.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: `RetentionPurgeQueue` Livewire component + route + schedule swap

**Files:**
- Create: `app/Livewire/Governance/RetentionPurgeQueue.php`
- Create: `resources/views/livewire/governance/retention-purge-queue.blade.php`
- Create: `resources/views/governance/retention-purges.blade.php`
- Modify: `routes/web.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Livewire/RetentionPurgeQueueTest.php`

**Interfaces:**
- Consumes: `RetentionPurgeProposal` (Task 2), `ProcessRetentionPurgeAction`/`ProcessRetentionPurgeData` (Task 3).
- Produces: `RetentionPurgeQueue::approve(int $id)`, `reject(int $id, string $reviewerNotes)`; `governance.retention-purges` route.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Livewire/RetentionPurgeQueueTest.php`:

```php
<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Governance\RetentionPurgeQueue;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\Organization;
use App\Models\RetentionPurgeProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RetentionPurgeQueueTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleTaskProposal(): RetentionPurgeProposal
    {
        $org = Organization::factory()->create();
        $deployment = AgentDeployment::factory()->create(['organization_id' => $org->id]);
        AgentTask::factory()->create([
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $org->id,
            'status' => 'completed',
            'created_at' => now()->subDays(100),
        ]);

        return RetentionPurgeProposal::create([
            'model_class' => AgentTask::class,
            'eligible_count' => 1,
            'retention_summary' => 'Completed AgentTask rows older than 90 days',
            'status' => 'pending',
        ]);
    }

    private function platformAdmin(): User
    {
        if (! Role::where('name', 'platform_admin')->exists()) {
            Role::create(['name' => 'platform_admin']);
        }

        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');

        return $admin;
    }

    public function test_platform_admin_can_approve_via_the_component(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();

        Livewire::actingAs($admin)
            ->test(RetentionPurgeQueue::class)
            ->call('approve', $proposal->id);

        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertDatabaseCount('agent_tasks', 0);
    }

    public function test_platform_admin_can_reject_via_the_component(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $admin = $this->platformAdmin();

        Livewire::actingAs($admin)
            ->test(RetentionPurgeQueue::class)
            ->set('reviewerNotes', 'Not yet.')
            ->call('reject', $proposal->id);

        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame('Not yet.', $proposal->fresh()->reviewer_notes);
        $this->assertDatabaseCount('agent_tasks', 1);
    }

    public function test_a_regular_user_is_forbidden(): void
    {
        $proposal = $this->eligibleTaskProposal();
        $regularUser = User::factory()->create();

        Livewire::actingAs($regularUser)
            ->test(RetentionPurgeQueue::class)
            ->call('approve', $proposal->id)
            ->assertForbidden();
    }

    public function test_retention_purges_route_requires_authentication(): void
    {
        $this->get('/governance/retention-purges')->assertRedirect('/login');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Livewire/RetentionPurgeQueueTest.php`
Expected: FAIL — `App\Livewire\Governance\RetentionPurgeQueue` and the `/governance/retention-purges` route don't exist yet.

- [ ] **Step 3: Create the Livewire component**

Create `app/Livewire/Governance/RetentionPurgeQueue.php`:

```php
<?php

namespace App\Livewire\Governance;

use App\Actions\Governance\ProcessRetentionPurgeAction;
use App\DTOs\Governance\ProcessRetentionPurgeData;
use App\Models\RetentionPurgeProposal;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RetentionPurgeQueue extends Component
{
    public string $reviewerNotes = '';

    #[Computed]
    public function proposals()
    {
        return RetentionPurgeProposal::where('status', 'pending')
            ->orderBy('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return RetentionPurgeProposal::where('status', 'pending')->count();
    }

    public function approve(int $id): void
    {
        $proposal = RetentionPurgeProposal::findOrFail($id);

        // SECURITY: $id is a Livewire method argument, fully attacker-controlled.
        abort_unless(auth()->user()->can('review', $proposal), 403);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'approved', $this->reviewerNotes ?: null),
        );

        $this->reviewerNotes = '';
        unset($this->proposals, $this->pendingCount);
        session()->flash('success', 'Retention purge approved.');
    }

    public function reject(int $id): void
    {
        $proposal = RetentionPurgeProposal::findOrFail($id);

        abort_unless(auth()->user()->can('review', $proposal), 403);

        app(ProcessRetentionPurgeAction::class)->execute(
            $proposal,
            new ProcessRetentionPurgeData($proposal->id, 'rejected', $this->reviewerNotes ?: null),
        );

        $this->reviewerNotes = '';
        unset($this->proposals, $this->pendingCount);
        session()->flash('success', 'Retention purge rejected.');
    }

    public function render()
    {
        return view('livewire.governance.retention-purge-queue');
    }
}
```

- [ ] **Step 4: Create the component view**

Read `resources/views/livewire/governance/approval-queue.blade.php` first to match its structure and Tailwind class conventions exactly (already read during plan-writing — it's a Tailwind-styled table/list with `wire:click` action buttons). Create `resources/views/livewire/governance/retention-purge-queue.blade.php`:

```blade
<div>
    @if (session('success'))
        <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($this->proposals->isEmpty())
        <div class="rounded-md border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
            No retention purges awaiting review.
        </div>
    @endif

    @foreach ($this->proposals as $proposal)
        <div class="mb-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-gray-900">{{ class_basename($proposal->model_class) }}</p>
                    <p class="text-sm text-gray-500">{{ $proposal->retention_summary }}</p>
                </div>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                    {{ $proposal->eligible_count }} row(s) eligible
                </span>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <input type="text" wire:model="reviewerNotes" placeholder="Reviewer notes (optional)"
                    class="flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <button wire:click="approve({{ $proposal->id }})" wire:confirm="Permanently delete {{ $proposal->eligible_count }} {{ class_basename($proposal->model_class) }} row(s)?"
                    class="rounded-md bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                    Approve Purge
                </button>
                <button wire:click="reject({{ $proposal->id }})"
                    class="rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700">
                    Reject
                </button>
            </div>
        </div>
    @endforeach

    {{ $this->proposals->links() }}
</div>
```

- [ ] **Step 5: Create the page view**

Create `resources/views/governance/retention-purges.blade.php`:

```blade
<x-app-layout>
    <div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-gray-900">Retention Purge Queue</h1>
        <p class="mt-1 text-sm text-gray-500">
            Models with rows past their own retention window, awaiting approval to permanently delete.
        </p>

        <div class="mt-6">
            <livewire:governance.retention-purge-queue />
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add the route**

In `routes/web.php`, add inside the existing `governance` route group, after the `decisions` route:

```php
        Route::get('/retention-purges', fn () => view('governance.retention-purges'))->name('retention-purges');
```

- [ ] **Step 7: Swap the schedule**

In `routes/console.php`, replace the `model:prune` schedule entry:

```php
// Prune old audit logs and agent messages older than 90 days — runs nightly at 02:00
Schedule::command('model:prune', [
    '--model' => [
        AuditLog::class,
        AgentMessage::class,
        AgentTask::class,
        AgentSession::class,
        AgentSkillExecution::class,
        PlatformMegaScorecard::class,
    ],
])->dailyAt('02:00')->onOneServer();
```

with:

```php
// Detects models with rows past their own retention window and proposes a
// purge for each — never deletes directly. A platform_admin must approve
// on the governance/retention-purges screen before ProcessRetentionPurgeAction
// actually deletes anything. Replaces the old direct model:prune schedule,
// which permanently deleted these same rows unattended every night.
Schedule::command('retention:detect-purge-candidates')->dailyAt('02:00')->onOneServer();
```

The now-unused `AuditLog`/`AgentMessage`/`AgentTask`/`AgentSession`/`AgentSkillExecution`/`PlatformMegaScorecard` `use` imports at the top of `routes/console.php` can stay if any other line in the file still references them; otherwise remove any that become unused after this change (check with a read of the full file before editing).

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Livewire/RetentionPurgeQueueTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes; re-run Step 8 if it reformats anything.

- [ ] **Step 10: Commit**

```bash
git add app/Livewire/Governance/RetentionPurgeQueue.php \
  resources/views/livewire/governance/retention-purge-queue.blade.php \
  resources/views/governance/retention-purges.blade.php \
  routes/web.php routes/console.php \
  tests/Feature/Livewire/RetentionPurgeQueueTest.php
git commit -m "$(cat <<'EOF'
feat: retention purge review screen + remove unattended model:prune

New /governance/retention-purges route + RetentionPurgeQueue Livewire
component, mirroring ApprovalQueue's shape: lists pending
RetentionPurgeProposal rows, lets a platform_admin approve (rows
actually deleted now) or reject (kept) each one.

routes/console.php no longer schedules model:prune directly --
replaced by retention:detect-purge-candidates, which only ever
proposes. This closes the audit's flagged violation: permanent
deletion (brain.autonomy.md's own named Level 3 example) was running
unattended every night; it now requires platform_admin sign-off.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Full regression

**Files:** none new — verification only.

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test --compact`
Expected: 0 failures — confirms Tasks 1-4 didn't break `ApprovalWorkflowTest`, `ApprovalQueueTest`, `ProcessApprovalActionTest`, `ProcessSkillApprovalActionTest`, or anything else in this large existing suite.

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: passes; re-run Step 1 if it reformats anything.

- [ ] **Step 3: Report completion**

No manual tinker verification for this task — this repo's own `.github/instructions/laravel-boost.instructions.md` states "Do not create verification scripts or tinker when tests cover that functionality and prove they work," identical to the rule several other platforms in this session's program already follow. Tasks 1-4's tests already exercise the real end-to-end lifecycle: `test_platform_admin_can_approve_and_the_rows_are_actually_deleted` (`RetentionPurgeWorkflowTest`) and `test_platform_admin_can_approve_via_the_component` (`RetentionPurgeQueueTest`) both confirm real rows actually disappear from the database on approval, not just an in-memory status flip. No commit for this task — it's verification only. If Step 1 finds any failures, stop and fix them (return to the relevant earlier task) before considering this plan complete.

## Self-Review Notes

- **Spec coverage:** Task 1 covers spec §1 (`ExpireOverdueApprovals`). Task 2 covers spec §2 (`retention_purge_proposals` + `DetectRetentionPurgeCandidates`). Task 3 covers spec §3 (`RetentionPurgeProposalPolicy` + `ProcessRetentionPurgeAction`). Task 4 covers spec §4-§5 (`RetentionPurgeQueue` + schedule swap). Task 5 covers the spec's implicit "this all actually works together" requirement.
- **Placeholder scan:** none — every step has literal file content, including full migration/model/policy/DTO/event/Action/Livewire/view contents.
- **Type consistency:** `RetentionPurgeProposal::create()`'s fillable keys, `ProcessRetentionPurgeData::__construct(int $proposalId, string $decision, ?string $reviewerNotes)`, `ProcessRetentionPurgeAction::execute(RetentionPurgeProposal $proposal, ProcessRetentionPurgeData $data): RetentionPurgeProposal`, and `RetentionPurgeQueue::approve/reject(int $id)` are used identically everywhere they're referenced across Tasks 2-4.
- **Existing-architecture fidelity, checked against real files before writing, not assumed:** `ProcessRetentionPurgeAction`'s shape (constructor-injected `AuditService`, `Gate::authorize()`, `\RuntimeException` on an already-reviewed record, `event()` dispatch, `AuditService::logUserAction()`'s exact named-parameter signature) was copied from reading `ProcessApprovalAction.php` and `ApprovalWorkflowTest.php` directly, not inferred from the spec's prose alone.
- **`platform_admin` role provisioning matches this repo's own test convention exactly:** every test file in this plan creates the role with `Role::create(['name' => 'platform_admin'])` + `assignRole()`, the identical pattern already used in `tests/Feature/Actions/Security/EmergencyKillSwitchActionTest.php` — not a new convention invented for this feature.
- **Cross-tenant scoping addressed explicitly, not left implicit:** Task 1's command calls `withoutGlobalScope('organization')` explicitly even though `HasOrganizationScope`'s own docblock confirms scheduled commands (no session) are unaffected either way — matching the explicit style every existing `prunable()` method already uses, per the Global Constraints section.
- **"Reject = defer, re-propose" tested across two runs, not assumed:** Task 2's `test_a_rejected_proposal_gets_a_fresh_one_on_the_next_eligible_run` explicitly simulates two detection runs with a rejection in between, carrying forward the same test shape used for Dot.Sheet's and Dot.Tasks' analogous recurring-reassessment gates.
- **`AgentMessage`'s missing `prunable()` explicitly tested, not silently ignored:** Task 2's `test_the_command_does_not_error_on_agent_message_which_has_no_prunable_method` confirms the defensive `method_exists()` check actually works, matching the spec's explicit out-of-scope note that this plan doesn't invent a retention policy for it.
