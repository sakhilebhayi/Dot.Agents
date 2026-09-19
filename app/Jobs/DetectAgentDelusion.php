<?php

namespace App\Jobs;

use App\Events\AgentDriftDetected;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Services\Governance\AuditService;
use App\Services\Governance\DelusionDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class DetectAgentDelusion implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public readonly AgentTask $task
    ) {
        $this->onQueue('governance');
    }

    public function handle(DelusionDetectionService $detector, AuditService $auditService): void
    {
        if (empty($this->task->output_data)) {
            return;
        }

        $analysis = $detector->analyze(
            $this->task->description ?? '',
            $this->task->output_data,
            $this->task->input_data ?? []
        );

        // Update the task with delusion scores
        $this->task->update([
            'delusion_risk_score' => $analysis['risk_score'],
            'reality_alignment_score' => $analysis['reality_alignment'],
        ]);

        // Create or update the associated decision log
        $decisionLog = DecisionLog::updateOrCreate(
            ['task_id' => $this->task->id],
            [
                'agent_deployment_id' => $this->task->agent_deployment_id,
                'organization_id' => $this->task->organization_id,
                'decision_type' => 'task_output',
                'title' => "Delusion Analysis: {$this->task->title}",
                'decision_summary' => $analysis['analysis'],
                'confidence_score' => $this->task->output_data['confidence'] ?? 75,
                'delusion_risk_score' => $analysis['risk_score'],
                'reality_alignment_score' => $analysis['reality_alignment'],
                'verification_score' => $analysis['verification_score'],
                'evidence_quality_score' => $analysis['evidence_quality'],
                'source_credibility_score' => $analysis['source_credibility'],
                'assumption_count' => $analysis['assumption_count'],
                'delusion_analysis' => $analysis,
                'requires_human_review' => $analysis['risk_score'] >= 60,
                'compliance_checked' => true,
            ]
        );

        // If delusion risk is high, flag for human review and fire drift event
        if ($analysis['risk_score'] >= 60) {
            $deployment = $this->task->deployment;

            if (! $deployment) {
                return;
            }

            event(new AgentDriftDetected(
                $deployment,
                'high_delusion_risk',
                $analysis['risk_score'] >= 80 ? 'critical' : 'warning',
                [
                    'task_id' => $this->task->id,
                    'risk_score' => $analysis['risk_score'],
                    'flags' => $analysis['flags'],
                ]
            ));

            $auditService->logAgentAction($deployment, 'delusion_risk_flagged', [
                'task_id' => $this->task->id,
                'risk_score' => $analysis['risk_score'],
                'flags' => $analysis['flags'],
                'recommendation' => $analysis['recommendation'],
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('[DetectAgentDelusion] Delusion detection failed', [
            'task_id' => $this->task->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
