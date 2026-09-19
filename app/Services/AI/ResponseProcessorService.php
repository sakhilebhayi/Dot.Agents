<?php

namespace App\Services\AI;

use App\Events\ApprovalRequested;
use App\Models\AgentApproval;
use App\Models\AgentDeployment;
use App\Models\AgentSession;
use App\Models\AgentTask;
use App\Models\DecisionLog;
use App\Models\UsageRecord;

/**
 * Handles AI response post-processing: output parsing, usage recording,
 * and approval request creation.
 *
 * Extracted from AgentOrchestrationService to provide a single, testable
 * home for all post-inference processing concerns.
 */
class ResponseProcessorService
{
    /**
     * Parse structured JSON output from an AI model response.
     * Falls back gracefully to a text envelope if JSON cannot be parsed.
     */
    public function parseTaskOutput(string $content): array
    {
        // Try to extract JSON from fenced code block
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded) {
                return $decoded;
            }
        }

        // Try raw JSON
        $decoded = json_decode($content, true);
        if ($decoded) {
            return $decoded;
        }

        // Fallback to text envelope
        return [
            'summary' => substr($content, 0, 500),
            'result' => ['raw_output' => $content],
            'confidence' => 60.0,
            'reasoning' => $content,
            'evidence' => [],
            'assumptions' => [],
            'risks' => [],
            'recommendations' => [],
            'impact_score' => 50,
        ];
    }

    /**
     * Record token/cost usage for billing and analytics.
     */
    public function recordUsage(
        AgentDeployment $deployment,
        array $response,
        ?AgentSession $session,
        ?AgentTask $task = null
    ): void {
        UsageRecord::create([
            'organization_id' => $deployment->organization_id,
            'agent_deployment_id' => $deployment->id,
            'metric_type' => 'tokens',
            'quantity' => $response['usage']['total_tokens'] ?? 0,
            'unit_cost' => 0.00002,
            'total_cost' => $response['cost'] ?? 0,
            'model_used' => $response['model'] ?? 'gpt-4o',
            'reference_type' => $session ? 'agent_session' : 'agent_task',
            'reference_id' => $session->id ?? $task?->id,
            'recorded_date' => now()->toDateString(),
        ]);
    }

    /**
     * Create a human-approval request for a completed task that exceeds
     * the deployment's confidence threshold or delusion risk threshold.
     */
    public function createApprovalRequest(
        AgentDeployment $deployment,
        AgentTask $task,
        DecisionLog $decisionLog,
        array $delusionAnalysis
    ): void {
        $approval = AgentApproval::create([
            'task_id' => $task->id,
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
            'requested_from' => $deployment->deployed_by,
            'approval_type' => 'action',
            'title' => "Approval Required: {$task->title}",
            'description' => 'This task requires human approval before proceeding.',
            'proposed_action' => $task->output_data,
            'impact_assessment' => [
                'delusion_risk' => $delusionAnalysis['risk_score'],
                'confidence_score' => $task->confidence_score,
                'risk_level' => $task->risk_score >= 70 ? 'high' : 'medium',
            ],
            'risk_level' => $task->risk_score >= 70 ? 'high' : 'medium',
            'confidence_score' => $task->confidence_score,
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        event(new ApprovalRequested($approval));
    }
}
