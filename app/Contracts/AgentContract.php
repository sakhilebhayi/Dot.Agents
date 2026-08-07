<?php

namespace App\Contracts;

/**
 * AgentContract — formal interface for all Dot.Agents skill implementations.
 *
 * Every AI agent skill class MUST implement this interface to be:
 *   1. Loadable by the AgentOrchestrationService
 *   2. Subject to governance hooks (delusion detection, confidence scoring)
 *   3. Eligible for listing in the Agent Marketplace
 *   4. Auditable via the AuditService
 *
 * Implementation checklist:
 *   ✅ execute() — primary skill logic, returns typed SkillResult
 *   ✅ getCapabilities() — declares what the skill can do (used for contract comparison)
 *   ✅ getRequiredPermissions() — permissions the platform must grant before execution
 *   ✅ getConfidenceThreshold() — minimum confidence below which human approval is required
 *   ✅ supports() — indicates whether the skill can handle a given task input
 */
interface AgentContract
{
    /**
     * Execute the skill with the provided input.
     *
     * Implementations must:
     * - Validate input against their own schema
     * - Return a result with a confidence_score between 0 and 100
     * - Never perform side effects that are not declared in getCapabilities()
     *
     * @param  array<string, mixed>  $input  Task-specific payload
     * @return array{
     *     status: string,
     *     result: mixed,
     *     confidence_score: float,
     *     tokens_used: int,
     *     metadata: array<string, mixed>
     * }
     */
    public function execute(array $input): array;

    /**
     * Declare the capability identifiers this skill provides.
     * Used by the governance system to detect breaking capability contract changes.
     *
     * @return string[] e.g. ['read_crm', 'draft_email', 'qualify_lead']
     */
    public function getCapabilities(): array;

    /**
     * Declare the platform permission keys required before this skill may run.
     * The deployment authorization system enforces these before calling execute().
     *
     * @return string[] e.g. ['crm.read', 'email.send']
     */
    public function getRequiredPermissions(): array;

    /**
     * The minimum confidence score (0–100) required for autonomous execution.
     * Tasks scoring below this threshold are routed to the human approval queue.
     * Return 0 to always execute autonomously (not recommended for high-risk skills).
     */
    public function getConfidenceThreshold(): float;

    /**
     * Indicate whether this skill is capable of handling the given task input.
     * The orchestration engine calls this to select the right skill for a task.
     *
     * @param  array<string, mixed>  $input
     */
    public function supports(array $input): bool;
}
