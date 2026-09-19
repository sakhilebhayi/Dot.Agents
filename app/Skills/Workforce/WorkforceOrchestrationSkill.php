<?php

namespace App\Skills\Workforce;

use App\Models\AgentDeployment;
use App\Skills\BaseSkill;
use App\Skills\DTOs\SkillResult;

/**
 * Workforce Orchestration Skill (Layer 3 — Workforce)
 *
 * THE flagship meta-skill. Every agent with this skill can ask:
 *
 *   "Can I solve this myself?"
 *       YES → Execute directly
 *       NO  → Decompose → Delegate → Monitor → Return outcome
 *
 * This transforms isolated agents into coordinated digital workforce members.
 * It is the root skill that activates the full agent collaboration graph.
 *
 * Decision tree:
 *   1. Assess self-capability (confidence vs threshold, complexity estimate)
 *   2. If capable: return 'execute' signal
 *   3. If not: invoke TaskDecompositionSkill
 *   4. If subtasks found: invoke DelegationSkill
 *   5. Return 'delegated' result with assignment manifest
 */
class WorkforceOrchestrationSkill extends BaseSkill
{
    public function key(): string
    {
        return 'workforce-orchestration';
    }

    public function layer(): string
    {
        return 'workforce';
    }

    /**
     * Input keys:
     *   task_description    – string description of the work
     *   initial_confidence  – float 0-100 (current agent's self-assessed confidence)
     */
    public function execute(array $input, array $context = []): SkillResult
    {
        $task = $input['task_description'] ?? $input['task'] ?? '';
        $confidence = (float) ($input['initial_confidence'] ?? 70.0);

        /** @var AgentDeployment|null $deployment */
        $deployment = $context['deployment'] ?? null;

        // ── Step 1: Assess self-capability ───────────────
        $assessment = $this->assessSelfCapability($task, $confidence, $deployment);

        if ($assessment['can_handle']) {
            return SkillResult::completed(
                [
                    'decision' => 'execute',
                    'reasoning' => $assessment['reasoning'],
                    'complexity_score' => $assessment['complexity_score'],
                ],
                $confidence
            );
        }

        // ── Step 2: Decompose task ────────────────────────
        $decomposition = app(TaskDecompositionSkill::class)->execute(
            ['task' => $task, 'complexity_score' => $assessment['complexity_score']],
            $context
        );

        $subtasks = $decomposition->output['subtasks'] ?? [];

        if (empty($subtasks)) {
            // Cannot decompose — execute directly but flag low confidence
            return SkillResult::completed(
                [
                    'decision' => 'execute_with_caution',
                    'reasoning' => 'Task could not be decomposed; executing directly with elevated uncertainty',
                ],
                $this->clamp($confidence - 20),
                ['Task is at the edge of agent capability — recommend human review of output']
            );
        }

        // ── Step 3: Delegate subtasks ─────────────────────
        $delegation = app(DelegationSkill::class)->execute(
            ['subtasks' => $subtasks, 'parent_task' => $task],
            $context
        );

        $assignedCount = $delegation->output['assigned_agents_count'] ?? 0;
        $unassigned = $delegation->output['unassigned_count'] ?? 0;

        $findings = [];
        if ($unassigned > 0) {
            $findings[] = "{$unassigned} subtask(s) unassigned — no qualified agents available";
        }

        return SkillResult::delegated(
            [
                'decision' => 'delegated',
                'subtask_count' => count($subtasks),
                'assigned_count' => $assignedCount,
                'unassigned_count' => $unassigned,
                'assignments' => $delegation->output['assignments'] ?? [],
                'decomposition' => $decomposition->output,
            ],
            $decomposition->confidence,
            $findings
        );
    }

    // ── Internal ─────────────────────────────────────────

    private function assessSelfCapability(string $task, float $confidence, ?AgentDeployment $deployment): array
    {
        $confidenceThreshold = (float) ($deployment->confidence_threshold ?? 75.0);
        $complexityScore = $this->estimateComplexity($task);

        // Can handle if confidence meets threshold AND complexity is manageable
        $canHandle = $confidence >= $confidenceThreshold && $complexityScore <= 65.0;

        return [
            'can_handle' => $canHandle,
            'complexity_score' => $complexityScore,
            'reasoning' => $canHandle
                ? "Confidence ({$confidence}%) ≥ threshold ({$confidenceThreshold}%) and complexity ({$complexityScore}/100) is within range"
                : "Complexity ({$complexityScore}/100) or confidence ({$confidence}%) requires delegation",
        ];
    }

    private function estimateComplexity(string $task): float
    {
        $markers = [
            'analyz', 'compar', 'evaluat', 'synthesiz', 'design', 'architect',
            'multiple', 'cross-department', 'strategic', 'comprehensive',
        ];

        $markerCount = $this->countKeywords($task, $markers);
        $wordCount = str_word_count($task);

        return $this->clamp(
            ($markerCount * 15) + ($wordCount > 50 ? 20 : 0) + ($wordCount > 100 ? 20 : 0)
        );
    }
}
