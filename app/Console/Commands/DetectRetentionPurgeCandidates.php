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
