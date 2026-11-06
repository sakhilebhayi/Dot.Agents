<?php

namespace App\Services\Tools\BuiltIn;

use App\Contracts\AgentTool;
use App\Models\AgentMemory;
use App\Services\AI\OutputModerationService;
use App\Services\Tools\ToolContext;
use App\Services\Tools\ToolDefinition;
use App\Services\Tools\ToolFailure;
use App\Services\Tools\ToolOutput;
use App\Support\RunTaint;

/**
 * Saves something for later runs. What is saved is not trusted yet: an agent below controlled autonomy, or one that has
 * read outside content, can only propose a memory, and nothing proposed is used until a person accepts it.
 */
class RememberFact implements AgentTool
{
    private const CONTROLLED = 4;

    public function __construct(private readonly OutputModerationService $moderation) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            key: 'remember_fact',
            label: 'Remember something',
            category: 'Business data',
            description: 'Saves one short fact for later work. Use it for lasting facts and preferences, never for a passing detail.',
            accesses: 'This agent\'s memory. A person can read, correct or remove anything saved.',
            schema: ['type' => 'object', 'properties' => [
                'subject' => ['type' => 'string', 'maxLength' => 120],
                'fact' => ['type' => 'string', 'maxLength' => 1000],
                'about' => ['type' => 'string', 'enum' => ['this_person', 'this_agent']],
            ], 'required' => ['subject', 'fact', 'about']],
            safeToRepeat: false,
        );
    }

    public function run(ToolContext $context, array $arguments): ToolOutput
    {
        $run = $context->run;

        if ($run->is_test) {
            return new ToolOutput('[Test run] Nothing was saved.');
        }

        $scan = $this->moderation->scan($arguments['fact']);

        if (isset($scan['flags']['secrets'])) {
            throw new ToolFailure('Credentials are never remembered.');
        }

        $personal = $arguments['about'] === 'this_person' && $run->initiated_by !== null;
        $trusted = (int) $run->deployment->autonomy_level >= self::CONTROLLED && ! RunTaint::has($run, RunTaint::UNTRUSTED) && ! isset($scan['flags']['pii']);

        AgentMemory::create([
            'agent_deployment_id' => $run->agent_deployment_id,
            'organization_id' => $run->organization_id,
            'user_id' => $personal ? $run->initiated_by : null,
            'scope' => $personal ? AgentMemory::SCOPE_PERSON : AgentMemory::SCOPE_AGENT,
            'status' => $trusted ? AgentMemory::ACTIVE : AgentMemory::PROPOSED,
            'memory_type' => 'long_term',
            'subject' => $arguments['subject'],
            'content' => $arguments['fact'],
            'agent_run_id' => $run->id,
            'source' => "Saved by {$run->deployment->name} during a run",
            'expires_at' => now()->addDays((int) $run->configVersion->settings()['memory']['retention_days']),
        ]);

        return new ToolOutput($trusted ? 'Saved.' : 'Saved for a person to review. It will not be used until they accept it.');
    }
}
