<?php

namespace App\Services\AI;

use App\Models\AgentDeployment;
use App\Models\AgentMessage;
use App\Models\AgentSession;
use App\Models\AgentTask;
use App\Models\AgentWorkflow;
use App\Models\DecisionLog;
use App\Models\Organization;
use App\Services\Governance\AuditService;
use App\Services\Governance\DelusionDetectionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Core AI orchestration coordinator.
 *
 * Responsibilities:
 * - Coordinate the message/task lifecycle (sandbox check → inference → moderation → persist)
 * - Multi-provider failover via CircuitBreaker
 * - Delegate prompt construction to PromptBuilderService
 * - Delegate response parsing and usage recording to ResponseProcessorService
 */
class AgentOrchestrationService
{
    public function __construct(
        private readonly ModelRouterService $modelRouter,
        private readonly DelusionDetectionService $delusionDetector,
        private readonly AuditService $auditService,
        private readonly MemoryService $memoryService,
        private readonly AgentSandboxService $sandbox,
        private readonly OutputModerationService $outputModeration,
        private readonly PromptBuilderService $promptBuilder,
        private readonly ResponseProcessorService $responseProcessor,
        private readonly AgentModelCaller $modelCaller,
        private readonly AgentQuotaGuard $quotaGuard,
    ) {}

