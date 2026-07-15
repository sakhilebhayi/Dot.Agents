<?php

namespace App\Actions\Governance;

use App\Events\DecisionLogCreated;
use App\Models\AgentDeployment;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Services\Governance\AuditService;
use App\Services\Governance\DelusionDetectionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateDecisionLogAction
{
    public function __construct(
        private readonly DelusionDetectionService $delusionDetector,
        private readonly AuditService $auditService
    ) {}

    public function execute(
        AgentDeployment $deployment,
        AgentTask $task,
        array $output,
        array $context = []
    ): DecisionLog {
        Gate::authorize('view', $deployment);

        // Run delusion analysis on the output
        $analysis = $this->delusionDetector->analyze(
            $task->description ?? '',
            $output,
            $task->input_data ?? []
        );

        $confidence = (float) ($output['confidence'] ?? 75.0);
        $requiresReview = $analysis['risk_score'] >= 60 || $confidence < $deployment->confidence_threshold;

        $decisionLog = DecisionLog::create([
            'uuid' => (string) Str::uuid(),
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
            'task_id' => $task->id,
            'decision_type' => $context['decision_type'] ?? 'task_output',
            'title' => $context['title'] ?? "Decision: {$task->title}",
            'decision_summary' => $output['summary'] ?? $output['content'] ?? '',
            'reasoning' => $output['reasoning'] ?? null,
            'evidence_used' => $output['evidence'] ?? [],
            'alternatives_considered' => $output['alternatives'] ?? [],
            'proposed_actions' => $output['actions'] ?? [],
            'confidence_score' => $confidence,
            'risk_score' => $output['risk_score'] ?? 0,
            'impact_score' => $output['impact_score'] ?? 50,
            'delusion_risk_score' => $analysis['risk_score'],
            'reality_alignment_score' => $analysis['reality_alignment'],
            'verification_score' => $analysis['verification_score'],
            'evidence_quality_score' => $analysis['evidence_quality'],
            'source_credibility_score' => $analysis['source_credibility'],
            'assumption_count' => $analysis['assumption_count'],
            'delusion_analysis' => $analysis,
            'compliance_checked' => true,
            'compliance_passed' => $analysis['risk_score'] < 60,
            'requires_human_review' => $requiresReview,
            'human_reviewed' => false,
        ]);

        if ($analysis['risk_score'] >= 60) {
            $this->auditService->logAgentAction($deployment, 'high_risk_decision_created', [
                'decision_log_id' => $decisionLog->id,
                'task_id' => $task->id,
                'risk_score' => $analysis['risk_score'],
                'flags' => $analysis['flags'],
            ]);
        }

        event(new DecisionLogCreated($decisionLog));

        return $decisionLog;
    }
}
