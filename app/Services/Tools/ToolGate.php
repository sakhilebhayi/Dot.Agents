<?php

namespace App\Services\Tools;

use App\Models\AgentRun;
use App\Services\AI\ToolPermissionService;
use App\Services\Approvals\ApprovalDesk;
use App\Support\RunTaint;

/**
 * The one place that decides whether a tool call happens, in PHP, before every call. The model has no say: whatever it
 * was told by a document or a web page, the worst it can do is ask for something this class refuses.
 *
 * Levels are AgentConfig::AUTONOMY. A tool with a ceiling always goes to a person, at every level.
 */
class ToolGate
{
    private const ASSIST = 1;

    private const CONTROLLED = 4;

    public function __construct(
        private readonly ToolPermissionService $permissions,
        private readonly ToolArguments $arguments,
        private readonly ApprovalDesk $desk,
    ) {}

    /**
     * @param  array{key: string, mode: string}|null  $grant  from the run's pinned version
     * @param  array<string, mixed>  $arguments
     */
    public function decide(AgentRun $run, ?ToolDefinition $tool, ?array $grant, array $arguments): GateDecision
    {
        if ($refused = $this->refused($run, $tool, $grant)) {
            return $refused;
        }

        if ($errors = $this->arguments->errors($tool->schema, $arguments)) {
            return GateDecision::deny('invalid_arguments', implode(' ', $errors));
        }

        return $this->trifecta($run, $tool) ?? $this->ruled($run, $tool) ?? $this->autonomy($run, $tool, $grant);
    }

    /**
     * What is settled before any arguments exist, which also decides what the model is offered.
     *
     * @param  array{key: string, mode: string}|null  $grant
     */
    public function refused(AgentRun $run, ?ToolDefinition $tool, ?array $grant): ?GateDecision
    {
        if ($tool === null) {
            return GateDecision::deny('unknown_tool', 'There is no such tool.');
        }

        if ($grant === null) {
            return GateDecision::deny('not_granted', 'This agent has not been given this tool.');
        }

        if ($this->permissions->isDenied($run->deployment, $tool->key)) {
            return GateDecision::deny('business_denied', 'This business does not allow this tool.');
        }

        if (($tool->writesState || $tool->ceiling) && $this->level($run) === self::ASSIST) {
            return GateDecision::deny('autonomy', 'This agent assists only, so it cannot change anything.');
        }

        return null;
    }

    /** Sensitive data, outside content and a way out, all in one run, is how data leaves. A person looks first. */
    private function trifecta(AgentRun $run, ToolDefinition $tool): ?GateDecision
    {
        $sensitive = RunTaint::has($run, RunTaint::SENSITIVE) || $tool->readsSensitive;

        if ($tool->externalReach && $sensitive && RunTaint::has($run, RunTaint::UNTRUSTED)) {
            return GateDecision::ask('trifecta', 'This run has read outside content and sensitive information, so sending anything out needs a person.');
        }

        return null;
    }

    /** A business's own rules can add a person to any action. They can never remove one. */
    private function ruled(AgentRun $run, ToolDefinition $tool): ?GateDecision
    {
        $rule = $this->desk->rule($run->organization_id, $tool);

        return $rule === null ? null : GateDecision::ask('business_rule', "This business's rule \"{$rule->name}\" sends this to a person.");
    }

    /** @param  array{key: string, mode: string}  $grant */
    private function autonomy(AgentRun $run, ToolDefinition $tool, array $grant): GateDecision
    {
        if ($tool->ceiling !== null) {
            return GateDecision::ask('ceiling', "This is {$tool->ceiling}, which always needs a person.");
        }

        if ($grant['mode'] === 'approve') {
            return GateDecision::ask('tool_needs_approval', 'This tool is set to ask a person first.');
        }

        if (! $tool->writesState) {
            return GateDecision::allow();
        }

        if ($this->level($run) < self::CONTROLLED) {
            return GateDecision::ask('autonomy', 'This agent acts only after a person approves.');
        }

        // Having read outside content lowers what an agent may do alone for the rest of the run.
        return RunTaint::has($run, RunTaint::UNTRUSTED)
            ? GateDecision::ask('tainted', 'This run has read outside content, so changes need a person.')
            : GateDecision::allow();
    }

    private function level(AgentRun $run): int
    {
        return (int) $run->deployment->autonomy_level;
    }
}