    /**
     * Process a user message and generate an agent response.
     */
    public function processMessage(
        AgentDeployment $deployment,
        AgentSession $session,
        string $userMessage,
        array $context = []
    ): AgentMessage {
        $this->sandbox->assertPermitted($deployment, 'process_message', $context);

        $history = $this->promptBuilder->buildConversationHistory($session);
        $memoryContext = $this->memoryService->getRelevantMemories($deployment, $userMessage);

        $systemPrompt = Cache::remember(
            "agent_system_prompt_{$deployment->id}",
            600,
            fn () => $this->promptBuilder->buildSystemPrompt($deployment, $memoryContext, $context)
        );

        $startTime = microtime(true);
        $response = $this->modelCaller->callWithFailover(
            $deployment->id,
            $this->modelRouter->buildFailoverChain($deployment),
            $systemPrompt,
            $history,
            $userMessage
        );
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        ['content' => $moderatedContent, 'scan' => $moderationScan] = $this->outputModeration->scanAndRedact(
            $response['content'],
            ['deployment_id' => $deployment->id, 'session_id' => $session->id]
        );

        if ($moderationScan['verdict'] === OutputModerationService::BLOCK) {
            $moderatedContent = $this->outputModeration->blockedResponse();
        }

        $assistantMessage = AgentMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $moderatedContent,
            'token_count' => $response['usage']['total_tokens'] ?? 0,
            'cost' => $response['cost'] ?? 0,
            'model_used' => $response['model_used'] ?? 'gpt-4o',
            'latency_ms' => $latencyMs,
            'flagged' => $moderationScan['flagged'],
            'flag_reason' => $moderationScan['flag_reason'],
            'metadata' => [
                'finish_reason' => $response['finish_reason'] ?? null,
                'tool_calls' => $response['tool_calls'] ?? null,
                'moderation_verdict' => $moderationScan['verdict'],
                'provider' => $response['provider'] ?? null,
            ],
        ]);

        $session->increment('message_count', 2);
        $session->increment('token_count', $response['usage']['total_tokens'] ?? 0);

        $this->responseProcessor->recordUsage($deployment, $response, $session);

        if ($deployment->enable_memory) {
            $this->memoryService->processInteraction($deployment, $userMessage, $response['content']);
        }

        $this->auditService->logAgentAction($deployment, 'message_processed', [
            'session_id' => $session->id,
            'message_id' => $assistantMessage->id,
            'token_count' => $response['usage']['total_tokens'] ?? 0,
        ]);

        return $assistantMessage;
    }

    /**
     * Execute an agent task with full governance (delusion detection, approval flow).
     */
    public function executeTask(AgentDeployment $deployment, AgentTask $task): AgentTask
    {
        $this->sandbox->assertPermitted($deployment, 'execute_task', [
            'task_id' => $task->id,
            'organization_id' => $deployment->organization_id,
        ]);

        // ── Plan quota check: monthly_token_quota ────────────────────────────
        $org = Organization::find($deployment->organization_id);
        $this->quotaGuard->assertQuotaAvailable($deployment->organization_id, $org?->plan);

        $task->update(['status' => 'in_progress', 'started_at' => now()]);

        try {
            $taskPrompt = $this->promptBuilder->buildTaskPrompt($deployment, $task);

            $persona = $deployment->agent->defaultPersona;
            $systemPrompt = Cache::remember(
                "agent_system_prompt_{$deployment->id}",
                600,
                fn () => $persona?->system_prompt ?? $this->promptBuilder->buildDefaultSystemPrompt($deployment)
            );

            $startTime = microtime(true);
            $response = $this->modelCaller->callWithFailover(
                $deployment->id,
                $this->modelRouter->buildFailoverChain($deployment),
                $systemPrompt,
                [],
                $taskPrompt
            );
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $output = $this->responseProcessor->parseTaskOutput($response['content']);

            $delusionAnalysis = $this->delusionDetector->analyze(
                $task->description,
                $output,
                $task->input_data ?? []
            );

            $confidenceScore = $output['confidence'] ?? 75.0;
            $requiresApproval = $deployment->requiresApprovalFor($confidenceScore)
                || $delusionAnalysis['risk_score'] >= 60;

            $task->update([
                'status' => $requiresApproval ? 'awaiting_approval' : 'completed',
                'output_data' => $output,
                'result_summary' => $output['summary'] ?? substr($response['content'], 0, 500),
                'confidence_score' => $confidenceScore,
                'risk_score' => $delusionAnalysis['risk_score'],
                'delusion_risk_score' => $delusionAnalysis['risk_score'],
                'reality_alignment_score' => $delusionAnalysis['reality_alignment'],
                'actual_duration_minutes' => (int) ($durationMs / 60000),
                'token_count' => $response['usage']['total_tokens'] ?? 0,
                'cost' => $response['cost'] ?? 0,
                'completed_at' => $requiresApproval ? null : now(),
            ]);

            $decisionLog = DecisionLog::create([
                'agent_deployment_id' => $deployment->id,
                'organization_id' => $deployment->organization_id,
                'task_id' => $task->id,
                'input_hash' => hash('sha256', $taskPrompt),
                'model_used' => $response['model_used'] ?? 'gpt-4o',
                'decision_type' => $task->task_type,
                'title' => "Task: {$task->title}",
                'decision_summary' => $task->result_summary,
                'reasoning' => $output['reasoning'] ?? null,
                'evidence_used' => $output['evidence'] ?? null,
                'confidence_score' => $confidenceScore,
                'risk_score' => $delusionAnalysis['risk_score'],
                'impact_score' => $output['impact_score'] ?? 50,
                'delusion_risk_score' => $delusionAnalysis['risk_score'],
                'reality_alignment_score' => $delusionAnalysis['reality_alignment'],
                'verification_score' => $delusionAnalysis['verification_score'],
                'evidence_quality_score' => $delusionAnalysis['evidence_quality'],
                'source_credibility_score' => $delusionAnalysis['source_credibility'],
                'assumption_count' => $delusionAnalysis['assumption_count'],
                'delusion_analysis' => $delusionAnalysis['analysis'],
                'requires_human_review' => $requiresApproval,
            ]);

            if ($requiresApproval) {
                $this->responseProcessor->createApprovalRequest($deployment, $task, $decisionLog, $delusionAnalysis);
            }

            $this->responseProcessor->recordUsage($deployment, $response, null, $task);

            $this->auditService->logAgentAction($deployment, 'task_executed', [
                'task_id' => $task->id,
                'status' => $task->status,
                'requires_approval' => $requiresApproval,
                'confidence_score' => $confidenceScore,
                'delusion_risk' => $delusionAnalysis['risk_score'],
            ]);

        } catch (\Throwable $e) {
            $task->update([
                'status' => 'failed',
                'output_data' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);

            $this->auditService->logAgentAction($deployment, 'task_failed', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $task->fresh();
    }

    /**
     * Execute a single graph node via agent key.
     * Used by GraphWorkflowEngineService to drive workflow node execution.
     *
     * @return array{ status: string, output: array, confidence: float }
     */
    public function executeGraphNode(
        string $agentKey,
        array $context,
        array $nodeConfig = [],
        array $metadata = []
    ): array {
        $organizationId = isset($metadata['workflow_id'])
            ? AgentWorkflow::find($metadata['workflow_id'])?->organization_id
            : null;

        $deployment = AgentDeployment::whereHas(
            'agent', fn ($q) => $q->where('key', $agentKey)
        )
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->where('status', 'active')
            ->first();

        if (! $deployment) {
            return [
                'status' => 'skipped',
                'output' => [],
                'confidence' => 0.0,
                'reason' => "No active deployment found for agent key [{$agentKey}]",
            ];
        }

        $task = AgentTask::create([
            'uuid' => (string) Str::uuid(),
            'agent_deployment_id' => $deployment->id,
            'organization_id' => $deployment->organization_id,
            'title' => "Graph node: {$agentKey}",
            'description' => 'Executed as part of graph workflow #'.($metadata['workflow_id'] ?? 'unknown'),
            'task_type' => 'workflow',
            'priority' => 'medium',
            'status' => 'pending',
            'input_data' => $context,
            'metadata' => $metadata,
        ]);

        $completedTask = $this->executeTask($deployment, $task);

        return [
            'status' => $completedTask->status,
            'output' => $completedTask->output_data ?? [],
            'confidence' => (float) ($completedTask->confidence_score ?? 0),
            'task_id' => $completedTask->id,
        ];
    }

    /**
     * Call the AI model with automatic multi-provider failover.
     */
}
